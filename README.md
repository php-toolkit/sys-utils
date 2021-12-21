# System Utils

[![License](https://img.shields.io/packagist/l/toolkit/sys-utils.svg?style=flat-square)](LICENSE)
[![Php Version](https://img.shields.io/badge/php-%3E8.0.0-brightgreen.svg?maxAge=2592000)](https://packagist.org/packages/toolkit/sys-utils)
[![Latest Stable Version](http://img.shields.io/packagist/v/toolkit/sys-utils.svg)](https://packagist.org/packages/toolkit/sys-utils)

Some useful system utils for php

- exec system command
- simple process usage
- php env info
- error and exception info

## Install

```bash
composer require toolkit/sys-utils
```

## Usage

### ProcTasks

**Example**:

```php
use Toolkit\Sys\Proc\ProcTasks;

// ProcTasks::new() // will auto get cpu num as proc num
ProcTasks::new(['procNum' => 2])
    ->setTaskHandler(function (array $task, array $ctx) {
        $pid = $ctx['pid'];
        println("worker#{$ctx['id']} [PID:$pid] - handle task, task data", $task);
        sleep(random_int(1, 3));
    })
    ->onBeforeCreate(fn($pid, $num) => println("master [PID:$pid] - Will create task process, number:", $num))
    ->onWorkersCreated(fn($pid, $info) => println("master [PID:$pid] - All task process started,", 'Workers info', $info))
    ->onWorkerStart(fn($pid, $id) => println("worker#$id started, pid is", $pid))
    ->onWorkerExit(fn($pid, $id) => println("worker#$id exited, pid is", $pid))
    ->onCompleted(fn($pid) => println("master [PID:$pid] - all workers exited, tasks run completed"))
    ->setTasks([
        ['task1'], // one task
        ['task2'],
        ['task3'],
        ['task4'],
    ])
    ->addTask(['task5'])
    ->run();
```

**Run**: 

```bash
php example/proc-tasks.php
```

**Output**:

```text
master [PID:49731] - Will create task process, number: 2
master [PID:49731] - All task process started, Workers info {"49732":{"id":0,"pid":49732,"startAt":1639245860},"49733":{"id":1,"pid":49733,"startAt":1639245860}}
worker#0 started, pid is 49732
worker#0 [PID:49732] - handle task, task data ["task1"]
worker#1 started, pid is 49733
worker#1 [PID:49733] - handle task, task data ["task4"]
worker#1 [PID:49733] - handle task, task data ["task5"]
worker#0 [PID:49732] - handle task, task data ["task2"]
worker#1 exited, pid is 49733
worker#0 [PID:49732] - handle task, task data ["task3"]
worker#0 exited, pid is 49732
master [PID:49731] - all workers exited, tasks run completed

```

## License

[MIT](LICENSE)
