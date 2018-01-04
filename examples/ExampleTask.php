<?php

namespace MadeSimple\TaskWorker\Example;

use MadeSimple\TaskWorker\Task;

class ExampleTask extends Task
{
//    protected $register = 'example';

    /**
     * Report constructor.
     *
     * @param mixed $data
     */
    public function __construct($data = [])
    {
        $this->data = $data;
    }

    public  function perform()
    {
        if ($this['message'] === 'Invalid Message') {
            throw new \RuntimeException('Invalid Message');
        }
        $this->logger->info('Performing Example Task', $this->data);
    }
}