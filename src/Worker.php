<?php

namespace MadeSimple\TaskWorker;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class Worker
 *
 * @package MadeSimple\TaskWorker
 * @author  Peter Scopes
 */
class Worker
{
    use LoggerAwareTrait, HasOptionsTrait;

    /** Option to set how long, in seconds, to wait if not tasks are in the queue before checking again. */
    const OPT_SLEEP = 'sleep';
    /** Option to set how many attempts a task is allowed before being failed (zero is unlimited). */
    const OPT_ATTEMPTS = 'attempts';
    /** Option to set how long, in seconds, the worker will stay alive for (zero is unlimited). */
    const OPT_ALIVE = 'alive';
    /** Option to set how long, in milliseconds, to rest between tasks. */
    const OPT_REST = 'rest';
    /** Option to set the location of a temporary directory that the worker can write to. */
    const OPT_TMP_DIR = 'tmp-dir';

    /**
     * @return array
     */
    public static function defaultOptions() : array
    {
        return [
            static::OPT_SLEEP    => 3,
            static::OPT_ATTEMPTS => 0,
            static::OPT_ALIVE    => 0,
            static::OPT_REST     => 50,
            static::OPT_TMP_DIR  => sys_get_temp_dir(),
        ];
    }

    /**
     * Broadcast a restart signal to all workers.
     *
     * @param null|string $tmpDirectory
     *
     * @return bool
     */
    public static function restart($tmpDirectory = null) : bool
    {
        $tmpDirectory = $tmpDirectory ?? self::defaultOptions()[self::OPT_TMP_DIR];

        return file_put_contents($tmpDirectory . '/restart', time()) === false;
    }


    /**
     * @var Queue
     */
    protected $queue;

    /**
     * @var string
     */
    protected $pidFile;

    protected $startTime;

    /**
     * TaskWorker constructor.
     *
     * @param LoggerInterface|null $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->setLogger($logger ?? new NullLogger());
        $this->setOptions(static::defaultOptions());
    }

    /**
     * Tidy up after task worker.
     */
    public function __destruct()
    {
        if ($this->pidFile !== null && file_exists($this->pidFile)) {
            unlink($this->pidFile);
        }
    }

    /**
     * @param Queue $queue
     *
     * @return Worker
     */
    public function setQueue(Queue $queue)
    {
        $this->queue = $queue;

        return $this;
    }

    /**
     * @return int 0 if everything went fine, or an error code
     */
    public function run(): int
    {
        $this->logger->debug('Listening to queue', ['worker' => get_class($this), 'queue' => get_class($this->queue), 'options' => $this->options]);
        $this->startTime = time();

        try {
            do {
                // Retrieve next task
                $task = $this->queue->reserve();

                // Perform task
                if ($task !== null) {
                    try {
                        $task->setLogger($this->logger);
                        $task->incrementAttempts();

                        $task->perform();
                        $this->queue->remove($task);
                    }
                    catch (\Throwable $throwable) {
                        $this->logger->critical($throwable->getMessage(), ['trace' => $throwable->getTrace()]);
                        if ($this->options[self::OPT_ATTEMPTS] > 0 && $task->attempts() >= $this->options[static::OPT_ATTEMPTS]) {
                            $this->logger->critical('Task failed', ['task' => $task]);
                            $task->fail($throwable);
                            $this->queue->fail($task, $throwable);
                        } else {
                            $this->queue->release($task);
                        }
                    }

                    // Give system a little respite
                    if ($this->options[self::OPT_REST] > 0) {
                        usleep($this->options[self::OPT_REST]);
                    }
                } else {
                    // Patiently wait for next task to arrive
                    sleep($this->options[self::OPT_SLEEP]);
                }
            }
            while ($this->shouldContinueWorking());
        }
        catch (\Throwable $throwable) {
            $this->logger->critical($throwable->getMessage(), ['trace' => $throwable->getTrace()]);

            return 1;
        }

        return 0;
    }

    /**
     * @return bool
     */
    protected function shouldContinueWorking(): bool
    {
        $restartFile = $this->options[self::OPT_TMP_DIR] . '/restart';
        $restartTime = file_exists($restartFile) ? (int) file_get_contents($restartFile) : 0;

        return
            ($this->options[self::OPT_ALIVE] <= 0 || $this->options[self::OPT_ALIVE] > round(time() - $this->startTime)) &&
            ($restartTime === 0 || $restartTime <= $this->startTime);
    }
}