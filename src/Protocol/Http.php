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
 * 继承原有的http协议，主要是更改了会话的逻辑
 *
 * @todo    回收过期会话
 * @package tourze\Server\Protocol
 */
class Http extends WorkerHttp
{

    /**
     * 设置cookie
     *
     * @param string  $name
     * @param string  $value
     * @param integer $maxage
     * @param string  $path
     * @param string  $domain
     * @param bool    $secure
     * @param bool    $HTTPOnly
     * @return bool
     */
    public static function setCookie($name, $value = '', $maxage = 0, $path = '', $domain = '', $secure = false, $HTTPOnly = false)
    {
        if (PHP_SAPI != 'cli')
        {
            return setcookie($name, $value, $maxage, $path, $domain, $secure, $HTTPOnly);
        }

        HttpCache::$header[] = 'Set-Cookie: ' . $name . '=' . rawurlencode($value)
            . (empty($domain) ? '' : '; Domain=' . $domain)
            . (empty($maxage) ? '' : '; Max-Age=' . $maxage)
            . (empty($path) ? '' : '; Path=' . $path)
            . (! $secure ? '' : '; Secure')
            . (! $HTTPOnly ? '' : '; HttpOnly');
        return true;
    }

    /**
     * 从cookie中读取当前session_id
     *
     * @return string
     */
    public static function getSessionID()
    {
        $id = Arr::get($_COOKIE, HttpCache::$sessionName, '');
        Base::getLog()->debug(__METHOD__ . ' get session id', [
            'id' => $id,
        ]);
        return $id;
    }

    /**
     * 生成一个随机会话ID
     *
     * @return string
     */
    public static function generateSession()
    {
        $sessionID = TextHelper::random(20, 'qwertyuiopasdfghjklzxcvbnm1234567890');
        Base::getLog()->debug(__METHOD__ . ' generate session id', [
            'id' => $sessionID,
        ]);
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
        Base::getLog()->debug(__METHOD__ . ' get session file', [
            'file' => $fileName,
        ]);

        if ( ! FileHelper::exists($fileName))
        {
            Base::getLog()->notice(__METHOD__ . ' session file not found, create it', [
                'file' => $fileName,
            ]);
            FileHelper::touch($fileName);
        }
        return $fileName;
    }

