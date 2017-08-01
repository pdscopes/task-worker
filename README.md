# MadeSimple - Task Worker
The task-worker package is a generic task worker primarily for background tasks.

## Worker
The worker patiently waits for tasks from the queue. Upon receiving a task it
performs the task, and assuming no exception was thrown, removes the task from
the queue.

Before checking the queue for the next message the worker asks whether is should
continue working. If it has outlived the length of time it should be alive for
or there has been restart signal broadcast then the worker will check again.

## Queues
There are currently two types of queues supported: database and synchronous.
Custom queues can be created and must implement the Queue interface.

### Database
The database queue uses a single table in an MySQL compatible database.
There must exist a table that has the following schema (the name of the table can
be changed in the DatabaseQueue options):
```mysql
CREATE TABLE `worker_task` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` char(255) DEFAULT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `reservedAt` int(10) unsigned DEFAULT NULL,
  `availableAt` int(10) unsigned NOT NULL,
  `createdAt` int(10) unsigned NOT NULL,
  `failedAt` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `queue` (`queue`,`reservedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

### Synchronous
The synchronous queue is a faux queue which immediately performs any task at the
point it is added. It will respect the delay if set.

## Commands
There are some commands already defined for `symfony/console`.

## Examples
There are some examples in the namesake directory. You will need to `composer install`
with dev requirements for them to work.