<?php

namespace tourze\Server;

use tourze\Base\Base;

/**
 * Class Server
 *
 * @package tourze\Server
 */
class Server extends Base
{

    /**
     * @return \tourze\Server\Component\Cli
     * @throws \tourze\Base\Exception\ComponentNotFoundException
     */
    public static function getCli()
    {
        return self::get('serverCli');
    }

}
