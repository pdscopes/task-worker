<?php

namespace MadeSimple\TaskWorker\Queue;

use MadeSimple\TaskWorker\Queue;
use MadeSimple\TaskWorker\Task;
use Predis\Client;
use Psr\Log\LoggerAwareTrait;

class RedisQueue implements Queue
{
    use LoggerAwareTrait;

    /**
     * @var array|string[]
     */
    protected $names;

    /**
     * @var int Position in the queues for reservation
     */
    protected $key = 0;

    /**
     * @var \Predis\Client
     */
    protected $client;

    /**
     * RedisQueue constructor.
     *
     * @param string|array $names
     * @param Client $client
     */
    public function __construct($names, Client $client)
    {
        $this->names = (array) $names;
        $this->client = $client;
    }

    public function __destruct()
    {
        if ($this->client) {
            $this->client->disconnect();
        }
    }

    function add(Task $task): bool
    {
        $serialized = $task->serialize();

        if ($task->delay() > 0) {
            $success = $this->client->zadd($this->delayed($task->queue()), time() + $task->delay(), $task->serialize()) > 0;
        } else {
            $success = $this->client->llen($task->queue()) < $this->client->lpush($task->queue(), [$serialized]);
        }

        if (!$success) {
            return false;
        }

        $this->logger->debug('Added task: ' . $serialized);
        return true;
    }

    function reserve(array &$register)
    {
        for ($i=0; $i<count($this->names); $i++) {
            $name = $this->names[$this->key];
            $this->key = ($this->key + 1) % count($this->names);

            // Move all delayed messages now available into the queue
            $time = time();
            $delayed = $this->client->zrangebyscore($this->delayed($name), 0, $time);
            if (!empty($delayed)) {
                $this->client->lpush($name, $delayed);
                $this->client->zremrangebyscore($this->delayed($name), 0, $time);
            }

            // Get the next message
            $message = $this->client->rpoplpush($name, $this->processing($name));

            if ($message !== null) {
                $this->logger->debug('Reserved task', [$message]);
                return Task::deserialize($register, $message);
            }
        }

        return null;
    }

    function release(Task $task): bool
    {
        $this->client->lpush($task->queue(), [$task->serialize()]);
        return $this->client->lrem($this->processing($task->queue()), 1, $task->serialize()) > 0;
    }

    function remove(Task $task): bool
    {
        return $this->client->lrem($this->processing($task->queue()), 1, $task->serialize()) > 0;
    }

    function fail(Task $task, \Throwable $throwable)
    {
        $this->client->lrem($this->processing($task->queue()), 1, $task->serialize());
    }

    protected function processing($name)
    {
        return $name . '-processing';
    }

    protected function delayed($name)
    {
        return $name . '-delayed';
    }
}