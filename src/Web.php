<?php

namespace tourze\Server;

use tourze\Base\Base;
use tourze\Base\Helper\Arr;
use Workerman\Connection\TcpConnection;
use Workerman\WebServer;

/**
 * 继承原有的服务器类
 *
 * @package tourze\Server
 */
class Web extends WebServer
{

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
        // 请求的文件
        $urlInfo = parse_url($_SERVER['REQUEST_URI']);
        if ( ! $urlInfo)
        {
            Base::getHttp()->header('HTTP/1.1 400 Bad Request');
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
        $_SERVER['PATH_INFO'] = '';
        if ( ! is_file($file) && $this->rewrite)
        {
            $_SERVER['PATH_INFO'] = $file;
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
                return $connection->close('<h1>400 Bad Request</h1>');
            }

            $file = realpath($file);

            // 如果请求的是php文件
            if ($extension === 'php')
            {
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
                catch (\Exception $e)
                {
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
