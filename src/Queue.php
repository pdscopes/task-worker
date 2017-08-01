<?php

namespace MadeSimple\TaskWorker;

use MadeSimple\TaskWorker\Exception\QueueConnectionException;

/**
 * Class Queue
 *
 * @package MadeSimple\TaskWorker
 * @author  Peter Scopes
 */
interface Queue
{
    /**
     * Add task to the end of the queue.
     *
     * @param Task $task
     *
     * @return bool
     *
     * @throws QueueConnectionException
     */
    function add(Task $task) : bool;

    /**
     * Reserves the next eligible task in the queue.
     *
     * Should increment the number of attempts on this reserved task.
     *
     * @return Task
     *
     * @throws QueueConnectionException
     */
    function reserve();

    /**
     * Releases the given task back in the queue.
     *
     * @param Task $task
     *
     * @return bool
     */
    function release(Task $task) : bool;

    /**
     * Remove the task from the queue.
     *
     * @param Task $task
     *
     * @return bool
     *
     * @throws QueueConnectionException
     */
    function remove(Task $task) : bool;

    /**
     * @param Task       $task
     * @param \Throwable $throwable
     *
     * @return void
     */
    function fail(Task $task, \Throwable $throwable);
}