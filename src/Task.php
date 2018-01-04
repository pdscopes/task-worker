<?php

namespace MadeSimple\TaskWorker;

use Psr\Log\LoggerAwareTrait;

abstract class Task implements \JsonSerializable, \ArrayAccess
{
    use LoggerAwareTrait;

    /**
     * @var string
     */
    protected $identifier;

    /**
     * @var string
     */
    protected $register;

    /**
     * @var string
     */
    protected $queue;

    /**
     * @var int
     */
    protected $attempts = 0;

    /**
     * @var int Number of seconds
     */
    protected $delay = 0;

    /**
     * @var array
     */
    protected $data = [];

    /**
     * @param array  $register
     * @param string $serialized
     * @return \MadeSimple\TaskWorker\Task
     */
    public static function deserialize(array &$register, string $serialized) : Task
    {
        $json = json_decode($serialized, true);

        // Check the register
        $task = $register[$json['register']] ?? null;

        // Attempt to autoload, on success add to register
        if ($task === null && class_exists($json['register'])) {
            $register[$json['register']] = ($task = new $json['register']);
        }

        // Fail if the task hasn't been registered
        if ($task === null) {
            throw new \RuntimeException('Task not registered');
        }

        // Clone and populate the task
        /** @var Task $task */
        $task = (clone $register[$json['register']]);
        $task->identifier = $json['identifier'];
        $task->register = $json['register'];
        $task->queue = $json['queue'];
        $task->attempts = $json['attempts'];
        $task->data = $json['data'];

        return $task;
    }

    public function __clone()
    {
        // Identifiers must be unique
        $this->identifier = null;
        $this->attempts = 0;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return [
            'identifier' => $this->identifier(),
            'register'   => $this->register(),
            'queue'      => $this->queue,
            'attempts'   => $this->attempts,
            'data'       => $this->data,
        ];
    }

    public function identifier() : string
    {
        if ($this->identifier === null) {
            $this->identifier = uniqid(getmypid() . '-');
        }
        return $this->identifier;
    }

    /**
     * @return string
     */
    public function register() : string
    {
        return (string) ($this->register ?? static::class);
    }

    /**
     * @param string $name
     *
     * @return Task
     */
    public function onQueue(string $name) : Task
    {
        $this->queue = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function queue()
    {
        return $this->queue;
    }

    /**
     * @param int $attempts
     *
     * @return Task
     */
    public function setAttempts(int $attempts)
    {
        $this->attempts = $attempts;

        return $this;
    }

    /**
     * Increments the number of attempts this task has had.
     *
     * @return Task
     */
    public function incrementAttempts()
    {
        $this->attempts++;

        return $this;
    }

    /**
     * @return int
     */
    public function attempts()
    {
        return $this->attempts;
    }

    /**
     * @param int $seconds
     *
     * @return Task
     */
    public function withDelay(int $seconds) : Task
    {
        $this->delay = $seconds;

        return $this;
    }

    /**
     * @return int
     */
    public function delay()
    {
        return $this->delay;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return static
     */
    public function set($key, $value)
    {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }
    public function offsetGet($offset)
    {
        return $this->data[$offset] ?? null;
    }
    public function offsetSet($offset, $value)
    {
        if ($offset === null) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }
    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }

    /**
     * Logic to be performed when a worker receives the task.
     *
     * @return void
     */
    public abstract function perform();

    /**
     * Called when the task is failed.
     *
     * @param \Throwable $throwable
     */
    public function fail(\Throwable $throwable)
    {
    }

    /**
     * Serialize this task into a string.
     *
     * @return string
     */
    public function serialize() : string
    {
        return json_encode($this);
    }
}