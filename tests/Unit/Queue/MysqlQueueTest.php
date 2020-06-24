<?php

namespace MadeSimple\TaskWorker\Test\Unit\Queue;

use MadeSimple\TaskWorker\Queue\MysqlQueue;
use MadeSimple\TaskWorker\Test\TestTask;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class MysqlQueueTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|\PDO
     */
    protected $mockPdo;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|\PDOStatement
     */
    protected $mockPdoStatement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockPdo = $this->getMockBuilder(\PDO::class)->disableOriginalConstructor()->getMock();
        $this->mockPdoStatement = $this->getMockBuilder(\PDOStatement::class)->disableOriginalConstructor()->getMock();
    }


    public function testAdd()
    {
        $task = new TestTask();
        $task->onQueue('queue_name');

        $this->mockPdo
            ->expects($this->once())
            ->method('prepare')
            ->with('INSERT INTO `worker_task` (`queue`, `payload`, `releasedAt`) VALUES (:queue, :payload, UNIX_TIMESTAMP() + :delay)')
            ->willReturn($this->mockPdoStatement);
        $this->mockPdoStatement
            ->expects($this->exactly(3))
            ->method('bindValue')
            ->withConsecutive(
                [':queue', $task->queue()],
                [':payload', $task->serialize()],
                [':delay', $task->delay()]
            );
        $this->mockPdoStatement
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        $this->mockPdoStatement
            ->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $queue = new MysqlQueue(['queue_name'], $this->mockPdo);
        $queue->setLogger(new NullLogger());

        $this->assertTrue($queue->add($task));
    }

    public function testReserve()
    {
        $task = new TestTask();
        $row = [
            'id' => 3,
            'payload' => $task->serialize(),
        ];
        $register = [];

        $this->mockPdo
            ->expects($this->exactly(2))
            ->method('prepare')
            ->willReturn($this->mockPdoStatement);
        $this->mockPdoStatement
            ->expects($this->exactly(2))
            ->method('bindValue')
            ->withConsecutive(
                [1, 'queue_name'],
                [':id', 3]
            );
        $this->mockPdoStatement
            ->expects($this->exactly(2))
            ->method('execute')
            ->with();
        $this->mockPdoStatement
            ->expects($this->once())
            ->method('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn($row);


        $queue = new MysqlQueue(['queue_name'], $this->mockPdo);
        $queue->setLogger(new NullLogger());

        $this->assertEquals($task->identifier(), $queue->reserve($register)->identifier());
        $this->assertEquals([TestTask::class => new TestTask()], $register);
    }

    public function testReserveEmptyQueue()
    {
        $register = [];

        $this->mockPdo
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockPdoStatement);
        $this->mockPdoStatement
            ->expects($this->once())
            ->method('bindValue')
            ->with(1, 'queue_name');
        $this->mockPdoStatement
            ->expects($this->once())
            ->method('execute')
            ->with();
        $this->mockPdoStatement
            ->expects($this->once())
            ->method('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn(false);


        $queue = new MysqlQueue(['queue_name'], $this->mockPdo);
        $queue->setLogger(new NullLogger());

        $this->assertNull($queue->reserve($register));
        $this->assertEquals([], $register);
    }

    public function testRelease()
    {
        $task = new TestTask();

        $this->mockPdo
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockPdoStatement);
        $this->mockPdoStatement
            ->expects($this->exactly(2))
            ->method('bindValue')
            ->withConsecutive(
                [':payload', $task->serialize()],
                [':id', 3]
            );
        $this->mockPdoStatement
            ->expects($this->once())
            ->method('execute')
            ->with()
            ->willReturn(true);
        $this->mockPdoStatement
            ->expects($this->once())
            ->method('rowCount')
            ->with()
            ->willReturn(1);

        $queue = new MysqlQueue(['queue_name'], $this->mockPdo);
        $queue->setLogger(new NullLogger());

        $property = new \ReflectionProperty(MysqlQueue::class, 'row');
        $property->setAccessible(true);
        $property->setValue($queue, ['id' => 3]);

        $this->assertTrue($queue->release($task));
    }

    public function testRemove()
    {
        $task = new TestTask();

        $this->mockPdo
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockPdoStatement);
        $this->mockPdoStatement
            ->expects($this->once())
            ->method('bindValue')
            ->with(':id', 3);
        $this->mockPdoStatement
            ->expects($this->once())
            ->method('execute')
            ->with()
            ->willReturn(true);
        $this->mockPdoStatement
            ->expects($this->once())
            ->method('rowCount')
            ->with()
            ->willReturn(1);

        $queue = new MysqlQueue(['queue_name'], $this->mockPdo);
        $queue->setLogger(new NullLogger());

        $property = new \ReflectionProperty(MysqlQueue::class, 'row');
        $property->setAccessible(true);
        $property->setValue($queue, ['id' => 3]);

        $this->assertTrue($queue->remove($task));
    }

    public function testFail()
    {
        $task = new TestTask();

        $this->mockPdo
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockPdoStatement);
        $this->mockPdoStatement
            ->expects($this->once())
            ->method('bindValue')
            ->with(':id', 3);
        $this->mockPdoStatement
            ->expects($this->once())
            ->method('execute')
            ->with()
            ->willReturn(true);

        $queue = new MysqlQueue(['queue_name'], $this->mockPdo);
        $queue->setLogger(new NullLogger());

        $property = new \ReflectionProperty(MysqlQueue::class, 'row');
        $property->setAccessible(true);
        $property->setValue($queue, ['id' => 3]);

        $queue->fail($task, new \RuntimeException());
    }
}