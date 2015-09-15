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
        Base::getLog()->debug(__METHOD__ . ' fetch socket info', [
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
        Base::getLog()->debug(__METHOD__ . ' set protocol', [
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

        // 如果有自定义初始化的class名
        if (isset($config['initClass']))
        {
            $class = $config['initClass'];
            unset($config['initClass']);
            new $class($config);
        }
        else
        {
            // 根据socketName来判断，如果是http的话，有单独的处理
            if (substr($socketName, 0, 4) == 'http')
            {
                new Web($config);
            }
            else
            {
                new Worker($config);
            }
        }

        return true;
    }

    /**
     * 打印帮助说明
     *
     * @param string $startFile
     */
    public static function printUsage($startFile)
    {
        Server::getCli()->yellow("\nUsage: php" . $startFile . " [ACTION]\n");
        Server::getCli()->table([
            [
                'ACTION' => 'start',
                'DESC' => 'Start all services',
                'EXAMPLE' => "php $startFile start",
            ],
            [
                'ACTION' => 'stop',
                'DESC' => 'Stop all services',
                'EXAMPLE' => "php $startFile stop",
            ],
            [
                'ACTION' => 'restart',
                'DESC' => '',
                'EXAMPLE' => "php $startFile restart",
            ],
            [
                'ACTION' => 'reload',
                'DESC' => '',
                'EXAMPLE' => "php $startFile reload",
            ],
            [
                'ACTION' => 'status',
                'DESC' => '',
                'EXAMPLE' => "php $startFile status",
            ],
        ]);
    }

    /**
     * 解析运行命令，输出更加好看的格式
     *
     * php start.php start | stop | restart | reload | status
     *
     */
    public static function parseCommand()
    {
        // 检查运行命令的参数
        global $argv;
        $startFile = $argv[0];
        if ( ! isset($argv[1]))
        {
            self::printUsage($startFile);
            exit;
        }

        // 命令
        $command = trim($argv[1]);

        // 子命令，目前只支持-d
        $command2 = isset($argv[2]) ? $argv[2] : '';

        // 记录日志
        $mode = '';
        if ($command === 'start')
        {
            if ($command2 === '-d')
            {
                $mode = 'in DAEMON mode';
            }
            else
            {
                $mode = 'in DEBUG mode';
            }
        }
        self::log("Workerman[$startFile] $command $mode");

        // 检查主进程是否在运行
        $master_pid = @file_get_contents(self::$pidFile);
        $master_is_alive = $master_pid && @posix_kill($master_pid, 0);
        if ($master_is_alive)
        {
            if ($command === 'start')
            {
                self::log("Workerman[$startFile] is running");
            }
        }
        elseif ($command !== 'start' && $command !== 'restart')
        {
            self::log("Workerman[$startFile] not run");
        }

        // 根据命令做相应处理
        switch ($command)
        {
            // 启动 workerman
            case 'start':
                if ($command2 === '-d')
                {
                    Worker::$daemonize = true;
                }
                break;
            // 显示 workerman 运行状态
            case 'status':
                // 尝试删除统计文件，避免脏数据
                if (is_file(self::$_statisticsFile))
                {
                    @unlink(self::$_statisticsFile);
                }
                // 向主进程发送 SIGUSR2 信号 ，然后主进程会向所有子进程发送 SIGUSR2 信号
                // 所有进程收到 SIGUSR2 信号后会向 $_statisticsFile 写入自己的状态
                posix_kill($master_pid, SIGUSR2);
                // 睡眠100毫秒，等待子进程将自己的状态写入$_statisticsFile指定的文件
                usleep(100000);
                // 展示状态
                readfile(self::$_statisticsFile);
                exit(0);
            // 重启 workerman
            case 'restart':
                // 停止 workeran
            case 'stop':
                self::log("Workerman[$startFile] is stoping ...");
                // 想主进程发送SIGINT信号，主进程会向所有子进程发送SIGINT信号
                $master_pid && posix_kill($master_pid, SIGINT);
                // 如果 $timeout 秒后主进程没有退出则展示失败界面
                $timeout = 5;
                $start_time = time();
                while (1)
                {
                    // 检查主进程是否存活
                    $master_is_alive = $master_pid && posix_kill($master_pid, 0);
                    if ($master_is_alive)
                    {
                        // 检查是否超过$timeout时间
                        if (time() - $start_time >= $timeout)
                        {
                            self::log("Workerman[$startFile] stop fail");
                            exit;
                        }
                        usleep(10000);
                        continue;
                    }
                    self::log("Workerman[$startFile] stop success");
                    // 是restart命令
                    if ($command === 'stop')
                    {
                        exit(0);
                    }
                    // -d 说明是以守护进程的方式启动
                    if ($command2 === '-d')
                    {
                        Worker::$daemonize = true;
                    }
                    break;
                }
                break;
            // 平滑重启 workerman
            case 'reload':
                posix_kill($master_pid, SIGUSR1);
                self::log("Workerman[$startFile] reload");
                exit;
            // 未知命令
            default :
                self::printUsage($startFile);
                exit;
        }
    }

    /**
     * 运行所有worker实例
     *
     * @return void
     */
    public static function runAll()
    {
        // 初始化环境变量
        self::init();
        // 解析命令
        self::parseCommand();
        // 尝试以守护进程模式运行
        self::daemonize();
        // 初始化所有worker实例，主要是监听端口
        self::initWorkers();
        //  初始化所有信号处理函数
        self::installSignal();
        // 保存主进程pid
        self::saveMasterPid();
        // 创建子进程（worker进程）并运行
        self::forkWorkers();
        // 展示启动界面
        self::displayUI();
        // 尝试重定向标准输入输出
        self::resetStd();
        // 监控所有子进程（worker进程）
        self::monitorWorkers();
    }
}
