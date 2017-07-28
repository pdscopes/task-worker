<?php

namespace MadeSimple\TaskWorker\Database;

use MadeSimple\TaskWorker\Exception\TaskRetrievalFailureException;
use MadeSimple\TaskWorker\Manager;
use MadeSimple\TaskWorker\Task;

/**
 * Class DatabaseManager
 *
 * @package MadeSimple\TaskWorker\Database
 * @author  Peter Scopes
 */
class DatabaseManager implements Manager
{
    /**
     * @var \PDO
     */
    protected $pdo;

    public function __construct(\PDO $pdo)
    {
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    function retrieve(): Task
    {
        try {
            $statement =
                $this->pdo->prepare('SELECT task.* FROM task WHERE (task.complete != TRUE AND task.scheduled IS NULL) OR (task.scheduled <= NOW()) LIMIT 1');
            $statement->execute();
            $row = $statement->fetch();
            if (!empty($row)) {
                return new $row['className']($row);
            } else {
                return null;
            }
        }
        catch (\PDOException $e) {
            throw new TaskRetrievalFailureException('Task retrieval failed', 1, $e);
        }
    }

    function store(Task $task): bool
    {
        return false;
    }

    function remove(Task $task): bool
    {
        return false;
    }
}