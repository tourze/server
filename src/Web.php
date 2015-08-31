<?php

namespace tourze\Server;

use Exception;
use tourze\Base\Base;
use tourze\Base\Helper\Arr;
use Workerman\Connection\TcpConnection;
use Workerman\Events\EventInterface;
use Workerman\Protocols\HttpCache;
use Workerman\WebServer;

/**
 * 继承原有的服务器类
 *
 * @package tourze\Server
 */
class Web extends WebServer
{

    public static $sessionName = 'TSESSION';

    /**
     * @var string 缺省文件
     */
    protected $indexFile = 'index.php';

    /**
     * @var bool|string 是否开启伪静态，如果该选项为字符串，则托管给该地址处理
     */
    protected $rewrite = false;

    /**
     * @var string 默认错误页
     */
    protected $errorFile = 'error.php';

    /**
     * @var string 可指定协议处理的类
     */
    protected $protocolClass = '';

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

    /**
     * 监听端口
     *
     * @throws Exception
     */
    public function listen()
    {
        if ( ! $this->_socketName)
        {
            return;
        }
        // 获得应用层通讯协议以及监听的地址
        list($scheme, $address) = explode(':', $this->_socketName, 2);
        // 如果有指定应用层协议，则检查对应的协议类是否存在
        if ($scheme != 'tcp' && $scheme != 'udp')
        {
            // 判断是否有自定义协议
            if (isset(Worker::$protocolMapping[$scheme]) && class_exists(Worker::$protocolMapping[$scheme]))
            {
                $this->_protocol = Worker::$protocolMapping[$scheme];
            }
            elseif ($this->protocolClass && class_exists($this->protocolClass))
            {
                $this->_protocol = $this->protocolClass;
            }
            else
            {
                $scheme = ucfirst($scheme);
                $this->_protocol = '\\Protocols\\' . $scheme;
                if ( ! class_exists($this->_protocol))
                {
                    $this->_protocol = "\\Workerman\\Protocols\\$scheme";
                    if ( ! class_exists($this->_protocol))
                    {
                        throw new Exception("class \\Protocols\\$scheme not exist");
                    }
                }
            }
        }
        elseif ($scheme === 'udp')
        {
            $this->transport = 'udp';
        }

        // flag
        $flags = $this->transport === 'udp' ? STREAM_SERVER_BIND : STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
        $errNo = 0;
        $errmsg = '';
        $this->_mainSocket = stream_socket_server($this->transport . ":" . $address, $errNo, $errmsg, $flags, $this->_context);
        if ( ! $this->_mainSocket)
        {
            throw new Exception($errmsg);
        }

        // 尝试打开tcp的keepalive，关闭TCP Nagle算法
        if (function_exists('socket_import_stream'))
        {
            $socket = socket_import_stream($this->_mainSocket);
            @socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
            @socket_set_option($socket, SOL_SOCKET, TCP_NODELAY, 1);
        }

        // 设置非阻塞
        stream_set_blocking($this->_mainSocket, 0);

        // 放到全局事件轮询中监听_mainSocket可读事件（客户端连接事件）
        if (self::$globalEvent)
        {
            if ($this->transport !== 'udp')
            {
                self::$globalEvent->add($this->_mainSocket, EventInterface::EV_READ, [$this, 'acceptConnection']);
            }
            else
            {
                self::$globalEvent->add($this->_mainSocket, EventInterface::EV_READ, [$this, 'acceptUdpConnection']);
            }
        }
    }

    /**
     * 进程启动的时候一些初始化工作
     *
     * @throws \Exception
     */
    public function onWorkerStart()
    {
        if (empty($this->serverRoot))
        {
            throw new \Exception('server root not set, please use WebServer::addRoot($domain, $root_path) to set server root path');
        }

        // 初始化HttpCache
        HttpCache::init();
        session_name(self::$sessionName);
        HttpCache::$sessionName = self::$sessionName;

        // 初始化mimeMap
        $this->initMimeTypeMap();

        // 尝试执行开发者设定的onWorkerStart回调
        if ($this->_onWorkerStart)
        {
            call_user_func($this->_onWorkerStart, $this);
        }
    }