    /**
     * 扩展原先的session_start函数，使session_id更加符合原生的规则
     *
     * @return bool
     */
    public static function sessionStart()
    {
        Base::getLog()->debug(__METHOD__ . ' call session stared.', [
            'ip' => Arr::get($_SERVER, 'REMOTE_ADDR'),
        ]);
        if (PHP_SAPI != 'cli')
        {
            return session_start();
        }

        // 如果没sessionID，那就分配一个
        if ( ! $sessionID = self::getSessionID())
        {
            $sessionID = self::generateSession();
            Base::getLog()->debug(__METHOD__ . ' dispatch new session id.', [
                'id' => $sessionID,
            ]);
            $_COOKIE[HttpCache::$sessionName] = $sessionID;

            Base::getLog()->debug(__METHOD__ . ' save session id to cookie', [
                'cookie' => HttpCache::$sessionName,
                'id'     => $sessionID,
            ]);
            self::setCookie(
                HttpCache::$sessionName,
                $sessionID,
                ini_get('session.cookie_lifetime'),
                ini_get('session.cookie_path'),
                ini_get('session.cookie_domain'),
                ini_get('session.cookie_secure'),
                ini_get('session.cookie_httponly')
            );
        }
        session_id($sessionID);

        // 读取文件
        $fileName = self::getSessionFile($sessionID);
        Base::getLog()->debug(__METHOD__ . ' read session file.', [
            'file' => $fileName,
        ]);
        $raw = file_get_contents($fileName);
        if ($raw)
        {
            $_SESSION = (array) json_decode($raw, true);
            Base::getLog()->debug(__METHOD__ . ' set $_SESSION');
        }
        else
        {
            Base::getLog()->warning(__METHOD__ . ' session file not found', [
                'file' => $fileName,
            ]);
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
        Base::getLog()->debug(__METHOD__ . ' call session write close');
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
                Base::getLog()->debug(__METHOD__ . ' save session data to file', [
                    'file' => $fileName,
                ]);
                return file_put_contents($fileName, $sessionStr);
            }
        }
        else
        {
            Base::getLog()->notice(__METHOD__ . ' session date is empty');
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
        $headers = HttpCache::$header;

        Base::getLog()->debug(__METHOD__ . ' encode header and send', [
            'headers' => $headers,
        ]);

        $sendHeader = [];

        // 没有http-code默认给个，content-type也是
        $hasStatus = false;
        $hasContentType = false;
        foreach ($headers as $header)
        {
            if (substr($header, 0, strlen('HTTP/')) == 'HTTP/')
            {
                $hasStatus = true;
                $sendHeader[] = $header;
                Base::getLog()->debug(__METHOD__ . ' custom http code', [
                    'header' => $header,
                ]);
            }
            elseif (strtolower(substr($header, 0, strlen('content-type:'))) == 'content-type:')
            {
                $hasContentType = true;
            }
        }
        if ( ! $hasStatus)
        {
            $sendHeader[] = "HTTP/1.1 200 OK";
            Base::getLog()->debug(__METHOD__ . ' default http code');
        }
        if ( ! $hasContentType)
        {
            Base::getLog()->debug(__METHOD__ . ' missing content type, set default value');
            $sendHeader[] = 'Content-Type: text/html;charset=utf-8';
        }

        // 合并
        $sendHeader = Arr::merge($sendHeader, $headers);

        // 一些额外的header信息
        if (Base::$expose)
        {
            $sendHeader[] = "Server: tourze/" . Base::VERSION;
        }
        $sendHeader[] = "Content-Length: " . strlen($content);

        // 记录要输出的header

        Base::getLog()->debug(__METHOD__ . ' final send header', $sendHeader);
        $sendHeader = implode("\r\n", $sendHeader);
        $sendHeader .= "\r\n\r\n";

        // save session
        self::sessionWriteClose();

        HttpCache::$header = [];
        // the whole http package
        return $sendHeader . $content;
    }

