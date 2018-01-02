<?php
//define('AMQP_DEBUG', true);

require __DIR__ . '/../vendor/autoload.php';

$dotenv = new \Dotenv\Dotenv(__DIR__);
$dotenv->load();

use Cache\Adapter\Filesystem\FilesystemCachePool;
use League\Flysystem;
use MadeSimple\TaskWorker\Queue\RedisQueue;
use MadeSimple\TaskWorker\Worker;

$logger    = new \Monolog\Logger('example');
$cachePool = new FilesystemCachePool(new Flysystem\Filesystem(new Flysystem\Adapter\Local(sys_get_temp_dir())));

$options = getopt('q:', ['queue:']);

// Created the queue
$queue = (new RedisQueue(['queue1', 'queue2']))
    ->setOptions([
        RedisQueue::OPT_SCHEME => getenv('QUEUE_REDIS_SCHEME'),
        RedisQueue::OPT_HOST => getenv('QUEUE_REDIS_HOST'),
        RedisQueue::OPT_PORT => getenv('QUEUE_REDIS_PORT'),
    ]);
$queue
    ->setLogger($logger);

// Create the worker and run
$worker = new Worker($cachePool, $logger);
$worker
    ->setQueue($queue)
    ->setOption(Worker::OPT_SLEEP, getenv('TASK_WORKER_SLEEP'))
    ->setOption(Worker::OPT_ALIVE, getenv('TASK_WORKER_ALIVE'))
    ->run();