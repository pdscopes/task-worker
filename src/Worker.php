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
    /** Option to set maximum number of tasks a worker should perofmr before stopping (zero is unlimited). */
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
                        $task->setLogger($this->logger);
                        $task->incrementAttempts();

                        $task->perform();
                        $this->queue->remove($task);
                        $this->taskCount++;
                    }
                    catch (\Throwable $throwable) {
                        $this->logger->critical($throwable->getMessage(), ['trace' => $throwable->getTrace()]);
                        if ($this->options[self::OPT_ATTEMPTS] > 0 && $task->attempts() >= $this->options[static::OPT_ATTEMPTS]) {
                            $this->logger->critical('Task failed', ['task' => $task]);
                            $task->fail($throwable);
                            $this->queue->fail($task, $throwable);
                            $this->taskCount++;
                        } else {
                            $this->queue->release($task);
                        }
                    }

                    // Give system a little respite
                    if ($this->options[self::OPT_REST] > 0) {
                        usleep($this->options[self::OPT_REST]);
                    }
                } else {
                    // Patiently wait for next task to arrive
                    sleep($this->options[self::OPT_SLEEP]);
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
     * @return bool
     */
    protected function shouldContinueWorking(): bool
    {
        $optMaxTasks = $this->options[self::OPT_MAX_TASKS];
        $optAlive    = $this->options[self::OPT_ALIVE];
        $restartTime = ($item = $this->cache->getItem(sha1(self::CACHE_RESTART)))->isHit() ? $item->get() : 0;

        return
            ($optMaxTasks <= 0 || $optMaxTasks > $this->taskCount) &&
            ($optAlive <= 0 || $optAlive > round(time() - $this->startTime)) &&
            ($restartTime === 0 || $restartTime <= $this->startTime);
    }
}