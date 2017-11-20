<?php

namespace framework\cli;

/**
 * PHP监控服务类(单进程)
 */
abstract class Daemon {

    /**
     * Daemon options
     * @var array
     */
    protected $_options = [];

    /**
     * Signal handlers
     * @var array
     */
    protected $_sigHandlers = [];

    /**
     * Things todo before main()
     * @var array
     */
    protected $_todos = [];

    /**
     * Iteration counter
     * @var int
     */
    protected $_cnt = 0;

    /**
     * Daemon PID
     * @var int
     */
    protected $_pid;
    protected $_exit = false;

    public function __construct() {

        if (php_sapi_name() != 'cli') {
            echo 'This Application must be started with cli mode.' . PHP_EOL;
            throw new \Exception('This Application must be started with cli mode.');
        }

        /* Reset opcache. */
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        global $argv;

        $defaults = [
            'chuser' => false,
            'uid' => 99,
            'gid' => 99,
            'maxTimes' => 0,
            'maxMemory' => 0,
            'limitMemory' => -1,
            'pid' => '/tmp/' . get_class($this) . '.pid',
            'help' => "Usage:\n\n 程序 " . $argv[0] . " " . $argv[1] . " start|stop|restart|status|help\n\n",
        ];

        $this->_options += $defaults;

        set_error_handler(array($this, 'errorHandler'));
        register_shutdown_function(array($this, 'shutdown'));

        ini_set('memory_limit', $this->_options['limitMemory']);
        ini_set('display_errors', 'Off');
        clearstatcache();
    }

    /**
     * Handle commands from cli
     *
     * start: start the daemon
     * stop: stop the daemon
     * restart: restart the daemon
     * status: print the daemon status
     * --help: print help message
     * -h: print help message
     *
     */
    public function run() {
        global $argv;
        if (empty($argv[2]) || !in_array($argv[2], ['start', 'stop', 'restart', 'status', 'help'])) {
            $argv[2] = 'help';
        }

        $action = $argv[2];
        $this->$action();
    }

    /**
     * Get daemon pid number
     * @return mix, false where not running
     */
    public function pid() {
        clearstatcache();

        if (!file_exists($this->_options['pid'])) {
            return false;
        }
        $pid = intval(file_get_contents($this->_options['pid']));
        return file_exists("/proc/{$pid}") ? $pid : false;
    }

    /**
     * 业务方法
     */
    abstract public function main();

    /**
     * 启动服务
     */
    public function start() {
        $msg = get_class($this) . " Starting daemon...";
        CliHelper::print_line($msg);

        $this->_daemonize();

        $msg = get_class($this) . " Daemon #" . $this->pid() . " is running";
        CliHelper::print_ok($msg);

        declare(ticks = 1) {
            while (!$this->_exit) {
                $this->_autoRestart();
                $this->_todo();
                if ($this->_exit) {
                    break;
                }
                try {
                    $this->main();
                } catch (Exception $e) {
                    $msg = get_class($this) . ' ' . $e->getMessage();
                    Log::write($msg);
                    CliHelper::print_error($msg);
                }
            }
        }
    }

    /**
     * Stop Daemon
     *
     */
    public function stop() {
        if (!$pid = $this->pid()) {
            $msg = get_class($this) . " Daemon is not running\n";
            CliHelper::print_error($msg);
            return false;
        }

        posix_kill($pid, SIGTERM);
        // 强制杀死进程
        posix_kill($pid, 9);

        CliHelper::print_ok('操作成功');
    }

    /**
     * Restart Daemon
     *
     */
    public function restart() {
        if (!$pid = $this->pid()) {
            CliHelper::print_error('Daemon is not running');
        } else {
            posix_kill($pid, SIGHUP);
        }
    }

    /**
     * Get Daemon status
     *
     */
    public function status() {
        if ($pid = $this->pid()) {
            CliHelper::print_ok("Daemon #{$pid} is running");
        } else {
            CliHelper::print_error("Daemon is not running");
        }
    }

    /**
     * Print help message
     *
     */
    public function help() {
        CliHelper::print_line($this->_options['help']);
    }

    /**
     * Default signal handler
     *
     * @param int $signo
     */
    public function defaultSigHandler($signo) {
        switch ($signo) {
            case SIGTERM:
            case SIGQUIT:
            case SIGINT:
                $this->_todos[] = array(array($this, '_stop'));
                break;
            case SIGHUP:
                $this->_todos[] = array(array($this, '_restart'));
                break;
            default:
                break;
        }
    }

