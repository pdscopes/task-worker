<?php

namespace MadeSimple\TaskWorker\Queue;

use MadeSimple\TaskWorker\Cache\NullCache;
use MadeSimple\TaskWorker\Queue;
use MadeSimple\TaskWorker\Task;
use MadeSimple\TaskWorker\Worker;
use Psr\Log\LoggerAwareTrait;

class SynchronousQueue implements Queue
{
    use LoggerAwareTrait;

    /**
     * @var Task
     */
    private $task;

    /**
     * SynchronousQueue constructor.
     */
    public function __construct() {}

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
        $this->logger->debug('Added task: ' . $task->serialize());

        (new Worker(NullCache::getInstance(), $this->logger))
            ->setQueue($this)
            ->setOption(Worker::OPT_REST, 0)
            ->setOption(Worker::OPT_MAX_TASKS, 1)
            ->run();

        $this->task = null;

        return true;
    }

    function reserve(array &$register)
    {
        return $this->task;
    }

    function release(Task $task): bool
    {
        return true;
    }

    function remove(Task $task): bool
    {
        if ($this->task === $task) {
            $this->task = null;
        }
        return true;
    }

    function fail(Task $task, \Throwable $throwable)
    {
        if ($this->task === $task) {
            $this->task = null;
        }
    }
}