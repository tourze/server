<?php

namespace tourze\Server\Worker;

use tourze\Server\Worker;

/**
 * 基础
 *
 * @package tourze\Server\Worker
 */
abstract class Base
{

    /**
     * 设置Worker启动时的回调函数，即当Worker启动后立即执行Worker::onWorkerStart成员指定的回调函数
     *
     * @see http://doc3.workerman.net/worker-development/on_worker_start.html
     * @param Worker $worker
     */
    abstract public function onWorkerStart(Worker $worker);

    /**
     * 设置Workert停止时的回调函数，即当Worker收到stop信号后执行Worker::onWorkerStop指定的回调函数
     *
     * @see http://doc3.workerman.net/worker-development/on-worker-stop.html
     * @param Worker $worker
     */
    abstract public function onWorkerStop(Worker $worker);

    /**
     * 当有客户端连接时触发的回调函数
     *
     * @see http://doc3.workerman.net/worker-development/on-connect.html
     * @param Worker $worker
     */
    abstract public function onConnect(Worker $worker);

    /**
     * @see http://doc3.workerman.net/worker-development/on-message.html
     * @param Worker $worker
     * @param mixed  $data
     * @return
     */
    abstract public function onMessage(Worker $worker, $data);

    /**
     * @see http://doc3.workerman.net/worker-development/on-close.html
     * @param Worker $worker
     */
    abstract public function onClose(Worker $worker);

    /**
     * @see http://doc3.workerman.net/worker-development/on-buffer-full.html
     * @param Worker $worker
     */
    abstract public function onBufferFull(Worker $worker);

    /**
     * @see http://doc3.workerman.net/worker-development/on-buffer-drain.html
     * @param Worker $worker
     */
    abstract public function onBufferDrain(Worker $worker);

    /**
     * @see http://doc3.workerman.net/worker-development/on-error.html
     * @param Worker $worker
     * @param int    $code
     * @param string $msg
     * @return
     */
    abstract public function onError(Worker $worker, $code, $msg);
}
