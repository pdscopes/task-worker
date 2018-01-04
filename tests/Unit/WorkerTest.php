<?php

namespace MadeSimple\TaskWorker\Test\Unit;

use MadeSimple\TaskWorker\Task;
use MadeSimple\TaskWorker\Test\TestTask;
use MadeSimple\TaskWorker\Worker;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

class WorkerTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|CacheInterface
     */
    protected $mockCache;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|LoggerInterface
     */
    protected $mockLogger;

    protected function setUp()
    {
        parent::setUp();

        $this->mockCache = $this->getMockBuilder(CacheInterface::class)->getMock();
        $this->mockLogger = $this->getMockBuilder(LoggerInterface::class)->getMock();
    }

    public function testPrepare()
    {
        $worker = new Worker($this->mockCache, $this->mockLogger);
        $task   = new TestTask();
        $worker
            ->addHandler(function (Task $task) {
                $task['foo'] = 'bar';
            })
            ->prepare($task);

        $reflection = new \ReflectionClass(TestTask::class);
        $property   = $reflection->getProperty('logger');
        $property->setAccessible(true);

        $this->assertEquals($this->mockLogger, $property->getValue($task));
        $this->assertEquals(1, $task->attempts());
        $this->assertEquals('bar', $task['foo']);
    }

    /**
     * @dataProvider shouldContinueWorkingProvider
     */
    public function testShouldContinueWorking($options, $values, $task, $shouldContinue, $reason)
    {
        $worker = new Worker($this->mockCache, $this->mockLogger);
        $worker->setOptions($options);

        $reflection = new \ReflectionClass(Worker::class);
        $property   = $reflection->getProperty('taskCount');
        $property->setAccessible(true);
        $property->setValue($worker, $values['taskCount'] ?? 2);
        $property   = $reflection->getProperty('startTime');
        $property->setAccessible(true);
        $property->setValue($worker, $values['startTime'] ?? time());

        $this->mockCache->method('get')->willReturn($values['restartTime'] ?? 0);

        $this->assertEquals($shouldContinue, $worker->shouldContinueWorking($task));

        $property   = $reflection->getProperty('exitReason');
        $property->setAccessible(true);
        $this->assertEquals($reason, $property->getValue($worker));
    }

    public function shouldContinueWorkingProvider()
    {
        return [
            [[Worker::OPT_MAX_TASKS => 2], ['taskCount' => 2], null, true, ''],
            [[Worker::OPT_MAX_TASKS => 2], ['taskCount' => 3], null, false, 'maximum number of tasks reached'],

            [[Worker::OPT_ALIVE => 1], [], null, true, ''],
            [[Worker::OPT_ALIVE => 1], ['startTime' => -5], null, false, 'maximum alive time reached'],

            [[], [], null, true, ''],
            [[], ['restartTime' => time() + 5], null, false, 'restart signal received'],

            [[Worker::OPT_UNTIL_EMPTY => 1], [], new TestTask(), true, ''],
            [[Worker::OPT_UNTIL_EMPTY => 1], [], null, false, 'queue is now empty'],
        ];
    }
}