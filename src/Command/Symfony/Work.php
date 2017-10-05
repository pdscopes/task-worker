<?php

namespace MadeSimple\TaskWorker\Command\Symfony;

use Dotenv\Dotenv;
use MadeSimple\TaskWorker\HasCacheTrait;
use MadeSimple\TaskWorker\Worker;
use MadeSimple\TaskWorker\Queue;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class work
 *
 * @package MadeSimple\TaskWorker\Command\Symfony
 * @author  Peter Scopes
 */
class Work extends Command
{
    use HasCacheTrait;

    /**
     * Work constructor.
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
            ->setName('task-worker:work')
            ->setDescription('Patiently wait for a task(s) to perform')
            ->setHelp('This command allows you to start a task worker')
            ->addOption('dotenv', 'e', InputOption::VALUE_REQUIRED, 'Load configuration from environment file', '.env')
            ->addOption(Worker::OPT_SLEEP, 's', InputOption::VALUE_REQUIRED, 'How long, in seconds, to sleep if not tasks are available', Worker::defaultOptions()[Worker::OPT_SLEEP])
            ->addOption(Worker::OPT_ATTEMPTS, 'a', InputOption::VALUE_REQUIRED, 'How long many attempts a task is allowed before being failed (zero is unlimited)', Worker::defaultOptions()[Worker::OPT_ATTEMPTS])
            ->addOption(Worker::OPT_ALIVE, 'l', InputOption::VALUE_REQUIRED, 'How long, in seconds, the worker will stay alive for (zero is unlimited)', Worker::defaultOptions()[Worker::OPT_ALIVE])
            ->addOption(Worker::OPT_REST, 'r', InputOption::VALUE_REQUIRED, 'How long, in milliseconds, to rest between tasks', Worker::defaultOptions()[Worker::OPT_REST])
            ->addOption(Worker::OPT_MAX_TASKS, 'm', InputOption::VALUE_REQUIRED, 'How many tasks should be performed (zero is unlimited)', Worker::defaultOptions()[Worker::OPT_MAX_TASKS])
            ->addOption(Worker::OPT_UNTIL_EMPTY, 'u', InputOption::VALUE_NONE, 'If the worker should stop when the queue is empty');
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

        $dotenv = new Dotenv(getcwd(), $input->getOption('dotenv'));
        $dotenv->load();

        $logger = new ConsoleLogger($output);

        $worker = new Worker($this->cache, $logger);
        switch (getenv('TASK_WORKER_QUEUE')) {
            case 'database':
                $pdo = new \PDO('mysql:host=localhost;dbname='  .getenv('QUEUE_DB_DATABASE'), getenv('QUEUE_DB_USERNAME'), getenv('QUEUE_DB_PASSWORD'));
                $queue = new Queue\DatabaseQueue($pdo);
                $queue->setLogger($logger);
                break;

            case 'synchronous':
            default:
                $queue = new Queue\SynchronousQueue();
                break;
        }
        $worker->setQueue($queue);
        foreach ($input->getOptions() as $name => $value) {
            $worker->setOption($name, $value);
        }

        return $worker->run();
    }
}