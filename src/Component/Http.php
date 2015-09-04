<?php

namespace tourze\Server\Component;

use tourze\Base\Base;
use tourze\Base\Component\Http as BaseHttp;
use tourze\Base\Helper\Arr;
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
        return HttpProtocol::setCookie($name, $value, $maxAge, $path, $domain, $secure, $httpOnly);
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
        $current = Arr::get($_COOKIE, HttpCache::$sessionName, '');

        if ($id === null)
        {
            return $current;
        }

        $this->setCookie(HttpCache::$sessionName, $id
            , ini_get('session.cookie_lifetime')
            , ini_get('session.cookie_path')
            , ini_get('session.cookie_domain')
            , ini_get('session.cookie_secure')
            , ini_get('session.cookie_httponly'));
        $_COOKIE[HttpCache::$sessionName] = $id;
        return $current;
    }

    /**
     * 下面要修改成workerman的逻辑
     *
     * @inheritdoc
     */
    public function sessionRegenerateID($deleteOldSession = false)
    {
        unset($_COOKIE[HttpCache::$sessionName]);
        return $this->sessionStart();
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
        Base::getLog()->debug(__METHOD__ . ' add response header', [
            'header'  => $string,
            'replace' => $replace,
        ]);
        return HttpCache::$header[] = $string;
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

    /**
     * @inheritdoc
     */
    public function headersSent(&$file = null, &$line = null)
    {
        Base::getLog()->info(__METHOD__ . ' check if header sent', [
            'file' => $file,
            'line' => $line,
        ]);
        return ! empty(HttpCache::$header);
    }
}