    /**
     * Regist signo handler
     *
     * @param int $signo
     * @param callback $action
     */
    public function regSigHandler($signo, $action) {
        $this->_sigHandlers[$signo] = $action;
    }

    /**
     * Daemonize
     *
     */
    protected function _daemonize() {
        if (!$this->_check()) {
            exit();
        }

        if (!$this->_fork()) {
            exit();
        }

        $this->_sigHandlers += array(
            SIGTERM => array($this, 'defaultSigHandler'),
            SIGQUIT => array($this, 'defaultSigHandler'),
            SIGINT => array($this, 'defaultSigHandler'),
            SIGHUP => array($this, 'defaultSigHandler'),
        );

        foreach ($this->_sigHandlers as $signo => $callback) {
            pcntl_signal($signo, $callback);
        }

        file_put_contents($this->_options['pid'], $this->_pid);
    }

    /**
     * Check environments
     *
     */
    protected function _check() {
        if ($pid = $this->pid()) {
            CliHelper::print_error(get_class($this) . " Daemon #{$pid} has already started");
            return false;
        }

        $dir = dirname($this->_options['pid']);
        if (!is_writable($dir)) {
            CliHelper::print_error("you do not have permission to write pid file @ {$dir}");
            return false;
        }

        if (!defined('SIGHUP')) {
            CliHelper::print_error("PHP is compiled without --enable-pcntl directive");
            return false;
        }

        if (!function_exists('posix_getpid')) {
            CliHelper::print_error("PHP is compiled without --enable-posix directive");
            return false;
        }

        return true;
    }

    /**
     * Fork
     *
     * @return boolean
     */
    protected function _fork() {
        $pid = pcntl_fork();

        if (-1 == $pid) { // error
            Log::write(get_class($this) . ' Could not fork');
            return false;
        }

        if ($pid) { // parent
            exit();
        }

        // children
        $this->_pid = posix_getpid();
        /* 使进程成为会话组长。让进程摆脱原会话的控制；让进程摆脱原进程组的控制； */
        posix_setsid();

        return true;
    }

    /**
     * Run things before iteration
     *
     */
    protected function _todo() {
        foreach ($this->_todos as $row) {
            (1 === count($row)) ? call_user_func($row[0]) : call_user_func_array($row[0], $row[1]);
        }
    }

    /**
     * Stop daemon
     *
     * @param boolean $exit
     * @return mixed
     */
    protected function _stop() {
        if (!is_writeable($this->_options['pid'])) {
            CliHelper::print_error(get_class($this) . " Daemon (no pid file) not running");
            return false;
        }

        $pid = $this->pid();
        unlink($this->_options['pid']);

        CliHelper::print_ok(get_class($this) . " Daemon #{$pid} has stopped");

        $this->_exit = true;
    }

    /**
     * Restart daemon
     *
     */
    protected function _restart() {
        global $argv;
        $this->_stop();

        CliHelper::print_ok(get_class($this) . " Daemon is restarting...");

        $cmd = $_SERVER['_'] . ' ' . implode(' ', $argv);
        $cmd = trim($cmd, ' > /dev/null 2>&1 &') . ' > /dev/null 2>&1 &';
        shell_exec($cmd);
    }

    /**
     * Check auto restart
     *
     */
    protected function _autoRestart() {
        if ((0 !== $this->_options['maxTimes'] && $this->_cnt >= $this->_options['maxTimes']) || (0 !== $this->_options['maxMemory'] && memory_get_usage(true) >= $this->_options['maxMemory'])) {
            $this->_todos[] = [[$this, '_restart']];
            $this->_cnt = 0;
        }
        $this->_cnt ++;
    }

    public function errorHandler($errno, $errstr, $errfile, $errline) {
        Log::write(get_class($this) . ' ' . implode('|', array($errno, $errstr, $errfile, $errline)));
        return true;
    }

    /**
     * Shutdown clean up
     *
     */
    public function shutdown() {
        if ($error = error_get_last()) {
            Log::write(get_class($this) . ' ' . implode('|', $error));
        }

        if (is_writeable($this->_options['pid']) && $this->_pid) {
            unlink($this->_options['pid']);
        }
    }

}
