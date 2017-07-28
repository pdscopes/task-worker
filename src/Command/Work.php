<?php

namespace MadeSimple\TaskWorker\Command;

use MadeSimple\TaskWorker\Worker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class work
 *
 * @package MadeSimple\TaskWorker\Command
 * @author  Peter Scopes
 */
class Work extends Command
{
    /**
     * Configures the current command.
     */
    protected function configure()
    {
        $this
            ->setName('simple:task-worker:work')
            ->setDescription('Patiently wait for a task(s) to perform')
            ->setHelp('This command allows you to start a task worker');
    }

    /**
     * Executes the current command.
     *
     * This method is not abstract because you can use this class
     * as a concrete class. In this case, instead of defining the
     * execute() method, you set the code to execute by passing
     * a Closure to the setCode() method.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     *
     * @return null|int null or 0 if everything went fine, or an error code
     *
     * @see setCode()
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $worker = new Worker();
        return $worker->run();
    }
}