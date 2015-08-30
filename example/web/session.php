<?php

\tourze\Base\Base::getHttp()->sessionStart();

var_dump($_SESSION);

$_SESSION[date('Y-m-d H:i')] = date('Y-m-d H:i:s');

echo \tourze\Base\Base::getHttp()->sessionID();
