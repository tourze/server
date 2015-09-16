<?php

namespace tourze\Server\Component;

use League\CLImate\CLImate;

/**
 * CLI处理类
 *
 * @package tourze\Server\Component
 */
class Cli extends CLImate
{

    /**
     * @var bool 当前组件是否可被初始化，如果不可持久化，在每次系统初始化时，会自动注销
     */
    public $persistence = true;

}
