<?php

require __DIR__ . '/../vendor/autoload.php';

$dotenv = new \Dotenv\Dotenv(__DIR__);
$dotenv->load();

use MadeSimple\TaskWorker\Queue\DatabaseQueue;
use MadeSimple\TaskWorker\Example\ExampleTask;


// Create the task
$options = getopt('m:q:d:', ['message:', 'queue:', 'delay:']);

$message = array_merge((array) ($options['m'] ?? []), (array) ($options['message'] ?? []));
if (count($message) === 0) {
    $message[] = 'Hello World!';
}
if (count($message) === 1) {
    $message = reset($message);
}

$task = new ExampleTask($message);
if (!empty($options['q']) || !empty($options['queue'])) {
    $task->onQueue($options['q'] ?? $options['queue'] ?? null);
}if (!empty($options['d']) || !empty($options['delay'])) {
    $task->withDelay($options['d'] ?? $options['delay'] ?? null);
}

// Created the queue
$pdo = new PDO('mysql:host=localhost;dbname='  .getenv('QUEUE_DB_DATABASE'), getenv('QUEUE_DB_USERNAME'), getenv('QUEUE_DB_PASSWORD'));
(new DatabaseQueue($pdo))->add($task);