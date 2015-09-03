<?php

namespace tourze\Server\Component;

use League\CLImate\CLImate;
use tourze\Base\Component\Log as BaseLog;

/**
 * Workerman架构下的日志组件
 *
 * @package tourze\Server\Component
 */
class Log extends BaseLog
{

    /**
     * @var CLImate
     */
    public $climate;

    public $timeFormat = 'Y-m-d H:i:s';

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
        $log = date($this->timeFormat) . ' ' . $log;
        $context = json_encode($context, JSON_UNESCAPED_UNICODE);
        $this->climate->yellow("DEBUG: $log [$context]");
    }

    /**
     * @inheritdoc
     */
    public function info($log, array $context = [])
    {
        $log = date($this->timeFormat) . ' ' . $log;
        $context = json_encode($context, JSON_UNESCAPED_UNICODE);
        $this->climate->white("INFO: $log [$context]");
    }

    /**
     * @inheritdoc
     */
    public function notice($log, array $context = [])
    {
        $log = date($this->timeFormat) . ' ' . $log;
        $context = json_encode($context, JSON_UNESCAPED_UNICODE);
        $this->climate->blue("NOTICE: $log [$context]");
    }

    /**
     * @inheritdoc
     */
    public function warning($log, array $context = [])
    {
        $log = date($this->timeFormat) . ' ' . $log;
        $context = json_encode($context, JSON_UNESCAPED_UNICODE);
        $this->climate->red("WARNING: $log [$context]");
    }

    /**
     * @inheritdoc
     */
    public function error($log, array $context = [])
    {
        $log = date($this->timeFormat) . ' ' . $log;
        $context = json_encode($context, JSON_UNESCAPED_UNICODE);
        $this->climate->lightRed("ERROR: $log [$context]");
    }

    /**
     * @inheritdoc
     */
    public function critical($log, array $context = [])
    {
        $log = date($this->timeFormat) . ' ' . $log;
        $context = json_encode($context, JSON_UNESCAPED_UNICODE);
        $this->climate->lightBlue("CRITICAL: $log [$context]");
    }

    /**
     * @inheritdoc
     */
    public function alert($log, array $context = [])
    {
        $log = date($this->timeFormat) . ' ' . $log;
        $context = json_encode($context, JSON_UNESCAPED_UNICODE);
        $this->climate->lightYellow("ALERT: $log [$context]");
    }

    /**
     * @inheritdoc
     */
    public function emergency($log, array $context = [])
    {
        $log = date($this->timeFormat) . ' ' . $log;
        $context = json_encode($context, JSON_UNESCAPED_UNICODE);
        $this->climate->darkGray("EMERGENCY: $log [$context]");
    }
}