    /**
     * 从http数据包中解析$_POST、$_GET、$_COOKIE等
     *
     * @param string        $recv_buffer
     * @param TcpConnection $connection
     * @return array
     */
    public static function decode($recv_buffer, TcpConnection $connection)
    {
        // 初始化
        Base::getLog()->debug(__METHOD__ . ' clean global variables');
        $_POST = $_GET = $_COOKIE = $_REQUEST = $_SESSION = $_FILES = [];
        $GLOBALS['HTTP_RAW_POST_DATA'] = '';
        $_SERVER = [
            'QUERY_STRING'         => '',
            'REQUEST_METHOD'       => '',
            'REQUEST_URI'          => '',
            'SERVER_PROTOCOL'      => '',
            'SERVER_SOFTWARE'      => 'tourze/' . Base::VERSION,
            'SERVER_NAME'          => '',
            'HTTP_HOST'            => '',
            'HTTP_USER_AGENT'      => '',
            'HTTP_ACCEPT'          => '',
            'HTTP_ACCEPT_LANGUAGE' => '',
            'HTTP_ACCEPT_ENCODING' => '',
            'HTTP_COOKIE'          => '',
            'HTTP_CONNECTION'      => '',
            'REMOTE_ADDR'          => '',
            'REMOTE_PORT'          => '0',
        ];

        Base::getLog()->debug(__METHOD__ . ' clean previous headers');
        // 清空上次的数据
        HttpCache::$header = ['Connection: keep-alive'];
        HttpCache::$instance = new HttpCache();

        // 将header分割成数组
        list($httpHeader, $httpBody) = explode("\r\n\r\n", $recv_buffer, 2);
        $headerData = explode("\r\n", $httpHeader);

        // 第一行为比较重要的一行
        $firstLine = array_shift($headerData);
        list($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_SERVER['SERVER_PROTOCOL']) = explode(' ', $firstLine);
        Base::getLog()->debug(__METHOD__ . ' receive http request', [
            'method'   => $_SERVER['REQUEST_METHOD'],
            'uri'      => $_SERVER['REQUEST_URI'],
            'protocol' => $_SERVER['SERVER_PROTOCOL'],
        ]);

        $httpPostBoundary = '';
        foreach ($headerData as $content)
        {
            // \r\n\r\n
            if (empty($content))
            {
                continue;
            }
            list($key, $value) = explode(':', $content, 2);
            $key = strtolower($key);
            $value = trim($value);
            switch ($key)
            {
                // HTTP_HOST
                case 'host':
                    $_SERVER['HTTP_HOST'] = $value;
                    $tmp = explode(':', $value);
                    $_SERVER['SERVER_NAME'] = $tmp[0];
                    if (isset($tmp[1]))
                    {
                        $_SERVER['SERVER_PORT'] = $tmp[1];
                    }
                    break;
                // cookie
                case 'cookie':
                    $_SERVER['HTTP_COOKIE'] = $value;
                    parse_str(str_replace('; ', '&', $_SERVER['HTTP_COOKIE']), $_COOKIE);
                    break;
                // user-agent
                case 'user-agent':
                    $_SERVER['HTTP_USER_AGENT'] = $value;
                    break;
                // accept
                case 'accept':
                    $_SERVER['HTTP_ACCEPT'] = $value;
                    break;
                // accept-language
                case 'accept-language':
                    $_SERVER['HTTP_ACCEPT_LANGUAGE'] = $value;
                    break;
                // accept-encoding
                case 'accept-encoding':
                    $_SERVER['HTTP_ACCEPT_ENCODING'] = $value;
                    break;
                // connection
                case 'connection':
                    $_SERVER['HTTP_CONNECTION'] = $value;
                    break;
                case 'referer':
                    $_SERVER['HTTP_REFERER'] = $value;
                    break;
                case 'if-modified-since':
                    $_SERVER['HTTP_IF_MODIFIED_SINCE'] = $value;
                    break;
                case 'if-none-match':
                    $_SERVER['HTTP_IF_NONE_MATCH'] = $value;
                    break;
                case 'content-type':
                    if ( ! preg_match('/boundary="?(\S+)"?/', $value, $match))
                    {
                        $_SERVER['CONTENT_TYPE'] = $value;
                    }
                    else
                    {
                        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data';
                        $httpPostBoundary = '--' . $match[1];
                    }
                    break;
            }
        }

        // 需要解析$_POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST')
        {
            if (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] === 'multipart/form-data')
            {
                self::parseUploadFiles($httpBody, $httpPostBoundary);
            }
            else
            {
                parse_str($httpBody, $_POST);
                $GLOBALS['HTTP_RAW_POST_DATA'] = $httpBody;
            }
        }

        // QUERY_STRING
        $_SERVER['QUERY_STRING'] = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
        if ($_SERVER['QUERY_STRING'])
        {
            // $GET
            parse_str($_SERVER['QUERY_STRING'], $_GET);
        }
        else
        {
            $_SERVER['QUERY_STRING'] = '';
        }

        // REQUEST
        $_REQUEST = array_merge($_GET, $_POST);

        // REMOTE_ADDR REMOTE_PORT
        $_SERVER['REMOTE_ADDR'] = $connection->getRemoteIp();
        $_SERVER['REMOTE_PORT'] = $connection->getRemotePort();

        $result = [
            'get'    => $_GET,
            'post'   => $_POST,
            'cookie' => $_COOKIE,
            'server' => $_SERVER,
            'files'  => $_FILES,
        ];
        Base::getLog()->debug(__METHOD__ . ' get request data', $result);
        return $result;
    }
}
