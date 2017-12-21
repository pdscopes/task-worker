<?php

require __DIR__ . '/../vendor/autoload.php';

$dotenv = new \Dotenv\Dotenv(__DIR__);
$dotenv->load();

use MadeSimple\TaskWorker\Queue\RabbitmqQueue;
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
$queue = (new RabbitmqQueue(['task_queue1', 'task_queue2']))
    ->setOptions([
        RabbitmqQueue::OPT_PORT => '5672',
        RabbitmqQueue::OPT_USER => 'admin',
        RabbitmqQueue::OPT_PASS => 'password',
        RabbitmqQueue::OPT_VIRTUAL_HOST => '/',
    ])
    ->connect()
    ->add($task);