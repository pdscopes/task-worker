<?php

namespace MadeSimple\TaskWorker\Queue;

use MadeSimple\TaskWorker\Exception\QueueConnectionException;
use MadeSimple\TaskWorker\HasOptionsTrait;
use MadeSimple\TaskWorker\Queue;
use MadeSimple\TaskWorker\Task;
use Psr\Log\LoggerAwareTrait;

class MysqlQueue implements Queue
{
    use LoggerAwareTrait, HasOptionsTrait;

    /** Option to set the name of the table to read from. */
    const OPT_TABLE_NAME = 'table-name';

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
     * @var array
     */
    protected $row;

    /**
     * MysqlQueue constructor.
     *
     * @param string|array $names
     * @param \PDO  $pdo
     */
    public function __construct($names, \PDO $pdo)
    {
        $this->names = (array) $names;
        $this->pdo   = $pdo;

        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->setOptions(self::defaultOptions());
    }

    /**
     * @param Task $task
     *
     * @return bool
     */
    function add(Task $task): bool
    {
        $serialized = $task->serialize();

        $statement = $this->pdo->prepare('INSERT INTO `'.$this->options[self::OPT_TABLE_NAME].'` (`queue`, `payload`, `releasedAt`) VALUES (:queue, :payload, UNIX_TIMESTAMP() + :delay)');
        $statement->bindValue(':queue', $task->queue());
        $statement->bindValue(':payload', $serialized);
        $statement->bindValue(':delay', $task->delay());

        if ($statement->execute() && $statement->rowCount() === 1) {
            $this->logger->debug('Added task: ' . $serialized);
            return true;
        }
        return false;
    }

    /**
     * @param array|Task[] $register
     * @return Task
     */
    function reserve(array &$register)
    {
        try {
            // Find the next available task
            $queues    = substr(str_repeat(',?', count($this->names)), 1);
            $statement = $this->pdo->prepare(
                'SELECT * FROM `'.$this->options[self::OPT_TABLE_NAME].'` '.
                'WHERE `queue` IN (' . $queues . ') '.
                'AND `releasedAt` <= UNIX_TIMESTAMP() '.
                'AND `reservedAt` IS NULL '.
                'AND `failedAt` IS NULL '.
                'ORDER BY `releasedAt` ASC LIMIT 1'
            );
            foreach($this->names as $k => $name) {
                $statement->bindValue($k+1, $name);
            }
            $statement->execute();
            $row = $statement->fetch(\PDO::FETCH_ASSOC);

            // Return null if empty
            if (empty($row)) {
                return null;
            }

            // Un-serialise the task and reserve.
            $this->row = $row;
            /** @var Task $task */
            $task = Task::deserialize($register, $row['payload']);

            $statement = $this->pdo->prepare('UPDATE `'.$this->options[self::OPT_TABLE_NAME].'` SET `reservedAt` = UNIX_TIMESTAMP() WHERE `id` = :id');
            $statement->bindValue(':id', (int) $this->row['id']);
            $statement->execute();

            $this->logger->debug('Reserved task', $row);

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
        $statement = $this->pdo->prepare('UPDATE `'.$this->options[self::OPT_TABLE_NAME].'` SET `reservedAt` = NULL, `releasedAt` = UNIX_TIMESTAMP(), `payload` = :payload WHERE `id` = :id');
        $statement->bindValue(':payload', $task->serialize());
        $statement->bindValue(':id', (int) $this->row['id']);

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
        $statement->bindValue(':id', (int) $this->row['id']);

        return $statement->execute() && $statement->rowCount() === 1;
    }

    function fail(Task $task, \Throwable $throwable)
    {
        $statement = $this->pdo->prepare('UPDATE `'.$this->options[self::OPT_TABLE_NAME].'` SET `failedAt` = UNIX_TIMESTAMP() WHERE `id` = :id');
        $statement->bindValue(':id', (int) $this->row['id']);

        $statement->execute();
    }
}