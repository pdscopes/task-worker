<?php

namespace MadeSimple\TaskWorker\Queue;

use MadeSimple\TaskWorker\HasOptionsTrait;
use MadeSimple\TaskWorker\Queue;
use MadeSimple\TaskWorker\Task;
use Predis\Client;
use Psr\Log\LoggerAwareTrait;

class RedisQueue implements Queue
{
    use LoggerAwareTrait, HasOptionsTrait;

    const OPT_SCHEME = 'scheme';
    const OPT_HOST = 'host';
    const OPT_PORT = 'port';
    const OPT_PATH = 'path';

    static function defaultOptions()
    {
        return [
            self::OPT_SCHEME => 'tcp',
            self::OPT_HOST => '127.0.0.1',
            self::OPT_PORT => 6379,
            self::OPT_PATH => '/tmp/redis.sock',
        ];
    }

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
     * @param array $options
     */
    public function __construct($names, array $options = null)
    {
        $this->names = (array) $names;
        $this->setOptions($options ?? self::defaultOptions());
    }

    public function __destruct()
    {
        if ($this->client) {
            $this->client->disconnect();
        }
    }

    /**
     * @return static
     */
    function connect()
    {
        // Only connect once
        if ($this->client) {
            return $this;
        }

        $this->client = new Client($this->options);

        return $this;
    }

    function reserve()
    {
        $this->connect();

        for ($i=0; $i<count($this->names); $i++) {
            $name = $this->names[$this->key];
            $this->key = ($this->key + 1) % count($this->names);
            $message = $this->client->rpoplpush($name, $this->processing($name));

            if ($message !== null) {
                $this->logger->debug('Received on "' . $name . '": ' . $message);
                return $this->unserialize($message);
            }
        }

        return null;
    }

    function release(Task $task): bool
    {
        $this->connect();

        $this->client->lpush($task->queue(), $this->serialize($task));
        return $this->client->lrem($this->processing($task->queue()), 1, $task) > 0;
    }

    function add(Task $task): bool
    {
        $this->connect();

        // @TODO implement task delay in redis queues
        return $this->client->llen($task->queue()) < $this->client->lpush($task->queue(), $this->serialize($task));

    }

    function remove(Task $task): bool
    {
        $this->connect();

        return $this->client->lrem($this->processing($task->queue()), 1, $this->serialize($task)) > 0;
    }

    function fail(Task $task, \Throwable $throwable)
    {
        $this->connect();

        $this->client->lrem($this->processing($task->queue()), 1, $this->serialize($task));
    }

    protected function processing($name)
    {
        return $name . '-processing';
    }

    protected function serialize(Task $task)
    {
        return serialize($task);
    }

    protected function unserialize($item)
    {
        return unserialize($item);
    }
}