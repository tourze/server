<?php

if (isset($_GET['header']))
{
    \tourze\Base\Base::getHttp()->header('Time: '.time());
}
