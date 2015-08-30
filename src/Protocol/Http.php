<?php

namespace tourze\Server\Protocol;

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
     * 编码，增加HTTP头
     * 覆盖原方法，自定义Server段
     *
     * @param string $content
     * @param TcpConnection $connection
     * @return string
     */
    public static function encode($content, TcpConnection $connection)
    {
        // 没有http-code默认给个
        if(!isset(HttpCache::$header['Http-Code']))
        {
            $header = "HTTP/1.1 200 OK\r\n";
        }
        else
        {
            $header = HttpCache::$header['Http-Code']."\r\n";
            unset(HttpCache::$header['Http-Code']);
        }

        // Content-Type
        if(!isset(HttpCache::$header['Content-Type']))
        {
            $header .= "Content-Type: text/html;charset=utf-8\r\n";
        }

        // other headers
        foreach(HttpCache::$header as $key=>$item)
        {
            if('Set-Cookie' === $key && is_array($item))
            {
                foreach($item as $it)
                {
                    $header .= $it."\r\n";
                }
            }
            else
            {
                $header .= $item."\r\n";
            }
        }

        // header
        $header .= "Server: TourzeServer/3.0\r\nContent-Length: ".strlen($content)."\r\n\r\n";

        // save session
        self::sessionWriteClose();

        // the whole http package
        return $header.$content;
    }

}
