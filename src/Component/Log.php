<?php

namespace tourze\Server\Component;

use League\CLImate\CLImate;
use tourze\Base\Component\Log as BaseLog;

/**
 * Server的日志组件
 *
 * @package tourze\Server\Component
 */
class Log extends BaseLog
{

    /**
     * @var CLImate
     */
    public $climate;

    /**
     * @var string 输出日志的时间格式
     */
    public $timeFormat = 'e Y-m-d H:i:s:u';

    /**
     * @var array 在此列表中的错误级别会被忽略
     */
    public $ignoreLevels = [];

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        if ( ! $this->climate)
        {
            $this->climate = new CLImate;
        }
    }

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
        $this->climate->yellow(date($this->timeFormat) . " DEBUG: $log [$context]");
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
        $this->climate->white(date($this->timeFormat) . " INFO: $log [$context]");
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
        $this->climate->blue(date($this->timeFormat) . " NOTICE: $log [$context]");
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
        $this->climate->red(date($this->timeFormat) . " WARNING: $log [$context]");
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
        $this->climate->lightRed(date($this->timeFormat) . " ERROR: $log [$context]");
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
        $this->climate->lightBlue(date($this->timeFormat) . " CRITICAL: $log [$context]");
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
        $this->climate->lightYellow(date($this->timeFormat) . " ALERT: $log [$context]");
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
        $this->climate->darkGray(date($this->timeFormat) . " EMERGENCY: $log [$context]");
    }
}
