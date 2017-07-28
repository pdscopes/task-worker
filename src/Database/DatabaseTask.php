<?php

namespace MadeSimple\TaskWorker\Database;

use MadeSimple\TaskWorker\Task;

/**
 * Class DatabaseTask
 *
 * @package MadeSimple\TaskWorker\Database
 * @author  Peter Scopes
 */
abstract class DatabaseTask implements Task
{

    /**
     * @var integer
     */
    protected $id;

    /**
     * @var string
     */
    protected $identifier;

    /**
     * @var boolean
     */
    protected $complete = false;

    /**
     * @var boolean
     */
    protected $repeat = false;

    /**
     * @var null|string Date time
     */
    protected $scheduled;

    /**
     * @var null|integer
     */
    protected $userId;

    /**
     * @var null|integer
     */
    protected $customerId;

    /**
     * @var null|integer
     */
    protected $step = null;

    /**
     * @var null|integer
     */
    protected $totalSteps = null;

    /**
     * @var null|integer
     */
    protected $percentage = null;

    /**
     * @var null|array
     */
    protected $data;

}