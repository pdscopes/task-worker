<?php

namespace MadeSimple\TaskWorker\Test;

use MadeSimple\TaskWorker\Task;

class TestTask extends Task
{
    /**
     * Logic to be performed when a worker receives the task.
     *
     * @return void
     */
    public function perform()
    {
        // Do nothing
    }
}