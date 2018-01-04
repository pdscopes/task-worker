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

    protected function setUp()
    {
        parent::setUp();

        $this->mockPdo = $this->getMockBuilder(\PDO::class)->disableOriginalConstructor()->getMock();
        $this->mockPdoStatement = $this->getMockBuilder(\PDOStatement::class)->disableOriginalConstructor()->getMock();
    }

    public function testAdd()
    {
        $task = new TestTask();
        $task->onQueue('queue');

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

        $queue = new MysqlQueue(['queue'], $this->mockPdo);
        $queue->setLogger(new NullLogger());

        $this->assertTrue($queue->add($task));
    }
}