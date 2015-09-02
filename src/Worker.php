<?php

namespace tourze\Server;

use Exception;
use tourze\Base\Base;
use tourze\Base\Config;
use tourze\Base\Helper\Arr;
use tourze\Server\Exception\BaseException;
use Workerman\Events\EventInterface;
use Workerman\Worker as BaseWorker;

/**
 * 继承原有的Worker基础类
 *
 * @package tourze\Server
 */
class Worker extends BaseWorker
{

    /**
     * @var array
     */
    public static $protocolMapping = [
        'http' => 'tourze\Server\Protocol\Http',
        'text' => 'tourze\Server\Protocol\Text',
    ];

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
            Base::getLog()->error(__METHOD__ . ' missing socket name');
            return;
        }

        // 获得应用层通讯协议以及监听的地址
        $temp = explode(':', $this->_socketName, 2);
        $scheme = Arr::get($temp, 0);
        $address = Arr::get($temp, 1);
        Base::getLog()->info(__METHOD__ . ' fetch socket info', [
            'scheme'  => $scheme,
            'address' => $address,
        ]);

        // 如果有指定应用层协议，则检查对应的协议类是否存在
        if ($scheme != 'tcp' && $scheme != 'udp')
        {
            // 判断是否有自定义协议
            if (isset(self::$protocolMapping[$scheme]) && class_exists(self::$protocolMapping[$scheme]))
            {
                $this->_protocol = self::$protocolMapping[$scheme];
            }
            elseif ($this->protocolClass && class_exists($this->protocolClass))
            {
                $this->_protocol = $this->protocolClass;
            }
            else
            {
                // 没有的话，就按照workerman那套来走
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
        Base::getLog()->info(__METHOD__ . ' set protocol', [
            'class' => $this->_protocol,
        ]);

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
     * 加载指定的配置
     *
     * @param string $name
     * @return bool
     * @throws \tourze\Server\Exception\BaseException
     */
    public static function load($name)
    {
        if ( ! $config = Config::load('main')->get('server.' . $name))
        {
            throw new BaseException('The requested config not found.');
        }

        if ( ! $socketName = Arr::get($config, 'socketName'))
        {
            throw new BaseException('The socket name should not be empty.');
        }

        $config['name'] = $name;
        // 根据socketName来判断，如果是http的话，有单独的处理
        if (substr($socketName, 0, 4) == 'http')
        {
            new Web($config);
        }
        else
        {
            new Worker($config);
        }

        return true;
    }

}
