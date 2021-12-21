<?php declare(strict_types=1);

use Toolkit\Sys\Proc\ProcTasks;

require dirname(__DIR__) . '/test/bootstrap.php';

// run: php example/proc-tasks.php
// ProcTasks::new() // will auto get cpu num as proc num
ProcTasks::new(['procNum' => 3])
    ->setProcName("procTasks")
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
