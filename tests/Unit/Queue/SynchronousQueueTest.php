<?php

namespace MadeSimple\TaskWorker\Test\Unit\Queue;

use MadeSimple\TaskWorker\Queue\SynchronousQueue;
use MadeSimple\TaskWorker\Test\TestTask;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class SynchronousQueueTest extends TestCase
{
    public function testAdd()
    {
        $task = new TestTask();

        $queue = new SynchronousQueue();
        $queue->setLogger(new NullLogger());
        $this->assertTrue($queue->add($task));
        $this->assertTrue($task['performed']);
    }

    public function testReserve()
    {
        $task = new TestTask();
        $register = [];

        $queue = new SynchronousQueue();
        $queue->setLogger(new NullLogger());

        $property = new \ReflectionProperty(SynchronousQueue::class, 'task');
        $property->setAccessible(true);
        $property->setValue($queue, $task);

        $this->assertEquals($task, $queue->reserve($register));
    }

    public function testReserveEmptyQueue()
    {
        $register = [];

        $queue = new SynchronousQueue();
        $queue->setLogger(new NullLogger());

        $this->assertNull($queue->reserve($register));
    }

    public function testRelease()
    {
        $task = new TestTask();

        $queue = new SynchronousQueue();
        $queue->setLogger(new NullLogger());

        $property = new \ReflectionProperty(SynchronousQueue::class, 'task');
        $property->setAccessible(true);
        $property->setValue($queue, $task);

        $this->assertTrue($queue->release($task));
        $this->assertEquals($task, $property->getValue($queue));
    }

    public function testRemove()
    {
        $task = new TestTask();

        $queue = new SynchronousQueue();
        $queue->setLogger(new NullLogger());

        $property = new \ReflectionProperty(SynchronousQueue::class, 'task');
        $property->setAccessible(true);
        $property->setValue($queue, $task);

        $this->assertTrue($queue->remove($task));
        $this->assertNull($property->getValue($queue));
    }

    public function testFail()
    {
        $task = new TestTask();

        $queue = new SynchronousQueue();
        $queue->setLogger(new NullLogger());

        $property = new \ReflectionProperty(SynchronousQueue::class, 'task');
        $property->setAccessible(true);
        $property->setValue($queue, $task);

        $queue->fail($task, new \RuntimeException());
        $this->assertNull($property->getValue($queue));
    }
}