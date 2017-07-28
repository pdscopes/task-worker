<?php

namespace MadeSimple\TaskWorker;

/**
 * Class Task
 *
 * @package MadeSimple\TaskWorker
 * @author  Peter Scopes
 */
interface Task
{
    /**
     * @return bool
     */
    function perform() : bool;

}