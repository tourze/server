<?php

use tourze\Base\Base;

Base::getHttp()->header('Content-Type: application/json');

tourze\Server\Patch\register_shutdown_function(function () {
    echo microtime(true);
});
