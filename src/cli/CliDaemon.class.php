<?php

/**
 * 守护进程类，需要pcntl扩展支持
 *
 */
abstract class CliDaemon {

    //开启子进程数
    var $maxProcess = 5;
    //pid文件路径
    var $pidFile = '/tmp/';
    //需要执行的类名
    var $processClass;

    public function __construct() {
        /* 只允许在cli下面运行 */
        if (php_sapi_name() !== "cli") {
            print_error('[失败] 只能在命令行下执行！');
            exit;
        }

        if (!extension_loaded('pcntl')) {
            print_error('[失败] pcntl 不支持！');
            exit;
        }

        declare(ticks = 10);

        $this->processClass = get_class($this);
        $this->pidFile .= '/' . $this->processClass . '.pid';
    }

    /**
     * 业务方法
     */
    abstract public function run();

    /**
     * 启动服务
     */
    public function start() {
        $this->_createProcess();
    }

    /**
     * 获取服务状态
     */
    public function status() {
        $pid = $this->_checkPPid();
        if ($pid) {
            print_ok("[状态] 监控服务已经运行！ 进程编号 #{$pid}");
        } else {
            print_error("[提示] 监控服务未运行");
        }
    }

    /**
     * 停止服务
     */
    public function stop() {
        $pid = $this->_checkPPid();
        if ($pid) {
            posix_kill($pid, SIGTERM);
            // 强制杀死进程
            posix_kill($pid, 9);
        }
        if (!is_file($this->pidFile)) {
            print_error("[提示] {$this->processClass} 服务未启动！");
        } else {
            if (!unlink($this->pidFile)) {
                print_error("[提示] {$this->processClass} 服务停止失败！");
            }
            print_ok("[提示] {$this->processClass} 服务已经停止运行！");
        }
    }

    private function _createProcess() {
        $pid = $this->_checkPPid();
        if ($pid) {
            print_error("[状态] 监控服务已经运行！ 进程编号 #{$pid} \n");
            exit();
        }

        $pid = pcntl_fork();
        if ($pid == -1) {
            print_error("[失败]: 启动子进程出错！");
            exit();
        }
        if ($pid) {
            print_line("[提示] 服务启动中...");
            pcntl_waitpid(0, $status, WNOHANG);
        } else {
            $spid = getmypid();
            if ($spid && is_readable(dirname($this->pidFile))) {
                file_put_contents($this->pidFile, $spid);
            } else {
                print_error("[提示] {$this->pidFile} 文件创建失败！");
                exit();
            }
            $this->_runProcess();
        }

        print_ok("[提示] pid 文件位置 {$this->pidFile}");
        print_ok("[提示] 子进程数 {$this->maxProcess} 启动完成！");
        print_ok("[提示] {$this->processClass} 服务启动完成！");
    }

    /**
     * 检查进程号
     * @return boolean
     */
    private function _checkPPid() {
        clearstatcache();
        if (is_file($this->pidFile)) {
            $pid = intval(file_get_contents($this->pidFile));
            return file_exists("/proc/{$pid}") ? $pid : false;
        }
        return false;
    }

    private function _runProcess() {
        while (true) {
            if (!$this->_checkPPid()) {
                exit(0);
            }
            $pid = pcntl_fork();
            if ($pid == -1) {
                print_error("[失败]: 启动子进程出错！\n");
                exit();
            }
            if ($pid) {
                static $execute = 0;
                $execute++;
                if ($execute >= $this->maxProcess) {
                    pcntl_wait($status);
                    $execute--;
                }
            } else {
                while (true) {
                    if (!$this->_checkPPid()) {
                        exit(0);
                    }
                    $this->pid = getmypid();
                    $this->run();
                }
                exit(0);
            }
        }
    }

}
