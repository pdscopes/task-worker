<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/helpers.php';

$dotenv = new \Dotenv\Dotenv(__DIR__);
$dotenv->load();

use MadeSimple\TaskWorker\Example\ExampleTask;


// Create the task
$options = getopt('m:q:d:c:', ['message:', 'queue:', 'delay:', 'count:']);

$message = array_merge((array) ($options['m'] ?? []), (array) ($options['message'] ?? []));
if (count($message) === 0) {
    $message[] = 'Hello World!';
}
if (count($message) === 1) {
    $message = reset($message);
}

$task = new ExampleTask(['message' => $message]);
if (!empty($options['q']) || !empty($options['queue'])) {
    $task->onQueue($options['q'] ?? $options['queue'] ?? null);
}
if (!empty($options['d']) || !empty($options['delay'])) {
    $task->withDelay($options['d'] ?? $options['delay'] ?? null);
}

// Created the queue
$queue = require __DIR__ . '/factory-queue.php';
$queue->setLogger(new \Monolog\Logger('task'));

$count = max(1, $options['c'] ?? ($options['count'] ?? 1));
for ($i=0; $i < $count; $i++) {
    $queue->add(clone $task);
}