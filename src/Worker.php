<?php

namespace MadeSimple\TaskWorker;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class Worker
 *
 * @package MadeSimple\TaskWorker
 * @author  Peter Scopes
 */
class Worker
{
    use HasCacheTrait, LoggerAwareTrait, HasOptionsTrait;

    /** Name of the cache key for restarting workers. */
    const CACHE_RESTART = 'simple-task-worker-restart';

    /** Option to set how long, in seconds, to wait if not tasks are in the queue before checking again. */
    const OPT_SLEEP = 'sleep';
    /** Option to set how many attempts a task is allowed before being failed (zero is unlimited). */
    const OPT_ATTEMPTS = 'attempts';
    /** Option to set how long, in seconds, the worker will stay alive for (zero is unlimited). */
    const OPT_ALIVE = 'alive';
    /** Option to set how long, in milliseconds, to rest between tasks. */
    const OPT_REST = 'rest';
    /** Option to set the maximum number of tasks a worker should perform before stopping (zero is unlimited). */
    const OPT_MAX_TASKS = 'max-tasks';

    /**
     * @return array
     */
    public static function defaultOptions() : array
    {
        return [
            self::OPT_SLEEP     => 3,
            self::OPT_ATTEMPTS  => 0,
            self::OPT_ALIVE     => 0,
            self::OPT_REST      => 50,
            self::OPT_MAX_TASKS => 0,
        ];
    }

    /**
     * Broadcast a restart signal to all workers.
     *
     * @param CacheItemPoolInterface $cache
     *
     * @return bool
     */
    public static function restart(CacheItemPoolInterface $cache) : bool
    {
        return $cache->save($cache->getItem(sha1(self::CACHE_RESTART))->set(time()));
    }


    /**
     * @var Queue
     */
    protected $queue;

    /**
     * @var int
     */
    protected $startTime;

    /**
     * @var int
     */
    protected $taskCount;

    /**
     * @var array
     */
    protected $handlers = [];

    /**
     * TaskWorker constructor.
     *
     * @param CacheItemPoolInterface $cache
     * @param LoggerInterface        $logger
     */
    public function __construct(CacheItemPoolInterface $cache, LoggerInterface $logger = null)
    {
        $this->setCache($cache);
        $this->setLogger($logger ?? new NullLogger());
        $this->setOptions(static::defaultOptions());
    }

    /**
     * @param Queue $queue
     *
     * @return Worker
     */
    public function setQueue(Queue $queue)
    {
        $this->queue = $queue;

        return $this;
    }

    /**
     * Add a closure that is called on every task before it is performed. The closure receives a single argument
     * that is the task.
     *
     * @param \closure $handler
     *
     * @return Worker
     */
    public function addHandler(\closure $handler)
    {
        $this->handlers[] = $handler;

        return $this;
    }

    /**
     * @return int 0 if everything went fine, or an error code
     */
    public function run(): int
    {
        $this->logger->debug('Listening to queue', ['worker' => get_class($this), 'queue' => get_class($this->queue), 'options' => $this->options]);
        $this->startTime = time();
        $this->taskCount = 1;

        try {
            do {
                // Retrieve next task
                $task = $this->queue->reserve();

                // Perform task
                if ($task !== null) {
                    try {
                        $this->prepare($task)->perform();

                        $this->queue->remove($task);
                        $this->taskCount++;
                    }
                    catch (\Throwable $throwable) {
                        $this->logger->critical($throwable->getMessage(), ['trace' => $throwable->getTrace()]);
                        if ($this->opt(self::OPT_ATTEMPTS) > 0 && $task->attempts() >= $this->opt(static::OPT_ATTEMPTS)) {
                            $this->logger->critical('Task failed', ['task' => $task]);
                            $task->fail($throwable);
                            $this->queue->fail($task, $throwable);
                            $this->taskCount++;
                        } else {
                            $this->queue->release($task);
                        }
                    }

                    // Give system a little respite
                    if ($this->opt(self::OPT_REST) > 0) {
                        usleep(min(1,$this->opt(self::OPT_REST)));
                    }
                } else {
                    // Patiently wait for next task to arrive
                    sleep(min(1, $this->opt(self::OPT_SLEEP)));
                }
            }
            while ($this->shouldContinueWorking());
        }
        catch (\Throwable $throwable) {
            $this->logger->critical($throwable->getMessage(), ['trace' => $throwable->getTrace()]);

            return 1;
        }

        return 0;
    }

    /**
     * Prepares the task ready to be performed. Sets the logger, increments the attempts count, and calls all
     * handlers on the task.
     *
     * @param Task $task
     *
     * @return Task
     */
    protected function prepare(Task $task) : Task
    {
        $task->setLogger($this->logger);
        $task->incrementAttempts();

        foreach ($this->handlers as $handler) {
            $handler($task);
        }

        return $task;
    }

    /**
     * @return bool
     */
    protected function shouldContinueWorking(): bool
    {
        $optMaxTasks = $this->opt(self::OPT_MAX_TASKS);
        $optAlive    = $this->opt(self::OPT_ALIVE);
        $restartTime = ($item = $this->cache->getItem(sha1(self::CACHE_RESTART)))->isHit() ? $item->get() : 0;

        return
            ($optMaxTasks <= 0 || $optMaxTasks > $this->taskCount) &&
            ($optAlive <= 0 || $optAlive > round(time() - $this->startTime)) &&
            ($restartTime === 0 || $restartTime <= $this->startTime);
    }
}