<?php

return [
    'mysql' => [
        'dsn'      => 'mysql:host=localhost;dbname=' . env('QUEUE_MYSQL_DATABASE', 'test'),
        'username' => env('QUEUE_MYSQL_USERNAME', 'root'),
        'password' => env('QUEUE_MYSQL_PASSWORD', ''),
    ],

    'rabbitmq' => [
        'host'  => env('QUEUE_RABBITMQ_HOST', 'localhost'),
        'port'  => env('QUEUE_RABBITMQ_PORT', '5672'),
        'user'  => env('QUEUE_RABBITMQ_USER', 'guest'),
        'pass'  => env('QUEUE_RABBITMQ_PASS', 'guest'),
        'vhost' => env('QUEUE_RABBITMQ_VHOST', '/'),
    ],

    'redis' => [
        'scheme' => env('QUEUE_REDIS_SCHEME', 'tcp'),
        'host'   => env('QUEUE_REDIS_HOST', '127.0.0.1'),
        'port'   => env('QUEUE_REDIS_PORT', 6379),
    ],
];