<?php

namespace MadeSimple\TaskWorker\Command\Symfony;

use MadeSimple\TaskWorker\HasCacheTrait;
use MadeSimple\TaskWorker\Worker;
use MadeSimple\TaskWorker\Queue;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Predis\Client;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class Work extends Command
{
    use HasCacheTrait;

    protected $register;

    /**
     * Work constructor.
     *
     * @param CacheInterface $cache
     * @param array|\MadeSimple\TaskWorker\Task[] $register
     */
    public function __construct(CacheInterface $cache, array $register = [])
    {
        parent::__construct();
        $this->setCache($cache);
        $this->register = $register;
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
            ->addOption(Worker::OPT_SLEEP, 's', InputOption::VALUE_REQUIRED, 'How long, in seconds, to sleep if not tasks are available', Worker::defaultOptions()[Worker::OPT_SLEEP])
            ->addOption(Worker::OPT_ATTEMPTS, 'a', InputOption::VALUE_REQUIRED, 'How long many attempts a task is allowed before being failed (zero is unlimited)', Worker::defaultOptions()[Worker::OPT_ATTEMPTS])
            ->addOption(Worker::OPT_ALIVE, 'l', InputOption::VALUE_REQUIRED, 'How long, in seconds, the worker will stay alive for (zero is unlimited)', Worker::defaultOptions()[Worker::OPT_ALIVE])
            ->addOption(Worker::OPT_REST, 'r', InputOption::VALUE_REQUIRED, 'How long, in milliseconds, to rest between tasks', Worker::defaultOptions()[Worker::OPT_REST])
            ->addOption(Worker::OPT_MAX_TASKS, 'm', InputOption::VALUE_REQUIRED, 'How many tasks should be performed (zero is unlimited)', Worker::defaultOptions()[Worker::OPT_MAX_TASKS])
            ->addOption(Worker::OPT_UNTIL_EMPTY, 'u', InputOption::VALUE_NONE, 'If the worker should stop when the queue is empty')
            ->addArgument('queues', InputArgument::IS_ARRAY, 'Queues to listen on', []);
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

        $worker = new Worker($this->cache, $logger);
        switch (getenv('TASK_WORKER_QUEUE')) {
            case 'mysql':
                $pdo = new \PDO(
                    'mysql:host=localhost;dbname='  .getenv('QUEUE_MYSQL_DATABASE'),
                    getenv('QUEUE_MYSQL_USERNAME'), getenv('QUEUE_MYSQL_PASSWORD')
                );
                $queue = new Queue\MysqlQueue($input->getArgument('queues'), $pdo);
                $queue->setOption(Queue\MysqlQueue::OPT_TABLE_NAME, getenv('QUEUE_MYSQL_TABLE_NAME'));
                break;

            case 'rabbitmq':
                $connection = new AMQPStreamConnection(
                    getenv('QUEUE_RABBITMQ_HOST'),
                    getenv('QUEUE_RABBITMQ_PORT'),
                    getenv('QUEUE_RABBITMQ_USER'),
                    getenv('QUEUE_RABBITMQ_PASS'),
                    getenv('QUEUE_RABBITMQ_VHOST')
                );
                $queue = new Queue\RabbitmqQueue($input->getArgument('queues'), $connection);
                break;

            case 'redis':
                $client = new Client([
                    'scheme' => getenv('QUEUE_REDIS_SCHEME'),
                    'host'   => getenv('QUEUE_REDIS_HOST'),
                    'port'   => getenv('QUEUE_REDIS_PORT'),
                ]);
                $queue = new Queue\RedisQueue($input->getArgument('queues'), $client);
                break;

            case 'synchronous':
            default:
                $queue = new Queue\SynchronousQueue();
                break;
        }
        $queue->setLogger($logger);
        $worker->setQueue($queue);
        $worker->setRegister($this->register);
        $worker->setLogger($logger);
        foreach ($input->getOptions() as $name => $value) {
            $worker->setOption($name, $value);
        }

        return $worker->run();
    }
}