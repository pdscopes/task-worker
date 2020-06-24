<?php

namespace MadeSimple\TaskWorker\Test\Unit;

use MadeSimple\TaskWorker\Test\TestTask;
use PHPUnit\Framework\TestCase;

class TaskTest extends TestCase
{
    public function testClone()
    {
        $task1 = new TestTask();
        $task1->identifier();
        $task1->incrementAttempts();
        $task2 = clone $task1;

        $this->assertNotEquals($task1->identifier(), $task2->identifier());
        $this->assertNotEquals($task1->attempts(), $task2->attempts());
        $this->assertEquals(1, $task1->attempts());
        $this->assertEquals(0, $task2->attempts());
    }

    public function testJsonSerialize()
    {
        $task = new TestTask();
        $json = $task->jsonSerialize();

        $this->assertArrayHasKey('identifier', $json);
        $this->assertArrayHasKey('register', $json);
        $this->assertArrayHasKey('queue', $json);
        $this->assertArrayHasKey('attempts', $json);
        $this->assertArrayHasKey('data', $json);
    }

    public function testIdentifier()
    {
        $task = new TestTask();
        $identifier = $task->identifier();
        $this->assertNotEmpty($identifier);
        $this->assertEquals($identifier, $task->identifier());
    }

    public function testRegister()
    {
        $task = new TestTask();
        $this->assertEquals(TestTask::class, $task->register());
    }

    public function testQueue()
    {
        $task = new TestTask();
        $this->assertNull($task->queue());
        $this->assertEquals('queue_name', $task->onQueue('queue_name')->queue());
    }

    public function testAttempts()
    {
        $task = new TestTask();

        $this->assertEquals(0, $task->attempts());
        $this->assertEquals(1, $task->incrementAttempts()->attempts());
        $this->assertEquals(2, $task->incrementAttempts()->attempts());
        $this->assertEquals(5, $task->setAttempts(5)->attempts());
    }

    public function testDelay()
    {
        $task = new TestTask();
        $this->assertEquals(0, $task->delay());
        $this->assertEquals(15, $task->withDelay(15)->delay());
    }

    public function testSerialize()
    {
        $task = new TestTask();
        $serialized = $task->serialize();

        $this->assertIsString($serialized);
        $this->assertJson($serialized);
    }
}