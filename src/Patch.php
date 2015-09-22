<?php

namespace tourze\Server;

use tourze\Base\Helper\Arr;

/**
 * 一些针对php原生方法的patch
 *
 * @package tourze\Server
 */
class Patch
{

    /**
     * @var array
     */
    public static $shutdownFunctions = [];

    /**
     * 自定义register_shutdown_function
     *
     * @return bool
     */
    public static function registerShutdownFunction()
    {
        $args = func_get_args();
        $callback = array_shift($args);
        if ( ! is_callable($callback))
        {
            return false;
        }

        self::$shutdownFunctions[] = [
            'callback' => $callback,
            'params'   => $args,
        ];
        return true;
    }

    /**
     * 实现register_shutdown_function
     */
    public static function applyShutdownFunction()
    {
        foreach (self::$shutdownFunctions as $pair)
        {
            call_user_func_array(Arr::get($pair, 'callback'), Arr::get($pair, 'params'));
        }
    }

}
