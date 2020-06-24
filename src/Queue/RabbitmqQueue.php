<?php

namespace MadeSimple\TaskWorker\Queue;

use MadeSimple\TaskWorker\Exception\QueueNameRequiredException;
use MadeSimple\TaskWorker\Queue;
use MadeSimple\TaskWorker\Task;
use PhpAmqpLib\Connection\AbstractConnection as AmqpConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerAwareTrait;

class RabbitmqQueue implements Queue
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
     * @var AmqpConnection
     */
    protected $connection;

    /**
     * @var \PhpAmqpLib\Channel\AMQPChannel
     */
    protected $channel;

    /**
     * @var \PhpAmqpLib\Message\AMQPMessage
     */
    protected $message;

    /**
     * RabbitmqQueue constructor.
     *
     * @param string|array $names
     * @param AmqpConnection $connection
     */
    public function __construct($names, AmqpConnection $connection)
    {
        $this->names = (array) $names;
        $this->connection = $connection;

        if (empty($this->names)) {
            throw new QueueNameRequiredException(static::class . ' requires at least one queue name');
        }

        $this->declareQueues();
    }

    public function __destruct()
    {
        if ($this->channel !== null) {
            $this->channel->close();
        }
        if ($this->connection !== null) {
            try {
                $this->connection->close();
            }
            catch (\Exception $e) {
            }
        }
    }

    function declareQueues()
    {
        $this->channel = $this->connection->channel();
        // Limit to 1 prefetch message on this channel (across all queues)
        $this->channel->basic_qos(null, 1, true);
        foreach ($this->names as $name) {
            $this->channel->queue_declare($name, false, true, false, false);
        }
    }

    function add(Task $task): bool
    {
        // If there is a delay create a delay queue
        if ($task->delay() > 0) {
            $queue = 'delayed_' . $task->delay() . '_' . $task->queue();
            $this->channel->queue_declare(
                $queue,
                false,
                true,
                false,
                false,
                false,
                [
                    'x-message-ttl' => ['I', $task->delay() * 1000],
                    'x-dead-letter-exchange' => ['S', ''],
                    'x-dead-letter-routing-key' => ['S', $task->queue()],
                ]
            );
        }

        // Publish the message
        $serialized = $task->serialize();
        $this->publish($serialized, $queue ?? $task->queue());
        $this->logger->debug('Added task: ' . $serialized);

        return true;
    }

    function reserve(array &$register)
    {
        for ($i=0; $i<count($this->names); $i++) {
            $name = $this->names[$this->key];
            $this->key = ($this->key + 1) % count($this->names);
            $message = $this->channel->basic_get($name, false);

            if ($message !== null) {
                $this->message = $message;
                $this->logger->debug('Reserved task', [
                    'body' => $this->message->body
                ]);
                return Task::deserialize($register, $this->message->body);
            }
        }

        return null;
    }

    function release(Task $task): bool
    {
        $this->channel->basic_nack($this->message->delivery_info['delivery_tag'], false, false);
        $this->publish($task->serialize(), $task->queue());
        return true;
    }

    function remove(Task $task): bool
    {
        $this->channel->basic_ack($this->message->delivery_info['delivery_tag']);
        return true;
    }

    function fail(Task $task, \Throwable $throwable)
    {
        $this->channel->basic_nack($this->message->delivery_info['delivery_tag'], false, false);
    }

    /**
     * @param string $body
     * @param string $queue
     */
    protected function publish(string $body, string $queue)
    {
        $message = new AMQPMessage($body, [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]);

        $this->channel->basic_publish($message, '', $queue);
    }
}