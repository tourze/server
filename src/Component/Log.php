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
    public $timeFormat = 'e Y-m-d H:i:s';

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
     * 返回当前时间
     *
     * @return string
     */
    public function currentTime()
    {
        return date($this->timeFormat) . substr((string) microtime(), 1, 8);
    }

    /**
     * 格式化上下文信息
     *
     * @param array $context
     * @return string
     */
    public function formatContext($context)
    {
        if (empty($context))
        {
            return '';
        }
        return '[' . stripslashes(json_encode($context, JSON_UNESCAPED_UNICODE)) . ']';
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
        $context = $this->formatContext($context);
        Server::getCli()->yellow($this->currentTime() . " DEBUG: $log $context");
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
        $context = $this->formatContext($context);
        Server::getCli()->white($this->currentTime() . " INFO: $log $context");
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
        $context = $this->formatContext($context);
        Server::getCli()->blue($this->currentTime() . " NOTICE: $log $context");
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
        $context = $this->formatContext($context);
        Server::getCli()->red($this->currentTime() . " WARNING: $log $context");
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
        $context = $this->formatContext($context);
        Server::getCli()->lightRed($this->currentTime() . " ERROR: $log $context");
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
        $context = $this->formatContext($context);
        Server::getCli()->lightBlue($this->currentTime() . " CRITICAL: $log $context");
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
        $context = $this->formatContext($context);
        Server::getCli()->lightYellow($this->currentTime() . " ALERT: $log $context");
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
        $context = $this->formatContext($context);
        Server::getCli()->darkGray($this->currentTime() . " EMERGENCY: $log $context");
    }
}
