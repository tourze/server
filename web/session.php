<?php

use tourze\Base\Base;

Base::getHttp()->header('Content-Type: text/html');

$str = date('Y-m-d H:i:s');
Base::getSession()->set(date('Y-m-d-H-i'), $str);

echo Base::getSession()->get(date('Y-m-d-H-i'));
echo "<br/>";
echo Base::getSession()->id();
echo "<pre>";
var_dump($_SESSION);
echo "</pre>";

//\tourze\Base\Base::getSession()->destroy();

//Base::getHttp()->end('end.');
