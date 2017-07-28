<?php

namespace MadeSimple\TaskWorker;

use MadeSimple\TaskWorker\Exception\TaskRetrievalFailureException;
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
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Manager
     */
    protected $retriever;

    /**
     * @var string
     */
    protected $pidFile;

    /**
     * TaskWorker constructor.
     *
     * @param LoggerInterface|null $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
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
     * @return int 0 if everything went fine, or an error code
     */
    public function run(): int
    {
        try {
            do {
                // Retrieve next task
                $task = $this->retriever->retrieve();

                // Perform task
                if ($task !== null) {
                    try {
                        $task->setComplete(false);
                        $task->perform();
                        if ($task->isComplete()) {
                            // ...
                        }
                    }
                    catch (\Throwable $throwable) {
                        $this->logger->critical($throwable->getMessage(), ['trace' => $throwable->getTrace()]);
                    }

                    // Give system a little respite
                    usleep(50);
                } else {
                    // Patiently wait for next task to arrive
                    sleep(3);
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

    protected function shouldContinueWorking(): bool
    {
        return false;
    }
}