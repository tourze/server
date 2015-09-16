<?php

use tourze\Base\Base;

Base::getHttp()->setCookie(date('Y-m-d-H-i-s'), microtime(true));

?>
<pre><?php print_r($_COOKIE) ?></pre>

<?php
