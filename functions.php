<?php

namespace tourze\Server\Patch {

    use tourze\Base\Base;

    if ( ! function_exists('tourze\Server\Patch\header'))
    {
        /**
         * header函数的补丁
         *
         * @param string    $string
         * @param bool|true $replace
         * @param null      $httpResponseCode
         */
        function header($string, $replace = true, $httpResponseCode = null)
        {
            Base::getHttp()->header($string, $replace, $httpResponseCode);
        }
    }

    if ( ! function_exists('tourze\Server\Patch\register_shutdown_function'))
    {
        /**
         * 自定义register_shutdown_function
         */
        function register_shutdown_function()
        {
            call_user_func_array('\tourze\Server\Patch::registerShutdownFunction', func_get_args());
        }
    }
}
