<?php

namespace MadeSimple\TaskWorker;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

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
    const CACHE_RESTART = 'simpleTaskWorkerRestart';

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
    /** Option to set the worker to stop when the queue it empty (boolean 1 or 0). */
    const OPT_UNTIL_EMPTY = 'until-empty';

    /**
     * @return array
     */
    public static function defaultOptions() : array
    {
        return [
            self::OPT_SLEEP       => 3,
            self::OPT_ATTEMPTS    => 0,
            self::OPT_ALIVE       => 0,
            self::OPT_REST        => 50,
            self::OPT_MAX_TASKS   => 0,
            self::OPT_UNTIL_EMPTY => 0,
        ];
    }

    /**
     * Broadcast a restart signal to all workers.
     *
     * @param CacheInterface $cache
     *
     * @return bool
     */
    public static function restart(CacheInterface $cache) : bool
    {
        try {
            return $cache->set(self::CACHE_RESTART, time());
        } catch (InvalidArgumentException $e) {
            return false;
        }
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
     * @param CacheInterface  $cache
     * @param LoggerInterface $logger
     */
    public function __construct(CacheInterface $cache, LoggerInterface $logger = null)
    {
        $this->setCache($cache);
        $this->setLogger($logger ?? new NullLogger());
        $this->setOptions(static::defaultOptions());
    }

    /**
     * @param Queue $queue
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
                }

                // Should we continue working?
                if (!$this->shouldContinueWorking($task)) {
                    break;
                }

                // Patiently wait for next task to arrive
                if ($task === null) {
                    sleep(min(1, $this->opt(self::OPT_SLEEP)));
                }
            }
            while (true);
        }
        catch (\Throwable $throwable) {
            $this->logger->critical($throwable->getMessage(), ['trace' => $throwable->getTrace()]);

            return 1;
        }
        finally {
            $this->logger->debug('Terminating', ['worker' => get_class($this), 'queue' => get_class($this->queue), 'options' => $this->options]);
        }

        return 0;
    }

    /**
     * Prepares the task ready to be performed. Sets the logger, increments the attempts count, and calls all
     * handlers on the task.
     *
     * @param Task $task
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
     * Determines whether the worker should continue to work.
     *
     * @param Task $task
     * @return bool
     */
    protected function shouldContinueWorking(Task $task = null): bool
    {
        try {
            $optMaxTasks   = $this->opt(self::OPT_MAX_TASKS);
            $optAlive      = $this->opt(self::OPT_ALIVE);
            $restartTime   = $this->cache->get(self::CACHE_RESTART, 0);
            $optUntilEmpty = $this->opt(self::OPT_UNTIL_EMPTY);

            return
                ($optMaxTasks <= 0 || $optMaxTasks > $this->taskCount)
                && ($optAlive <= 0 || $optAlive > round(time() - $this->startTime))
                && ($restartTime === 0 || $restartTime <= $this->startTime)
                && (!$optUntilEmpty || $task !== null);
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }
}