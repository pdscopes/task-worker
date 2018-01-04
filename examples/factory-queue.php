<?php

use MadeSimple\TaskWorker\Queue;

$settings = require __DIR__ . '/settings.php';
$names = array_merge((array) ($options['q'] ?? []), (array) ($options['queue'] ?? []));

switch (env('TASK_WORKER_QUEUE', 'redis')) {
    case 'mysql':
        $pdo = new PDO($settings['mysql']['dsn'], $settings['mysql']['username'], $settings['mysql']['password']);
        $queue = new Queue\MysqlQueue($names, $pdo);
        break;

    case 'rabbitmq':
        $connection = new \PhpAmqpLib\Connection\AMQPStreamConnection(
            $settings['rabbitmq']['host'],
            $settings['rabbitmq']['port'],
            $settings['rabbitmq']['user'],
            $settings['rabbitmq']['pass'],
            $settings['rabbitmq']['vhost']
        );
        $queue = new Queue\RabbitmqQueue($names, $connection);
        break;

    case 'redis':
        $client = new \Predis\Client($settings['redis']);
        $queue = new Queue\RedisQueue($names, $client);
        break;

    case 'synchronous':
    default:
        $queue = new Queue\SynchronousQueue();
        break;
}

return $queue;