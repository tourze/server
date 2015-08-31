<?php

namespace tourze\Server\Protocol;

use tourze\Base\Base;
use tourze\Base\Helper\Arr;
use tourze\Base\Helper\File as FileHelper;
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
     * 从cookie中读取当前session_id
     *
     * @return string
     */
    public static function getSessionID()
    {
        return Arr::get($_COOKIE, HttpCache::$sessionName, '');
    }

    /**
     * 生成一个随机会话ID
     *
     * @return string
     */
    public static function generateSession()
    {
        $sessionID = TextHelper::random(20, 'qwertyuiopasdfghjklzxcvbnm1234567890');
        return $sessionID;
    }

    /**
     * 生成一个会话文件
     *
     * @param string $sessionID
     * @return string
     */
    public static function getSessionFile($sessionID)
    {
        $fileName = HttpCache::$sessionPath . '/tourze_session_' . $sessionID;

        if ( ! FileHelper::exists($fileName))
        {
            FileHelper::touch($fileName);
        }
        return $fileName;
    }

    /**
     * 扩展原先的session_start函数，使session_id更加符合原生的规则
     *
     * @todo 使用PHP原生的session来解决这部分，以完成其他复杂功能
     *
     * @return bool
     */
    public static function sessionStart()
    {
        Base::getLog()->info(__METHOD__ . ' call session stared.', [
            'ip' => Arr::get($_SERVER, 'REMOTE_ADDR')
        ]);
        if (PHP_SAPI != 'cli')
        {
            return session_start();
        }

        // 如果没sessionID，那就分配一个
        if ( ! $sessionID = self::getSessionID())
        {
            $sessionID = self::generateSession();
            Base::getLog()->info(__METHOD__ . ' dispatch session id.', [
                'id' => $sessionID
            ]);
            $_COOKIE[HttpCache::$sessionName] = $sessionID;

            self::setcookie(
                HttpCache::$sessionName
                , $sessionID
                , ini_get('session.cookie_lifetime')
                , ini_get('session.cookie_path')
                , ini_get('session.cookie_domain')
                , ini_get('session.cookie_secure')
                , ini_get('session.cookie_httponly')
            );
        }
        session_id($sessionID);

        // 读取文件
        $fileName = self::getSessionFile($sessionID);
        Base::getLog()->info(__METHOD__ . ' read session file.', [
            'file' => $fileName
        ]);
        $raw = file_get_contents($fileName);
        if ($raw)
        {
            $_SESSION = (array) json_decode($raw, true);
        }

        return true;
    }

    /**
     * 保存session
     *
     * @return bool
     */
    public static function sessionWriteClose()
    {
        if (PHP_SAPI != 'cli')
        {
            session_write_close();
            return true;
        }
        if ( ! empty($_SESSION))
        {
            $sessionStr = json_encode($_SESSION);
            if ($sessionStr)
            {
                $sessionID = self::getSessionID();
                $fileName = self::getSessionFile($sessionID);
                return file_put_contents($fileName, $sessionStr);
            }
        }
        return empty($_SESSION);
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
