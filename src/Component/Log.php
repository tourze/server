<?php

namespace tourze\Server\Component;

use League\CLImate\CLImate;
use tourze\Base\Component\Log as BaseLog;

/**
 * Workerman架构下的日志组件
 *
 * @property string timeFormat
 * @package tourze\Server\Component
 */
class Log extends BaseLog
{

    /**
     * @var CLImate
     */
    public $climate;

    /**
     * @var string
     */
    protected $_timeFormat = 'e Y-m-d H:i:s:u';

    /**
     * @return string
     */
    public function getTimeFormat()
    {
        return $this->_timeFormat;
    }

    /**
     * @param string $timeFormat
     */
    public function setTimeFormat($timeFormat)
    {
        $this->_timeFormat = $timeFormat;
    }

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
     * @inheritdoc
     */
    public function debug($log, array $context = [])
    {
        $context = stripslashes(json_encode($context, JSON_UNESCAPED_UNICODE));
        $this->climate->yellow(date($this->timeFormat) . " DEBUG: $log [$context]");
    }

    /**
     * @inheritdoc
     */
    public function info($log, array $context = [])
    {
        $context = stripslashes(json_encode($context, JSON_UNESCAPED_UNICODE));
        $this->climate->white(date($this->timeFormat) . " INFO: $log [$context]");
    }

    /**
     * @inheritdoc
     */
    public function notice($log, array $context = [])
    {
        $context = stripslashes(json_encode($context, JSON_UNESCAPED_UNICODE));
        $this->climate->blue(date($this->timeFormat) . " NOTICE: $log [$context]");
    }

    /**
     * @inheritdoc
     */
    public function warning($log, array $context = [])
    {
        $context = stripslashes(json_encode($context, JSON_UNESCAPED_UNICODE));
        $this->climate->red(date($this->timeFormat) . " WARNING: $log [$context]");
    }

    /**
     * @inheritdoc
     */
    public function error($log, array $context = [])
    {
        $context = stripslashes(json_encode($context, JSON_UNESCAPED_UNICODE));
        $this->climate->lightRed(date($this->timeFormat) . " ERROR: $log [$context]");
    }

    /**
     * @inheritdoc
     */
    public function critical($log, array $context = [])
    {
        $context = stripslashes(json_encode($context, JSON_UNESCAPED_UNICODE));
        $this->climate->lightBlue(date($this->timeFormat) . " CRITICAL: $log [$context]");
    }

    /**
     * @inheritdoc
     */
    public function alert($log, array $context = [])
    {
        $context = stripslashes(json_encode($context, JSON_UNESCAPED_UNICODE));
        $this->climate->lightYellow(date($this->timeFormat) . " ALERT: $log [$context]");
    }

    /**
     * @inheritdoc
     */
    public function emergency($log, array $context = [])
    {
        $context = stripslashes(json_encode($context, JSON_UNESCAPED_UNICODE));
        $this->climate->darkGray(date($this->timeFormat) . " EMERGENCY: $log [$context]");
    }
}
