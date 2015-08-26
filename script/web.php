<?php

use tourze\Server\Worker;

// 检查扩展
if ( ! extension_loaded('pcntl'))
{
    exit("Please install pcntl extension. See http://doc3.workerman.net/install/install.html\n");
}
if ( ! extension_loaded('posix'))
{
    exit("Please install posix extension. See http://doc3.workerman.net/install/install.html\n");
}

require '../bootstrap.php';

Worker::load('web');
Worker::runAll();
