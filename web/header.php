<?php

use tourze\Base\Base;

Base::getHttp()->header('Content-Type: application/json');

echo time();
