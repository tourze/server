<?php

namespace tourze\Server;

use tourze\Base\Helper\Arr;
use Workerman\WebServer;

/**
 * 继承原有的服务器类
 *
 * @package tourze\Server
 */
class Web extends WebServer
{

    /**
     * 修改原构造方法
     *
     * @param array $config
     */
    public function __construct($config)
    {
        parent::__construct(Arr::get($config, 'socketName'), Arr::get($config, 'contextOptions'));

        // 设置数据
        foreach ($config as $k => $v)
        {
            if (isset($this->$k))
            {
                $this->$k = $v;
            }
        }

        $siteList = Arr::get($config, 'siteList');
        foreach ($siteList as $domain => $path)
        {
            $this->addRoot($domain, $path);
        }
    }

}
