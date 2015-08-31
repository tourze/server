<?php

\tourze\Base\Base::reload('http');

$str = date('Y-m-d H:i:s');
\tourze\Base\Base::getSession()->set(date('Y-m-d-H-i'), $str);

echo \tourze\Base\Base::getSession()->get(date('Y-m-d-H-i'));
echo "<br/>";
echo "<pre>";
var_dump($_SESSION);
echo "</pre>";

\tourze\Base\Base::getSession()->destroy();

\tourze\Base\Base::getHttp()->end('end.');
