<?php
//define('AMQP_DEBUG', true);

require __DIR__ . '/../vendor/autoload.php';

$dotenv = new \Dotenv\Dotenv(__DIR__);
$dotenv->load();

use Cache\Adapter\Filesystem\FilesystemCachePool;
use League\Flysystem;
use MadeSimple\TaskWorker\Queue\RabbitmqQueue;
use MadeSimple\TaskWorker\Worker;

$logger    = new \Monolog\Logger('example');
$cachePool = new FilesystemCachePool(new Flysystem\Filesystem(new Flysystem\Adapter\Local(sys_get_temp_dir())));

$options = getopt('q:', ['queue:']);

// Created the queue
$rabbitmqQueue = (new RabbitmqQueue(['task_queue1', 'task_queue2']))
    ->setOptions([
        RabbitmqQueue::OPT_HOST => getenv('QUEUE_RABBITMQ_HOST'),
        RabbitmqQueue::OPT_PORT => getenv('QUEUE_RABBITMQ_PORT'),
        RabbitmqQueue::OPT_USER => getenv('QUEUE_RABBITMQ_USER'),
        RabbitmqQueue::OPT_PASS => getenv('QUEUE_RABBITMQ_PASS'),
        RabbitmqQueue::OPT_VIRTUAL_HOST => getenv('QUEUE_RABBITMQ_VHOST'),
    ]);
$rabbitmqQueue
    ->setLogger($logger);

// Create the worker and run
$worker = new Worker($cachePool, $logger);
$worker
    ->setQueue($rabbitmqQueue)
    ->setOption(Worker::OPT_SLEEP, getenv('TASK_WORKER_SLEEP'))
    ->setOption(Worker::OPT_ALIVE, getenv('TASK_WORKER_ALIVE'))
    ->run();