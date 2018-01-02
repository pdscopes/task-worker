<?php

namespace MadeSimple\TaskWorker\Queue;

use MadeSimple\TaskWorker\HasOptionsTrait;
use MadeSimple\TaskWorker\Queue;
use MadeSimple\TaskWorker\Task;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerAwareTrait;

class RabbitmqQueue implements Queue
{
    use LoggerAwareTrait, HasOptionsTrait;

    const OPT_HOST = 'host';
    const OPT_PORT = 'port';
    const OPT_USER = 'user';
    const OPT_PASS = 'pass';
    const OPT_VIRTUAL_HOST = 'vhost';

    public static function defaultOptions()
    {
        return [
            self::OPT_HOST => 'localhost',
            self::OPT_PORT => '5672',
            self::OPT_USER => 'guest',
            self::OPT_PASS => 'guest',
            self::OPT_VIRTUAL_HOST => '/',
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
     * @var \PhpAmqpLib\Connection\AMQPConnection
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
     * @param array $options
     */
    public function __construct($names, array $options = null)
    {
        $this->names = (array) $names;
        $this->setOptions($options ?? self::defaultOptions());
    }

    public function __destruct()
    {
        if ($this->channel !== null) {
            $this->channel->close();
        }
        if ($this->connection !== null) {
            $this->connection->close();
        }
    }

    /**
     * @return static
     */
    function connect()
    {
        // Only connect once
        if ($this->connection) {
            return $this;
        }

        $this->connection = new AMQPStreamConnection(
            $this->options[self::OPT_HOST],
            $this->options[self::OPT_PORT],
            $this->options[self::OPT_USER],
            $this->options[self::OPT_PASS],
            $this->options[self::OPT_VIRTUAL_HOST]
        );

        $this->channel = $this->connection->channel();
        // Limit to 1 prefetch message on this channel (across all queues)
        $this->channel->basic_qos(null, 1, true);
        foreach ($this->names as $name) {
            $this->channel->queue_declare($name, false, true, false, false);
        }

        return $this;
    }

    function reserve()
    {
        $this->connect();

        for ($i=0; $i<count($this->names); $i++) {
            $name = $this->names[$this->key];
            $this->key = ($this->key + 1) % count($this->names);
            $message = $this->channel->basic_get($name, false);

            if ($message !== null) {
                $this->logger->debug('Received on "' . $name . '": ' . $message->body, $message->delivery_info);
                $this->message = $message;
                return unserialize($this->message->body);
            }
        }

        return null;
    }

    function release(Task $task): bool
    {
        $this->connect();

        $this->channel->basic_nack($this->message->delivery_info['delivery_tag'], false, true);

        return true;
    }

    function add(Task $task): bool
    {
        $this->connect();

        $message = new AMQPMessage(serialize($task), [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]);

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
            $this->channel->basic_publish($message, '', $queue);
        } else {
            $this->channel->basic_publish($message, '', $task->queue());
        }

        return true;
    }

    function remove(Task $task): bool
    {
        $this->connect();

        $this->channel->basic_ack($this->message->delivery_info['delivery_tag']);
        return true;
    }

    function fail(Task $task, \Throwable $throwable)
    {
        $this->connect();

        $this->channel->basic_nack($this->message->delivery_info['delivery_tag'], false, false);
    }
}