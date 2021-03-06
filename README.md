# MadeSimple - Task Worker
[![Build Status](https://travis-ci.org/pdscopes/task-worker.svg?branch=master)](https://travis-ci.org/pdscopes/task-worker)

The task-worker package is a generic task worker primarily for background tasks.

Workers patiently wait to reserve Tasks from their Queue. When they receive a
task they prepare and perform the task. The task object itself stores the logic
for performing the task. If the task was successfully performed, that
is it did not throw an exception, then the task is removed from the queue.
If the task threw an exception then it is put back into the queue to be performed
again.

## Installation
Install via [composer](https://getcomposer.org/):
```
{
    "require": {
        "madesimple/task-worker": "~2.0"
    }
}
```
Then run `composer install` or run `composer require madesimple/task-worker`.

## Task
***NOTE*** `Task`'s _must_ be constructable with an empty construction, i.e.: `new ExampleTask()`.

Tasks are a combination of the data required and the business logic to perform them.
Tasks must `extend` the abstract `\MadeSimple\TaskWorker\Task` class and only need implement `public function perform()`.
Task data can be set using `\ArrayAccess` (e.g. `$task['foo'] ='bar'`) or directly inside the class (e.g. `$this->data['foo'] = 'bar';`).

Tasks are serialized in JSON messages when they are put into a queue:
```json
{
    "identifier": "38213-5a4f5275644c2",
    "register": "\\Namespace\\ClassName",
    "queue": "task_queue",
    "attempts": 0,
    "data": {
        "foo": "bar"
    }
}
```

A Task's `identifier` is automatically generated when `identifier()` is called and is created using `uniqid(getmypid() . '-')`; cloned Tasks ***do not*** share their `identifier` or `attempts`.
A Task's `register` defaults to `static::class` and is used in `Task::deserialize` to work out which Task class should be used; `deserialize` firstly checks its `register` (passed to it from the `Worker`) for a match, next it attempts to autoload, finally fails if neither of these return a Task.
You can `register` inside the Task to be any string, just be sure to register the value with the `Worker` if it is not autoloadable:
```php
<?php
class MyTask extends \MadeSimple\TaskWorker\Task
{
    protected $register = 'my_task';

    // ... rest of the class goes here
}
```
A Task's `queue` is simply the queue it should be placed on and is generally set `$task->onQueue('queue_name')`.
A Task's `attempts` is a simple counter which is incremented every time a `Worker` attempts to perform it.
A Task's `data` should be a JSON serializable set of information required to perform the task.

## Worker
The worker patiently waits for tasks from the queue. Upon receiving a task it
performs the task, and assuming no exception was thrown, removes the task from
the queue.

Before checking the queue for the next message the worker asks whether is should
continue working. If it has outlived the length of time it should be alive for
or there has been restart signal broadcast then the worker will check again.

### Options
Available options for Workers are:
* `OPT_SLEEP`: how long, in seconds, to wait if not tasks are in the queue before checking again
* `OPT_ATTEMPTS`: how many attempts a task is allowed before being failed (zero is unlimited)
* `OPT_ALIVE`: how long, in seconds, the worker will stay alive for (zero is unlimited)
* `OPT_REST`: how long, in milliseconds, to rest between tasks
* `OPT_MAX_TASKS`: the maximum number of tasks a worker should perform before stopping (zero is unlimited)
* `OPT_UNTIL_EMPTY`: set the worker to stop when the queue it empty

### Handlers
Handlers are closures you can add to a worker to help prepare a task to be performed.
Every handler that has been added to a worker is called before every task is performed.
Common usage for this would be dependency injection. Below is the signature a handler
must have (can be an anonymous function or invokable class):
```php
function (Task $task) {
    // logic goes here
}
```

## Queues
There are currently four types of queues support, `MySQL`, `RabbitMQ`, `Redis`, and `Synchronous`.
Custom queues can be created and must implement the `Queue` interface. When implementing a Queue the convention is
to provide a list of queue names. An implementation of a Queue can throw a
`MadeSimple\TaskWorker\Exception\QueueNameRequiredException` if no queue names are provided.

### MySQL
The MySQL queue uses a single table in an MySQL compatible database.
There must exist a table that has the following schema (the name of the table can
be changed in the `MysqlQueue` options):
```mysql
CREATE TABLE `worker_task` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` char(255) DEFAULT '',
  `payload` longtext NOT NULL,
  `reservedAt` int(10) unsigned DEFAULT NULL,
  `releasedAt` int(10) unsigned NOT NULL,
  `failedAt` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `queue` (`queue`,`releasedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

Available options for the MysqlQueue are:
* `OPT_TABLE_NAME`: the name of the table to read tasks from

### RabbitMQ
The RabbitMQ queue uses the `"php-amqplib/php-amqplib": "^2.7"` library to connect to a RabbitMQ instance (or cluster).
The `default` exchange is used to route tasks to the specified queue.
Dead letters are used to delay tasks, the queue name is generated as follows: `delayed_<seconds>_<queue_name>`.

### Redis
The Redis queue uses the `"predis/predis": "^1.1"` library to connect to a redis instance.
The queue names are directly used and there are the following pseudo queues:
* `<queue_name>-processing` to hold the workers current task, and
* `<queue_name>-delayed` to hold delayed tasks.


### Synchronous
The synchronous queue is a faux queue which immediately performs any task at the point it is added.
It will respect the delay if set by sleeping the thread that called `add` for the specified time.

## Commands
There are some commands already defined for `symfony/console`.

## Examples
There are some examples in the namesake directory. You will need to `composer install`
with dev requirements for them to work. Copy `examples/.env.example` to `examples/.env` and update
the variable values for your environment then the examples can be run using the following command:

```bash
export $(cat examples/.env | xargs) && php examples/
```

# External Documentation
Links to documentation for external dependencies:
* [PHP Docs](http://php.net/)
* [Logging PSR-3](http://www.php-fig.org/psr/psr-3/) ([GitHub page](https://github.com/php-fig/log))
* [Simple Cache PSR-16](http://www.php-fig.org/psr/psr-16/) ([GitHub page](https://github.com/php-fig/simple-cache))

Links to documentation for development only external dependencies:
* [cache/cache](http://www.php-cache.com/en/latest/)
* [monolog/monolog](https://github.com/Seldaek/monolog)
* [symfony/console](http://symfony.com/doc/current/components/console.html)
* [php-amqplib/php-amqplib](https://github.com/php-amqplib/php-amqplib)
* [predis/predis](https://github.com/nrk/predis)
