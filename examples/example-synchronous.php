<?php

require __DIR__ . '/../vendor/autoload.php';

$dotenv = new \Dotenv\Dotenv(__DIR__);
$dotenv->load();

use MadeSimple\TaskWorker\Synchronous\SynchronousQueue;
use MadeSimple\TaskWorker\Example\ExampleTask;
use MadeSimple\TaskWorker\Worker;

$logger = new \Monolog\Logger('example');

// Created the queue
$synchronousQueue = new SynchronousQueue($logger);

// Add a task
$synchronousQueue->add((new ExampleTask(['alpha' => time()]))->onQueue('alpha'));
$synchronousQueue->add((new ExampleTask(['beta' => time()]))->onQueue('beta')->withDelay(1));
$synchronousQueue->add((new ExampleTask(['gamma' => time()]))->onQueue('gamma')->withDelay(3));
$synchronousQueue->add((new ExampleTask(['omega' => time() + 5]))->onQueue('omega')->withDelay(5));

// Create the worker and run
$worker = new Worker($logger);
$worker
    ->setQueue($synchronousQueue)
    ->setOption(Worker::OPT_SLEEP, getenv('TASK_WORKER_SLEEP'))
    ->setOption(Worker::OPT_ALIVE, getenv('TASK_WORKER_ALIVE'))
    ->run();