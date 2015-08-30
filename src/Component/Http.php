<?php

namespace tourze\Server\Component;

use tourze\Base\Component\Http as BaseHttp;
use tourze\Server\Protocol\Http as HttpProtocol;
use Workerman\Protocols\HttpCache;

/**
 * 适用于workerman规则的http组件
 *
 * @package tourze\Server\Component
 */
class Http extends BaseHttp
{

    /**
     * @inheritdoc
     */
    public function end($msg = '')
    {
        HttpProtocol::end($msg);
    }

    /**
     * @inheritdoc
     */
    public function setCookie($name, $value = '', $maxAge = 0, $path = '', $domain = '', $secure = false, $httpOnly = false)
    {
        return HttpProtocol::setcookie($name, $value, $maxAge, $path, $domain, $secure, $httpOnly);
    }

    /**
     * @inheritdoc
     */
    public function sessionStart()
    {
        return HttpProtocol::sessionStart();
    }

    /**
     * 下面要修改成workerman的逻辑
     *
     * @inheritdoc
     */
    public function sessionID($id = null)
    {
        return session_id($id);
    }

    /**
     * 下面要修改成workerman的逻辑
     *
     * @inheritdoc
     */
    public function sessionRegenerateID($deleteOldSession = false)
    {
        return session_regenerate_id($deleteOldSession);
    }

    /**
     * @inheritdoc
     */
    public function sessionWriteClose()
    {
        HttpProtocol::sessionWriteClose();
    }

    /**
     * @inheritdoc
     */
    public function header($string, $replace = true, $httpResponseCode = null)
    {
        return HttpProtocol::header($string, $replace, $httpResponseCode);
    }

    /**
     * @inheritdoc
     */
    public function headerRemove($name = null)
    {
        HttpProtocol::headerRemove($name);
    }

    /**
     * @inheritdoc
     */
    public function headersList()
    {
        return HttpCache::$header;
    }
}
