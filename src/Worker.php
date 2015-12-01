<?php

namespace tourze\Server;

use Exception;
use tourze\Base\Base;
use tourze\Base\Config;
use tourze\Base\Helper\Arr;
use tourze\Server\Exception\BaseException;
use Workerman\Connection\ConnectionInterface;
use Workerman\Events\EventInterface;
use Workerman\Lib\Timer;
use Workerman\Worker as BaseWorker;

/**
 * 继承原有的Worker基础类
 *
 * @package tourze\Server
 */
class Worker extends BaseWorker
{

    /**
     * @var string 运行 status 命令时用于保存结果的文件名
     */
    public static $statusFile = '';

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
     * 初始化一些环境变量
     *
     * @return void
     */
    public static function init()
    {
        // 如果没设置$pidFile，则生成默认值
        if (empty(self::$pidFile))
        {
            $backtrace = debug_backtrace();
            self::$_startFile = $backtrace[count($backtrace) - 1]['file'];
            self::$pidFile = sys_get_temp_dir() . "/workerman." . str_replace('/', '_', self::$_startFile) . ".pid";
        }
        // 没有设置日志文件，则生成一个默认值
        if (empty(self::$logFile))
        {
            self::$logFile = __DIR__ . '/../server.log';
        }
        // 标记状态为启动中
        self::$_status = self::STATUS_STARTING;

        // 启动时间戳
        self::$_globalStatistics['start_timestamp'] = time();
        Base::getLog()->debug(__METHOD__ . ' worker start timestamp', [
            'time' => self::$_globalStatistics['start_timestamp'],
        ]);

        // 设置status文件位置
        self::$statusFile = sys_get_temp_dir() . '/workerman.status';
        Base::getLog()->debug(__METHOD__ . ' set status file', [
            'file' => self::$statusFile,
        ]);

        // 尝试设置进程名称（需要php>=5.5或者安装了proctitle扩展）
        self::setProcessTitle('WorkerMan: master process  start_file=' . self::$_startFile);

        // 初始化ID
        self::initId();

        // 初始化定时器
        Timer::init();
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
                'ACTION'  => 'start',
                'DESC'    => 'Start all services',
                'EXAMPLE' => "php $startFile start",
            ],
            [
                'ACTION'  => 'stop',
                'DESC'    => 'Stop all services',
                'EXAMPLE' => "php $startFile stop",
            ],
            [
                'ACTION'  => 'restart',
                'DESC'    => 'Restart  all running services',
                'EXAMPLE' => "php $startFile restart",
            ],
            [
                'ACTION'  => 'reload',
                'DESC'    => 'Reload all running services',
                'EXAMPLE' => "php $startFile reload",
            ],
            [
                'ACTION'  => 'status',
                'DESC'    => 'Response current service status',
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

        Base::getLog()->debug(__METHOD__ . ' parse and execute command', [
            'file'    => $startFile,
            'command' => $command,
            'mode'    => $mode,
        ]);

        // 检查主进程是否在运行
        $masterPid = @file_get_contents(self::$pidFile);
        Base::getLog()->debug(__METHOD__ . ' get master pid from pidFile', [
            'file' => self::$pidFile,
            'pid'  => $masterPid,
        ]);

        $masterIsAlive = $masterPid && @posix_kill($masterPid, 0);
        Base::getLog()->debug(__METHOD__ . ' if master is alive', [
            'alive' => $masterIsAlive,
        ]);

        if ($masterIsAlive)
        {
            if ($command === 'start')
            {
                Base::getLog()->debug(__METHOD__ . ' server is running', [
                    'file' => $startFile,
                ]);
            }
        }
        elseif ($command !== 'start' && $command !== 'restart')
        {
            Base::getLog()->debug(__METHOD__ . ' server is not running', [
                'file' => $startFile,
            ]);
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
                if (is_file(self::$statusFile))
                {
                    Base::getLog()->debug(__METHOD__ . ' delete old status file', [
                        'file' => self::$statusFile,
                    ]);
                    @unlink(self::$statusFile);
                }
                // 向主进程发送 SIGUSR2 信号 ，然后主进程会向所有子进程发送 SIGUSR2 信号
                // 所有进程收到 SIGUSR2 信号后会向 $statusFile 写入自己的状态
                posix_kill($masterPid, SIGUSR2);
                // 睡眠100毫秒，等待子进程将自己的状态写入 $statusFile 指定的文件
                usleep(100000);
                // 展示状态
                if ( ! is_file(self::$statusFile))
                {
                    exit("Status file is missing.\n");
                }
                readfile(self::$statusFile);
                exit(0);
            // 重启 workerman
            case 'restart':
                // 停止 workeran

            case 'stop':

                Base::getLog()->debug(__METHOD__ . ' stopping all services', [
                    'file' => $startFile,
                ]);

                // 想主进程发送SIGINT信号，主进程会向所有子进程发送SIGINT信号
                $masterPid && posix_kill($masterPid, SIGINT);
                // 如果 $timeout 秒后主进程没有退出则展示失败界面
                $timeout = 5;
                $startTime = time();
                while (1)
                {
                    // 检查主进程是否存活
                    $masterIsAlive = $masterPid && posix_kill($masterPid, 0);
                    if ($masterIsAlive)
                    {
                        // 检查是否超过$timeout时间
                        if (time() - $startTime >= $timeout)
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
                posix_kill($masterPid, SIGUSR1);
                self::log("Workerman[$startFile] reload");
                exit;
            // 未知命令
            default :
                self::printUsage($startFile);
                exit;
        }
    }

    /**
     * 尝试以守护进程的方式运行
     *
     * @throws Exception
     */
    protected static function daemonize()
    {
        Base::getLog()->debug(__METHOD__ . ' calling daemonize', [
            'daemonize' => self::$daemonize,
        ]);
        if ( ! self::$daemonize)
        {
            return;
        }

        umask(0);
        $pid = pcntl_fork();
        if (-1 === $pid)
        {
            throw new Exception('fork fail');
        }
        elseif ($pid > 0)
        {
            exit(0);
        }
        if (-1 === posix_setsid())
        {
            throw new Exception("setsid fail");
        }
        // fork again avoid SVR4 system regain the control of terminal
        $pid = pcntl_fork();
        if (-1 === $pid)
        {
            throw new Exception("fork fail");
        }
        elseif (0 !== $pid)
        {
            exit(0);
        }
    }

    /**
     * 重定向标准输入输出
     *
     * @throws Exception
     */
    protected static function resetStd()
    {
        if ( ! self::$daemonize)
        {
            return;
        }
        global $STDOUT, $STDERR;
        $handle = fopen(self::$stdoutFile, "a");
        if ($handle)
        {
            unset($handle);
            @fclose(STDOUT);
            @fclose(STDERR);
            $STDOUT = fopen(self::$stdoutFile, "a");
            $STDERR = fopen(self::$stdoutFile, "a");
        }
        else
        {
            throw new Exception('can not open stdoutFile ' . self::$stdoutFile);
        }
    }

    /**
     * 将当前进程的统计信息写入到统计文件
     *
     * @return void
     */
    protected static function writeStatisticsToStatusFile()
    {
        // 主进程部分
        if (self::$_masterPid === posix_getpid())
        {
            $loadAvg = sys_getloadavg();
            file_put_contents(self::$statusFile, "---------------------------------------GLOBAL STATUS--------------------------------------------\n");
            file_put_contents(self::$statusFile, 'Tourze version:' . Base::version() . "          PHP version:" . PHP_VERSION . "\n", FILE_APPEND);
            file_put_contents(self::$statusFile, 'start time:' . date('Y-m-d H:i:s', self::$_globalStatistics['start_timestamp']) . '   run ' . floor((time() - self::$_globalStatistics['start_timestamp']) / (24 * 60 * 60)) . ' days ' . floor(((time() - self::$_globalStatistics['start_timestamp']) % (24 * 60 * 60)) / (60 * 60)) . " hours   \n", FILE_APPEND);
            file_put_contents(self::$statusFile, 'load average: ' . implode(", ", $loadAvg) . "\n", FILE_APPEND);
            file_put_contents(self::$statusFile, count(self::$_pidMap) . ' workers       ' . count(self::getAllWorkerPids()) . " processes\n", FILE_APPEND);
            file_put_contents(self::$statusFile, str_pad('worker_name', self::$_maxWorkerNameLength) . " exit_status     exit_count\n", FILE_APPEND);
            foreach (self::$_pidMap as $worker_id => $worker_pid_array)
            {
                $worker = self::$_workers[$worker_id];
                if (isset(self::$_globalStatistics['worker_exit_info'][$worker_id]))
                {
                    foreach (self::$_globalStatistics['worker_exit_info'][$worker_id] as $worker_exit_status =>
                             $worker_exit_count)
                    {
                        file_put_contents(self::$statusFile, str_pad($worker->name, self::$_maxWorkerNameLength) . " " . str_pad($worker_exit_status, 16) . " $worker_exit_count\n", FILE_APPEND);
                    }
                }
                else
                {
                    file_put_contents(self::$statusFile, str_pad($worker->name, self::$_maxWorkerNameLength) . " " . str_pad(0, 16) . " 0\n", FILE_APPEND);
                }
            }
            file_put_contents(self::$statusFile, "---------------------------------------PROCESS STATUS-------------------------------------------\n", FILE_APPEND);
            file_put_contents(self::$statusFile, "pid\tmemory  " . str_pad('listening', self::$_maxSocketNameLength) . " " . str_pad('worker_name', self::$_maxWorkerNameLength) . " connections " . str_pad('total_request', 13) . " " . str_pad('send_fail', 9) . " " . str_pad('throw_exception', 15) . "\n", FILE_APPEND);

            chmod(self::$statusFile, 0722);

            foreach (self::getAllWorkerPids() as $worker_pid)
            {
                posix_kill($worker_pid, SIGUSR2);
            }
            return;
        }

        // 子进程部分
        $worker = current(self::$_workers);
        $statusStr = posix_getpid() . "\t" . str_pad(round(memory_get_usage(true) / (1024 * 1024), 2) . "M", 7) . " " . str_pad($worker->getSocketName(), self::$_maxSocketNameLength) . " " . str_pad(($worker->name === $worker->getSocketName() ? 'none' : $worker->name), self::$_maxWorkerNameLength) . " ";
        $statusStr .= str_pad(ConnectionInterface::$statistics['connection_count'], 11) . " " . str_pad(ConnectionInterface::$statistics['total_request'], 14) . " " . str_pad(ConnectionInterface::$statistics['send_fail'], 9) . " " . str_pad(ConnectionInterface::$statistics['throw_exception'], 15) . "\n";
        file_put_contents(self::$statusFile, $statusStr, FILE_APPEND);
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
