<?php

namespace tourze\Server;

use Exception;
use tourze\Base\Base;
use tourze\Base\Helper\Arr;
use tourze\Base\Helper\Mime;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\HttpCache;

/**
 * 继承原有的服务器类
 *
 * @package tourze\Server
 */
class Web extends Worker
{

    /**
     * @var string 默认mime类型
     */
    protected static $defaultMimeType = 'text/html; charset=utf-8';

    /**
     * @var string 默认会话使用的session名称
     */
    public static $sessionName = 'TSESSION';

    /**
     * mime类型映射关系
     *
     * @var array
     */
    protected static $mimeTypeMap = [];

    /**
     * 用来保存用户设置的onWorkerStart回调
     *
     * @var callback
     */
    protected $_onWorkerStart = null;

    /**
     * 服务器名到文件路径的转换
     *
     * @var array ['workerman.net'=>'/home', 'www.workerman.net'=>'home/www']
     */
    protected $serverRoot = [];

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
        parent::__construct($config);

        $siteList = Arr::get($config, 'siteList');
        foreach ($siteList as $domain => $path)
        {
            $this->addRoot($domain, $path);
        }
    }

    /**
     * 添加站点域名与站点目录的对应关系，类似nginx的
     *
     * @param string $domain
     * @param string $root_path
     * @return void
     */
    public function addRoot($domain, $root_path)
    {
        $this->serverRoot[$domain] = $root_path;
    }

    /**
     * 运行
     *
     * @see Workerman.Worker::run()
     */
    public function run()
    {
        $this->_onWorkerStart = $this->onWorkerStart;
        $this->onWorkerStart = [$this, 'onWorkerStart'];
        $this->onMessage = [$this, 'onMessage'];
        parent::run();
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
            throw new Exception('server root not set, please use WebServer::addRoot($domain, $root_path) to set server root path');
        }

        // 初始化HttpCache
        HttpCache::init();
        session_name(self::$sessionName);
        HttpCache::$sessionName = self::$sessionName;

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
            Base::getHttp()->header('Content-Type: ' . Mime::getMimeFromExtension($extension, self::$defaultMimeType));

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
