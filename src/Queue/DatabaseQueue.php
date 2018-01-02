<?php

namespace MadeSimple\TaskWorker\Queue;

use MadeSimple\TaskWorker\Exception\QueueConnectionException;
use MadeSimple\TaskWorker\HasOptionsTrait;
use MadeSimple\TaskWorker\Queue;
use MadeSimple\TaskWorker\Task;
use Psr\Log\LoggerAwareTrait;

/**
 * Class DatabaseManager
 *
 * @package MadeSimple\TaskWorker\Queue
 * @author  Peter Scopes
 */
class DatabaseQueue implements Queue
{
    use LoggerAwareTrait, HasOptionsTrait;

    /** Option to set the name of the table to read from. */
    const OPT_TABLE_NAME = 'database-table-name';

    /**
     * @return array
     */
    public static function defaultOptions() : array
    {
        return [
            self::OPT_TABLE_NAME => 'worker_task',
        ];
    }

    /**
     * @var array|string[]
     */
    protected $names;

    /**
     * @var \PDO
     */
    protected $pdo;

    /**
     * DatabaseQueue constructor.
     *
     * @param \PDO  $pdo
     * @param array $names
     */
    public function __construct(\PDO $pdo, array $names = [])
    {
        $this->pdo   = $pdo;
        $this->names = $names;

        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->setOptions(self::defaultOptions());
    }

    /**
     * @return Task
     */
    function reserve()
    {
        try {
            // Find the next available task
            if (empty($this->names)) {
                $statement =
                    $this->pdo->prepare('SELECT * FROM `'.$this->options[self::OPT_TABLE_NAME].'` WHERE `availableAt` <= UNIX_TIMESTAMP() AND `reservedAt` IS NULL AND `failedAt` IS NULL ORDER BY `createdAt` ASC LIMIT 1');
            } else {
                $queues    = implode(',', array_fill_keys(array_keys($this->names), '?'));
                $statement =
                    $this->pdo->prepare('SELECT * FROM `'.$this->options[self::OPT_TABLE_NAME].'` WHERE `queue` IN (' . $queues . ') AND `availableAt` <= UNIX_TIMESTAMP() AND `reservedAt` IS NULL AND `failedAt` IS NULL ORDER BY `createdAt` ASC LIMIT 1');
                foreach($this->names as $k => $name) {
                    $statement->bindValue($k+1, $name);
                }
            }
            $statement->execute();
            $row = $statement->fetch(\PDO::FETCH_ASSOC);

            // Return null if empty
            if (empty($row)) {
                return null;
            }

            // Un-serialise the task and reserve.
            /** @var Task $task */
            $task = unserialize($row['payload']);
            $task->setId($row['id']);
            $task->setAttempts($row['attempts']);

            $statement = $this->pdo->prepare('UPDATE `'.$this->options[self::OPT_TABLE_NAME].'` SET `reservedAt` = UNIX_TIMESTAMP() WHERE `id` = :id');
            $statement->bindValue(':id', $task->id());
            $statement->execute();

            return $task;
        }
        catch (\PDOException $e) {
            throw new QueueConnectionException('Task retrieval failed', 1, $e);
        }
    }

    /**
     * @param Task $task
     *
     * @return bool
     */
    function release(Task $task): bool
    {
        // Update the number of attempts and release the reservation
        $statement = $this->pdo->prepare('UPDATE `'.$this->options[self::OPT_TABLE_NAME].'` SET  `attempts` = :attempts, `reservedAt` = NULL, `payload` = :payload WHERE `id` = :id');
        $statement->bindValue(':attempts', min($task->attempts(), 255));
        $statement->bindValue(':payload', serialize($task));
        $statement->bindValue(':id', $task->id());

        return $statement->execute() && $statement->rowCount() === 1;
    }

    /**
     * @param Task $task
     *
     * @return bool
     */
    function add(Task $task): bool
    {
        $statement = $this->pdo->prepare('INSERT INTO `'.$this->options[self::OPT_TABLE_NAME].'` (`queue`, `payload`, `availableAt`, `createdAt`) VALUES (:queue, :payload, UNIX_TIMESTAMP() + :delay, UNIX_TIMESTAMP())');
        $statement->bindValue(':queue', $task->queue());
        $statement->bindValue(':payload', serialize($task));
        $statement->bindValue(':delay', $task->delay());

        return $statement->execute() && $statement->rowCount() === 1;
    }

    /**
     * @param Task $task
     *
     * @return bool
     */
    function remove(Task $task): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM `'.$this->options[self::OPT_TABLE_NAME].'` WHERE `id` = :id');
        $statement->bindValue(':id', $task->id());

        return $statement->execute() && $statement->rowCount() === 1;
    }

    function fail(Task $task, \Throwable $throwable)
    {
        $statement = $this->pdo->prepare('UPDATE `'.$this->options[self::OPT_TABLE_NAME].'` SET `failedAt` = UNIX_TIMESTAMP() WHERE `id` = :id');
        $statement->bindValue(':id', $task->id());

        $statement->execute();
    }
}