<?php

namespace tourze\Server\Patch {

    /**
     * 自定义register_shutdown_function
     */
    function register_shutdown_function()
    {
        call_user_func_array('\tourze\Server\Patch::registerShutdownFunction', func_get_args());
    }

}
