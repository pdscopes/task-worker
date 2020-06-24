<?php

namespace MadeSimple\TaskWorker\Test\Unit\Queue;

use MadeSimple\TaskWorker\Queue\RabbitmqQueue;
use MadeSimple\TaskWorker\Test\TestTask;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class RabbitmqQueueTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|\PhpAmqpLib\Connection\AbstractConnection
     */
    protected $mockConnection;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|\PhpAmqpLib\Channel\AMQPChannel
     */
    protected $mockChannel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockConnection = $this->getMockBuilder(AbstractConnection::class)->disableOriginalConstructor()->getMock();
        $this->mockChannel = $this->getMockBuilder(AMQPChannel::class)->disableOriginalConstructor()->getMock();

        $this->mockConnection
            ->expects($this->once())
            ->method('channel')
            ->with()
            ->willReturn($this->mockChannel);
        $this->mockChannel
            ->expects($this->once())
            ->method('basic_qos')
            ->with(null, 1, true);
        $this->mockChannel
            ->expects($this->at(1))
            ->method('queue_declare')
            ->with('queue_name', false, true, false, false);
    }


    public function testAdd()
    {
        $task = new TestTask();
        $task->onQueue('queue_name');

        $this->mockChannel
            ->expects($this->once())
            ->method('basic_publish')
            ->with($this->isInstanceOf(AMQPMessage::class), '', 'queue_name');

        $queue = new RabbitmqQueue('queue_name', $this->mockConnection);
        $queue->setLogger(new NullLogger());

        $this->assertTrue($queue->add($task));
    }

    public function testAddWithDelay()
    {
        $task = new TestTask();
        $task->onQueue('queue_name')->withDelay(5);

        $this->mockChannel
            ->expects($this->at(2))
            ->method('queue_declare')
            ->with('delayed_5_queue_name', false, true, false, false, false, [
                'x-message-ttl' => ['I', $task->delay() * 1000],
                'x-dead-letter-exchange' => ['S', ''],
                'x-dead-letter-routing-key' => ['S', $task->queue()],
            ]);
        $this->mockChannel
            ->expects($this->once())
            ->method('basic_publish')
            ->with($this->isInstanceOf(AMQPMessage::class), '', 'delayed_5_queue_name');

        $queue = new RabbitmqQueue('queue_name', $this->mockConnection);
        $queue->setLogger(new NullLogger());

        $this->assertTrue($queue->add($task));
    }

    public function testReserve()
    {
        $task = new TestTask();
        $message = new AMQPMessage($task->serialize());
        $register = [];

        $this->mockChannel
            ->expects($this->once())
            ->method('basic_get')
            ->with('queue_name', false)
            ->willReturn($message);



        $queue = new RabbitmqQueue('queue_name', $this->mockConnection);
        $queue->setLogger(new NullLogger());

        $this->assertEquals($task->identifier(), $queue->reserve($register)->identifier());
        $this->assertEquals([TestTask::class => new TestTask()], $register);
    }

    public function testReserveEmptyQueue()
    {
        $register = [];

        $this->mockChannel
            ->expects($this->once())
            ->method('basic_get')
            ->with('queue_name', false)
            ->willReturn(null);



        $queue = new RabbitmqQueue('queue_name', $this->mockConnection);
        $queue->setLogger(new NullLogger());

        $this->assertNull($queue->reserve($register));
        $this->assertEquals([], $register);
    }

    public function testRelease()
    {
        $task = new TestTask();
        $task->onQueue('queue_name');
        $message = new AMQPMessage($task->serialize());
        $message->delivery_info['delivery_tag'] = '123';

        $this->mockChannel
            ->expects($this->once())
            ->method('basic_nack')
            ->with('123', false, false);
        $this->mockChannel
            ->expects($this->once())
            ->method('basic_publish')
            ->with($this->isInstanceOf(AMQPMessage::class), '', 'queue_name');

        $queue = new RabbitmqQueue('queue_name', $this->mockConnection);
        $queue->setLogger(new NullLogger());

        $property = new \ReflectionProperty(RabbitmqQueue::class, 'message');
        $property->setAccessible(true);
        $property->setValue($queue, $message);

        $this->assertTrue($queue->release($task));
    }

    public function testRemove()
    {
        $task = new TestTask();
        $task->onQueue('queue_name');
        $message = new AMQPMessage($task->serialize());
        $message->delivery_info['delivery_tag'] = '123';

        $this->mockChannel
            ->expects($this->once())
            ->method('basic_ack')
            ->with('123');

        $queue = new RabbitmqQueue('queue_name', $this->mockConnection);
        $queue->setLogger(new NullLogger());

        $property = new \ReflectionProperty(RabbitmqQueue::class, 'message');
        $property->setAccessible(true);
        $property->setValue($queue, $message);

        $this->assertTrue($queue->remove($task));
    }

    public function testFail()
    {
        $task = new TestTask();
        $task->onQueue('queue_name');
        $message = new AMQPMessage($task->serialize());
        $message->delivery_info['delivery_tag'] = '123';

        $this->mockChannel
            ->expects($this->once())
            ->method('basic_nack')
            ->with('123', false, false);

        $queue = new RabbitmqQueue('queue_name', $this->mockConnection);
        $queue->setLogger(new NullLogger());

        $property = new \ReflectionProperty(RabbitmqQueue::class, 'message');
        $property->setAccessible(true);
        $property->setValue($queue, $message);

        $queue->fail($task, new \RuntimeException());
    }
}