<?php

namespace MadeSimple\TaskWorker\Synchronous;

use MadeSimple\TaskWorker\Worker;

/**
 * Class SynchronousWorker
 *
 * @package MadeSimple\TaskWorker\Synchronous
 * @author  Peter Scopes
 */
class SynchronousWorker extends Worker
{
    protected function shouldContinueWorking(): bool
    {
        return false;
    }
}