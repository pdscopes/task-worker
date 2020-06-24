<?php

namespace MadeSimple\TaskWorker\Test\Unit\Queue;

use MadeSimple\TaskWorker\Queue\RedisQueue;
use MadeSimple\TaskWorker\Test\TestTask;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use Psr\Log\NullLogger;

class RedisQueueTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|\Predis\Client
     */
    protected $mockClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = $this->getMockBuilder(Client::class)->disableOriginalConstructor()->getMock();
    }


    public function testAdd()
    {
        $task = new TestTask();
        $task->onQueue('queue_name');

        $this->mockClient
            ->expects($this->at(0))
            ->method('__call')
            ->with('llen', [$task->queue()])
            ->willReturn(0);
        $this->mockClient
            ->expects($this->at(1))
            ->method('__call')
            ->with('lpush', [$task->queue(), [$task->serialize()]])
            ->willReturn(1);


        $queue = new RedisQueue('queue_name', $this->mockClient);
        $queue->setLogger(new NullLogger());

        $this->assertTrue($queue->add($task));
    }

    public function testAddWithDelay()
    {
        $task = new TestTask();
        $task->onQueue('queue_name')->withDelay(5);

        $this->mockClient
            ->expects($this->at(0))
            ->method('__call')
            ->with('zadd', [$task->queue() . '-delayed', [time() + $task->delay(), $task->serialize()]])
            ->willReturn(1);


        $queue = new RedisQueue('queue_name', $this->mockClient);
        $queue->setLogger(new NullLogger());

        $this->assertTrue($queue->add($task));
    }

    public function testReserve()
    {
        $task = new TestTask();
        $task->onQueue('queue_name');
        $register = [];

        $this->mockClient
            ->expects($this->at(0))
            ->method('__call')
            ->with('zrangebyscore', ['queue_name-delayed', 0, time()])
            ->willReturn([]);
        $this->mockClient
            ->expects($this->at(1))
            ->method('__call')
            ->with('rpoplpush', ['queue_name', 'queue_name-processing'])
            ->willReturn($task->serialize());


        $queue = new RedisQueue('queue_name', $this->mockClient);
        $queue->setLogger(new NullLogger());

        $this->assertEquals($task->identifier(), $queue->reserve($register)->identifier());
        $this->assertEquals([TestTask::class => new TestTask()], $register);
    }

    public function testReserveWithDelayedTask()
    {
        $task = new TestTask();
        $task->onQueue('queue_name');
        $register = [];

        $this->mockClient
            ->expects($this->at(0))
            ->method('__call')
            ->with('zrangebyscore', ['queue_name-delayed', 0, time()])
            ->willReturn([$task->serialize()]);
        $this->mockClient
            ->expects($this->at(1))
            ->method('__call')
            ->with('lpush', ['queue_name', [$task->serialize()]]);
        $this->mockClient
            ->expects($this->at(2))
            ->method('__call')
            ->with('zremrangebyscore', ['queue_name-delayed', 0, time()]);
        $this->mockClient
            ->expects($this->at(3))
            ->method('__call')
            ->with('rpoplpush', ['queue_name', 'queue_name-processing'])
            ->willReturn($task->serialize());


        $queue = new RedisQueue('queue_name', $this->mockClient);
        $queue->setLogger(new NullLogger());

        $this->assertEquals($task->identifier(), $queue->reserve($register)->identifier());
        $this->assertEquals([TestTask::class => new TestTask()], $register);
    }

    public function testReserveEmptyQueue()
    {
        $register = [];

        $this->mockClient
            ->expects($this->at(0))
            ->method('__call')
            ->with('zrangebyscore', ['queue_name-delayed', 0, time()])
            ->willReturn([]);
        $this->mockClient
            ->expects($this->at(1))
            ->method('__call')
            ->with('rpoplpush', ['queue_name', 'queue_name-processing'])
            ->willReturn(null);


        $queue = new RedisQueue('queue_name', $this->mockClient);
        $queue->setLogger(new NullLogger());

        $this->assertNull($queue->reserve($register));
        $this->assertEquals([], $register);
    }

    public function testRelease()
    {
        $task = new TestTask();
        $task->onQueue('queue_name');

        $this->mockClient
            ->expects($this->at(0))
            ->method('__call')
            ->with('lpush', [$task->queue(), [$task->serialize()]]);
        $this->mockClient
            ->expects($this->at(1))
            ->method('__call')
            ->with('lrem', [$task->queue() . '-processing', 1, $task->serialize()])
            ->willReturn(1);


        $queue = new RedisQueue('queue_name', $this->mockClient);
        $queue->setLogger(new NullLogger());

        $this->assertTrue($queue->release($task));
    }

    public function testRemove()
    {
        $task = new TestTask();
        $task->onQueue('queue_name');

        $this->mockClient
            ->expects($this->at(0))
            ->method('__call')
            ->with('lrem', [$task->queue() . '-processing', 1, $task->serialize()])
            ->willReturn(1);


        $queue = new RedisQueue('queue_name', $this->mockClient);
        $queue->setLogger(new NullLogger());

        $this->assertTrue($queue->remove($task));
    }

    public function testFail()
    {
        $task = new TestTask();
        $task->onQueue('queue_name');

        $this->mockClient
            ->expects($this->at(0))
            ->method('__call')
            ->with('lrem', [$task->queue() . '-processing', 1, $task->serialize()])
            ->willReturn(1);


        $queue = new RedisQueue('queue_name', $this->mockClient);
        $queue->setLogger(new NullLogger());

        $queue->fail($task, new \RuntimeException());
    }
}