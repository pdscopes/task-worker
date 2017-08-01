<?php

namespace MadeSimple\TaskWorker\Example;

use MadeSimple\TaskWorker\Task;

class ExampleTask extends Task
{
    /**
     * @var mixed
     */
    public $message;

    /**
     * Report constructor.
     *
     * @param mixed $data
     */
    public function __construct($data = [])
    {
        $this->message = $data;
    }

    public  function perform()
    {
        $this->logger->info('Performing Example Task', ['message' => $this->message]);
    }
}