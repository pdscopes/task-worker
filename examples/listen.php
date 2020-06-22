<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/helpers.php';

use Cache\Adapter\Filesystem\FilesystemCachePool;
use League\Flysystem;
use MadeSimple\TaskWorker\Worker;

$logger    = new \Monolog\Logger('listen');
$cachePool = new FilesystemCachePool(new Flysystem\Filesystem(new Flysystem\Adapter\Local(sys_get_temp_dir())));

$options = getopt('q:', ['queue:']);

// Created the queue
$queue = require __DIR__ . '/factory-queue.php';
$queue->setLogger($logger);

// Create the worker and run
$worker = new Worker($cachePool, $logger);
$worker
    ->setQueue($queue)
//    ->setRegister(['example' => new \MadeSimple\TaskWorker\Example\ExampleTask])
    ->setOption(Worker::OPT_SLEEP, env('TASK_WORKER_SLEEP'))
    ->setOption(Worker::OPT_ATTEMPTS, env('TASK_WORKER_ATTEMPTS'))
    ->setOption(Worker::OPT_ALIVE, env('TASK_WORKER_ALIVE'))
    ->setOption(Worker::OPT_REST, env('TASK_WORKER_REST'))
    ->setOption(Worker::OPT_MAX_TASKS, env('TASK_WORKER_MAX_TASKS'))
    ->setOption(Worker::OPT_UNTIL_EMPTY, env('TASK_WORKER_UNTIL_EMPTY'))
    ->run();