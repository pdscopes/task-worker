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
     */
    public function __construct($names)
    {
        $this->names = (array) $names;
        $this->setOptions(self::defaultOptions());
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
        $this->connection = new AMQPStreamConnection(
            $this->options[self::OPT_HOST],
            $this->options[self::OPT_PORT],
            $this->options[self::OPT_USER],
            $this->options[self::OPT_PASS],
            $this->options[self::OPT_VIRTUAL_HOST]
        );

        $tag = null;
        $this->channel = $this->connection->channel();
        foreach ($this->names as $name) {
            var_dump('declaring queue');
            $this->channel->queue_declare($name, false, true, false, false);
            var_dump('basic qos');
            $this->channel->basic_qos(null, 1, null);
            var_dump('basic consume');
            $tag = $this->channel->basic_consume($name, $tag ?? '', false, true, false, false);
        }

        var_dump('here');

        return $this;
    }

    function reserve()
    {
        return $this->message;
    }

    function release(Task $task): bool
    {
        $this->message->delivery_info['channel']->basic_nack($this->message->delivery_info['delivery_tag'], false, true);

        return true;
    }

    function add(Task $task): bool
    {
        $message = new AMQPMessage(serialize($task), [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]);
        $this->channel->basic_publish($message, '', $task->queue());

        return true;
    }

    function remove(Task $task): bool
    {
        $this->message->delivery_info['channel']->basic_ack($this->message->delivery_info['delivery_tag']);
        return true;
    }

    function fail(Task $task, \Throwable $throwable)
    {
        $this->message->delivery_info['channel']->basic_nack($this->message->delivery_info['delivery_tag'], false, false);
    }

    function receive($message)
    {
        $this->message = $message;
    }
}