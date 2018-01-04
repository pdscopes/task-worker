<?php

namespace MadeSimple\TaskWorker\Queue;

use Aws\Exception\AwsException;
use Aws\Sqs\SqsClient;
use MadeSimple\TaskWorker\HasOptionsTrait;
use MadeSimple\TaskWorker\Queue;
use MadeSimple\TaskWorker\Task;
use Psr\Log\LoggerAwareTrait;

class SqsQueue implements Queue
{
    use LoggerAwareTrait, HasOptionsTrait;

    public static function defaultOptions()
    {
        return [];
    }

    /**
     * @var int Position in the queues for reservation
     */
    protected $key = 0;

    /**
     * array|string[]
     */
    protected $names;

    /**
     * @var array|string[]
     */
    protected $urls;

    /**
     * @var \Aws\Result
     */
    protected $message;

    /**
     * @var \Aws\Sqs\SqsClient
     */
    protected $client;

    /**
     * SqsQueue constructor.
     *
     * @param string|array $names
     * @param \Aws\Sqs\SqsClient $client
     */
    public function __construct($names, SqsClient $client)
    {
        $this->names = (array) $names;
        $this->client = $client;

        $this->declareQueues();
    }

    function declareQueues()
    {
        foreach ($this->names as $name) {
            // @TODO check the queue doesn't already exist before creating an AWS SQS Queue
            $result = $this->client->createQueue([
                'QueueName' => $name,
                'Attributes' => [],
            ]);

            $this->urls[$name] = $result->get('QueueUrl');
        }
    }

    function add(Task $task): bool
    {
        $serialized = $task->serialize();

        $params = [
            'DelaySeconds' => $task->delay(),
            'MessageAttributes' => [],
            'MessageBody' => $serialized,
            'QueueUrl' => $this->urls[$task->queue()],
        ];

        try {
            $this->client->sendMessage($params);
            $this->logger->debug('Added task: ' . $serialized);
            return true;
        } catch (AwsException $e) {
            $this->logger->critical($e->getMessage());
            return false;
        }
    }

    function reserve(array &$register)
    {
        for ($i=0; $i<count($this->names); $i++) {
            $name      = $this->names[$this->key];
            $url       = $this->urls[$name];
            $this->key = ($this->key + 1) % count($this->names);

            $result = $this->client->receiveMessage([
                'MaxNumberOfMessages' => 1,
                'MessageAttributeNames' => ['All'],
                'QueueUrl' => $url,
                'WaitTimeSeconds' => 0,
            ]);

            if ($result->hasKey('Messages')) {
                $this->message = $result->get('Messages')[0];
                $this->client->deleteMessage([
                    'QueueUrl' => $url,
                    'ReceiptHandle' => $this->message['ReceiptHandle'],
                ]);

                $this->logger->debug('Reserved task', $this->message);
                return Task::deserialize($register, $this->message['MessageBody']);
            }
        }

        return null;
    }

    function release(Task $task): bool
    {
        return $this->add($task);
    }

    function remove(Task $task): bool
    {
        // Do nothing
        return true;
    }

    function fail(Task $task, \Throwable $throwable)
    {
        // Do nothing
    }
}