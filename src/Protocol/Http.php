<?php

namespace tourze\Server\Protocol;

use tourze\Base\Base;
use tourze\Base\Helper\Text as TextHelper;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http as WorkerHttp;
use Workerman\Protocols\HttpCache;

/**
 * 继承原有的http协议
 *
 * @package tourze\Server\Protocol
 */
class Http extends WorkerHttp
{

    /**
     * 扩展原先的session_start函数：
     *
     * 1. 使session_id更加符合原生的规则
     *
     * @return bool
     */
    public static function sessionStart()
    {
        if (PHP_SAPI != 'cli')
        {
            return session_start();
        }
        if (HttpCache::$instance->sessionStarted)
        {
            Base::getLog()->notice('already sessionStarted');
            return true;
        }
        HttpCache::$instance->sessionStarted = true;

        // 如果没有session，那就分配一个session
        if ( ! isset($_COOKIE[HttpCache::$sessionName]) || ! is_file(HttpCache::$sessionPath . '/ses' . $_COOKIE[HttpCache::$sessionName]))
        {
            $sessionID = strtolower(TextHelper::random(20, 'qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM1234567890'));
            $fileName = HttpCache::$sessionPath . '/ses' . $sessionID;
            if ( ! $fileName)
            {
                return false;
            }
            HttpCache::$instance->sessionFile = $fileName;
            return self::setcookie(
                HttpCache::$sessionName
                , $sessionID
                , ini_get('session.cookie_lifetime')
                , ini_get('session.cookie_path')
                , ini_get('session.cookie_domain')
                , ini_get('session.cookie_secure')
                , ini_get('session.cookie_httponly')
            );
        }
        if ( ! HttpCache::$instance->sessionFile)
        {
            HttpCache::$instance->sessionFile = HttpCache::$sessionPath . '/ses' . $_COOKIE[HttpCache::$sessionName];
        }
        // 有sid则打开文件，读取session值
        if (HttpCache::$instance->sessionFile)
        {
            $raw = file_get_contents(HttpCache::$instance->sessionFile);
            if ($raw)
            {
                session_decode($raw);
            }
        }
    }

    /**
     * 编码，增加HTTP头
     * 覆盖原方法，自定义Server段
     *
     * @param string        $content
     * @param TcpConnection $connection
     * @return string
     */
    public static function encode($content, TcpConnection $connection)
    {
        // 没有http-code默认给个
        if ( ! isset(HttpCache::$header['Http-Code']))
        {
            $header = "HTTP/1.1 200 OK\r\n";
        }
        else
        {
            $header = HttpCache::$header['Http-Code'] . "\r\n";
            unset(HttpCache::$header['Http-Code']);
        }

        // Content-Type
        if ( ! isset(HttpCache::$header['Content-Type']))
        {
            $header .= "Content-Type: text/html;charset=utf-8\r\n";
        }

        // other headers
        foreach (HttpCache::$header as $key => $item)
        {
            if ('Set-Cookie' === $key && is_array($item))
            {
                foreach ($item as $it)
                {
                    $header .= $it . "\r\n";
                }
            }
            else
            {
                $header .= $item . "\r\n";
            }
        }

        // header
        $header .= "Server: tourze/" . Base::VERSION . "\r\nContent-Length: " . strlen($content) . "\r\n\r\n";

        // save session
        self::sessionWriteClose();

        // the whole http package
        return $header . $content;
    }

}
