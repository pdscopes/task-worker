<?php

namespace MadeSimple\TaskWorker;

use MadeSimple\TaskWorker\Exception\TaskRetrievalFailureException;

/**
 * Class Manager
 *
 * @package MadeSimple\TaskWorker
 * @author  Peter Scopes
 */
interface Manager
{
    /**
     * @param Task $task
     *
     * @return bool
     */
    function store(Task $task) : bool;

    /**
     * @return Task
     *
     * @throws TaskRetrievalFailureException
     */
    function retrieve() : Task;

    /**
     * @param Task $task
     *
     * @return bool
     */
    function remove(Task $task) : bool;
}