<?php

require __DIR__ . '/../vendor/autoload.php';

$dotenv = new \Dotenv\Dotenv(__DIR__);
$dotenv->load();

use MadeSimple\TaskWorker\Queue\RedisQueue;
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
$queue = (new RedisQueue(['queue1', 'queue2']))
    ->setOptions([
        RedisQueue::OPT_SCHEME => getenv('QUEUE_REDIS_SCHEME'),
        RedisQueue::OPT_HOST => getenv('QUEUE_REDIS_HOST'),
        RedisQueue::OPT_PORT => getenv('QUEUE_REDIS_PORT'),
    ])
    ->add($task);