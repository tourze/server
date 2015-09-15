<?php

namespace tourze\Server\Component;

use tourze\Base\Component\Log as BaseLog;
use tourze\Server\Server;

/**
 * Server的日志组件
 *
 * @package tourze\Server\Component
 */
class Log extends BaseLog
{

    /**
     * @var string 输出日志的时间格式
     */
    public $timeFormat = 'e Y-m-d H:i:s:u';

    /**
     * @var array 在此列表中的错误级别会被忽略
     */
    public $ignoreLevels = [];

    /**
     * 检查指定的级别是否被忽略了
     *
     * @param string $level
     * @return bool
     */
    public function checkIgnoreList($level)
    {
        return in_array($level, $this->ignoreLevels);
    }

    /**
     * @inheritdoc
     */
    public function debug($log, array $context = [])
    {
        if ($this->checkIgnoreList('debug'))
        {
            return;
        }
        $context = stripslashes(json_encode($context, JSON_UNESCAPED_UNICODE));
        Server::getCli()->yellow(date($this->timeFormat) . " DEBUG: $log [$context]");
    }

    /**
     * @inheritdoc
     */
    public function info($log, array $context = [])
    {
        if ($this->checkIgnoreList('info'))
        {
            return;
        }
        $context = stripslashes(json_encode($context, JSON_UNESCAPED_UNICODE));
        Server::getCli()->white(date($this->timeFormat) . " INFO: $log [$context]");
    }

    /**
     * @inheritdoc
     */
    public function notice($log, array $context = [])
    {
        if ($this->checkIgnoreList('notice'))
        {
            return;
        }
        $context = stripslashes(json_encode($context, JSON_UNESCAPED_UNICODE));
        Server::getCli()->blue(date($this->timeFormat) . " NOTICE: $log [$context]");
    }

    /**
     * @inheritdoc
     */
    public function warning($log, array $context = [])
    {
        if ($this->checkIgnoreList('warning'))
        {
            return;
        }
        $context = stripslashes(json_encode($context, JSON_UNESCAPED_UNICODE));
        Server::getCli()->red(date($this->timeFormat) . " WARNING: $log [$context]");
    }

    /**
     * @inheritdoc
     */
    public function error($log, array $context = [])
    {
        if ($this->checkIgnoreList('error'))
        {
            return;
        }
        $context = stripslashes(json_encode($context, JSON_UNESCAPED_UNICODE));
        Server::getCli()->lightRed(date($this->timeFormat) . " ERROR: $log [$context]");
    }

    /**
     * @inheritdoc
     */
    public function critical($log, array $context = [])
    {
        if ($this->checkIgnoreList('critical'))
        {
            return;
        }
        $context = stripslashes(json_encode($context, JSON_UNESCAPED_UNICODE));
        Server::getCli()->lightBlue(date($this->timeFormat) . " CRITICAL: $log [$context]");
    }

    /**
     * @inheritdoc
     */
    public function alert($log, array $context = [])
    {
        if ($this->checkIgnoreList('alert'))
        {
            return;
        }
        $context = stripslashes(json_encode($context, JSON_UNESCAPED_UNICODE));
        Server::getCli()->lightYellow(date($this->timeFormat) . " ALERT: $log [$context]");
    }

    /**
     * @inheritdoc
     */
    public function emergency($log, array $context = [])
    {
        if ($this->checkIgnoreList('emergency'))
        {
            return;
        }
        $context = stripslashes(json_encode($context, JSON_UNESCAPED_UNICODE));
        Server::getCli()->darkGray(date($this->timeFormat) . " EMERGENCY: $log [$context]");
    }
}
