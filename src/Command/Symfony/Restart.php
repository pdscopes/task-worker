<?php

namespace MadeSimple\TaskWorker\Command\Symfony;

use MadeSimple\TaskWorker\HasCacheTrait;
use MadeSimple\TaskWorker\Worker;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Restart
 *
 * @package MadeSimple\TaskWorker\Command\Symfony
 * @author  Peter Scopes
 */
class Restart extends Command
{
    use HasCacheTrait;

    /**
     * Restart constructor.
     *
     * @param CacheInterface $cache
     */
    public function __construct(CacheInterface $cache)
    {
        parent::__construct();
        $this->setCache($cache);
    }

    /**
     * Configures the current command.
     */
    protected function configure()
    {
        $this
            ->setName('simple:task-worker:restart')
            ->setDescription('Inform all task worker to stop after current task is complete')
            ->setHelp('This command allows you to safely stop all task workers');
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
        $logger = new ConsoleLogger($output);
        $logger->info('Broadcasting restart signal');

        return Worker::restart($this->cache) ? 0 : 1;
    }
}