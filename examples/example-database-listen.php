<?php

require __DIR__ . '/../vendor/autoload.php';

$dotenv = new \Dotenv\Dotenv(__DIR__);
$dotenv->load();

use MadeSimple\TaskWorker\Database\DatabaseQueue;
use MadeSimple\TaskWorker\Worker;

$logger = new \Monolog\Logger('example');

$options = getopt('q:', ['queue:']);

// Created the queue
$pdo = new PDO('mysql:host=localhost;dbname='  .getenv('QUEUE_DB_DATABASE'), getenv('QUEUE_DB_USERNAME'), getenv('QUEUE_DB_PASSWORD'));
$databaseQueue = new DatabaseQueue($pdo, array_merge((array) ($options['q'] ?? []), (array) ($options['queue'] ?? [])));

// Create the worker and run
$worker = new Worker($logger);
$worker
    ->setQueue($databaseQueue)
    ->setOption(Worker::OPT_SLEEP, getenv('TASK_WORKER_SLEEP'))
    ->setOption(Worker::OPT_ALIVE, getenv('TASK_WORKER_ALIVE'))
    ->run();