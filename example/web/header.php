<?php

foreach ($_GET as $k => $v)
{
    \tourze\Base\Base::getHttp()->header($v);
}

echo time();
