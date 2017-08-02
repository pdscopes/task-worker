<?php

namespace MadeSimple\TaskWorker\Queue;

use MadeSimple\TaskWorker\Cache\NullCache;
use MadeSimple\TaskWorker\Queue;
use MadeSimple\TaskWorker\Task;
use MadeSimple\TaskWorker\Worker;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class SynchronousQueue
 *
 * @package MadeSimple\TaskWorker\Queue
 * @author  Peter Scopes
 */
class SynchronousQueue implements Queue
{
    use LoggerAwareTrait;

    /**
     * @var Task
     */
    private $task;

    /**
     * SynchronousQueue constructor.
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->setLogger($logger ?? new NullLogger());
    }

    /**
     * Performs task. If delay is set will delay execution.
     *
     * @param Task $task
     *
     * @return bool
     */
    function add(Task $task): bool
    {
        // Delay execution
        if ($task->delay() > 0) {
            sleep($task->delay());
        }

        $this->task = $task;

        (new Worker(new NullCache(), $this->logger))
            ->setQueue($this)
            ->setOption(Worker::OPT_REST, 0)
            ->setOption(Worker::OPT_MAX_TASKS, 1)
            ->run();

        $this->task = null;

        return false;
    }

    function reserve()
    {
        return $this->task;
    }

    function release(Task $task): bool
    {
        return true;
    }

    function remove(Task $task): bool
    {
        return true;
    }

    function fail(Task $task, \Throwable $throwable)
    {
    }
}