    /**
     * 覆盖原workerman流程，实现更多功能
     * 当接收到完整的http请求后的处理逻辑
     *
     * 1、如果请求的是以php为后缀的文件，则尝试加载
     * 2、如果请求的url没有后缀，则尝试加载对应目录的index.php
     * 3、如果请求的是非php为后缀的文件，尝试读取原始数据并发送
     * 4、如果请求的文件不存在，则返回404
     *
     * @param TcpConnection $connection
     * @param mixed         $data
     * @return mixed
     */
    public function onMessage($connection, $data)
    {
        Base::getLog()->info(__METHOD__ . ' receive http request', [
            'uri'  => $_SERVER['REQUEST_URI'],
            'ip'   => $connection->getRemoteIp(),
            'port' => $connection->getRemotePort(),
        ]);

        // 请求的文件
        $urlInfo = parse_url($_SERVER['REQUEST_URI']);
        if ( ! $urlInfo)
        {
            Base::getHttp()->header('HTTP/1.1 400 Bad Request');
            Base::getLog()->warning(__METHOD__ . ' receive bad request', [
                'uri'  => $_SERVER['REQUEST_URI'],
                'ip'   => $connection->getRemoteIp(),
                'port' => $connection->getRemotePort(),
            ]);
            return $connection->close('<h1>400 Bad Request</h1>');
        }

        $path = $urlInfo['path'];

        $pathInfo = pathinfo($path);
        $extension = isset($pathInfo['extension']) ? $pathInfo['extension'] : '';
        if ($extension === '')
        {
            $path = ($len = strlen($path)) && $path[$len - 1] === '/'
                ? $path . $this->indexFile
                : $path . '/' . $this->indexFile;
            $extension = 'php';
        }

        $rootDir = isset($this->serverRoot[$_SERVER['HTTP_HOST']])
            ? $this->serverRoot[$_SERVER['HTTP_HOST']]
            : current($this->serverRoot);

        $file = "$rootDir/$path";

        // 对应的php文件不存在，而且支持rewrite
        if ( ! is_file($file) && $this->rewrite)
        {
            $file = is_string($this->rewrite)
                ? $rootDir . '/' . $this->rewrite
                : $rootDir . '/' . $this->indexFile;
            $extension = 'php';
        }

        // 请求的文件存在
        if (is_file($file))
        {
            // 判断是否是站点目录里的文件
            if (( ! ($requestRealPath = realpath($file)) || ! ($rootDirRealPath = realpath($rootDir))) || 0 !== strpos($requestRealPath, $rootDirRealPath))
            {
                Base::getHttp()->header('HTTP/1.1 400 Bad Request');
                Base::getLog()->warning(__METHOD__ . ' receive bad request', [
                    'uri'  => $_SERVER['REQUEST_URI'],
                    'ip'   => $connection->getRemoteIp(),
                    'port' => $connection->getRemotePort(),
                ]);
                return $connection->close('<h1>400 Bad Request</h1>');
            }

            $file = realpath($file);

            // 如果请求的是php文件
            if ($extension === 'php')
            {
                Base::getLog()->info(__METHOD__ . ' handle request', [
                    'uri'  => $_SERVER['REQUEST_URI'],
                    'ip'   => $connection->getRemoteIp(),
                    'port' => $connection->getRemotePort(),
                    'file' => $file,
                ]);
                Base::cleanComponents();
                $cwd = getcwd();
                chdir($rootDir);
                ini_set('display_errors', 'off');
                // 缓冲输出
                ob_start();
                // 载入php文件
                try
                {
                    // $_SERVER变量
                    $_SERVER['REMOTE_ADDR'] = $connection->getRemoteIp();
                    $_SERVER['REMOTE_PORT'] = $connection->getRemotePort();
                    include $file;
                }
                catch (Exception $e)
                {
                    Base::getLog()->error($e->getMessage(), [
                        'code' => $e->getCode(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                    // 如果不是exit
                    if ($e->getMessage() != 'jump_exit')
                    {
                        echo $e;
                    }
                }
                $content = ob_get_clean();
                ini_set('display_errors', 'on');
                $connection->close($content);
                chdir($cwd);
                return;
            }

            // 请求的是静态资源文件
            if (isset(self::$mimeTypeMap[$extension]))
            {
                Base::getHttp()->header('Content-Type: ' . self::$mimeTypeMap[$extension]);
            }
            else
            {
                Base::getHttp()->header('Content-Type: ' . self::$defaultMimeType);
            }

            // 获取文件信息
            $info = stat($file);

            $modified_time = $info ? date('D, d M Y H:i:s', $info['mtime']) . ' GMT' : '';

            // 如果有$_SERVER['HTTP_IF_MODIFIED_SINCE']
            if ( ! empty($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $info)
            {
                // 文件没有更改则直接304
                if ($modified_time === $_SERVER['HTTP_IF_MODIFIED_SINCE'])
                {
                    // 304
                    Base::getHttp()->header('HTTP/1.1 304 Not Modified');
                    // 发送给客户端
                    return $connection->close('');
                }
            }

            if ($modified_time)
            {
                Base::getHttp()->header("Last-Modified: $modified_time");
            }
            // 发送给客户端
            return $connection->close(file_get_contents($file));
        }
        else
        {
            // 404
            Base::getHttp()->header("HTTP/1.1 404 Not Found");
            return $connection->close('<html><head><title>404 页面不存在</title></head><body><center><h3>404 Not Found</h3></center></body></html>');
        }
    }
}
