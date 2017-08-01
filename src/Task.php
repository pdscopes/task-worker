<?php

namespace MadeSimple\TaskWorker;

use Psr\Log\LoggerAwareTrait;

/**
 * Class Task
 *
 * @package MadeSimple\TaskWorker
 * @author  Peter Scopes
 */
abstract class Task implements \Serializable
{
    use LoggerAwareTrait;

    /**
     * @var int
     */
    protected $id;

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
     * @param int $id
     *
     * @return Task
     */
    public function setId(int $id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return int
     */
    public function id() : int
    {
        return (int) $this->id;
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
     */
    public function incrementAttempts()
    {
        $this->attempts++;
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

    public function serialize()
    {
        $properties = get_object_vars($this);
        unset($properties['logger']);
        unset($properties['attempts']);
        return serialize($properties);
    }

    public function unserialize($serialized)
    {
        foreach (unserialize($serialized) as $property => $value) {
            $this->{$property} = $value;
        }
    }
}