<?php

/**
 * php 秒级循环任务管理器
 * https://github.com/KomaBeyond/php-cronManager
 */
class PHPCronManager {

    const processName = 'phpCronManager';

    /**
     * phpCronManager Master 进程Id，也是所有 worker 的父进程
     *
     * @var int
     */
    private $pid = 0;
    private $pidFile = '';

    /**
     * Master 和 Worker 进程通信的管道
     *
     * @var object
     */
    private $pipe = null;

    /**
     * Master 和 Worker 进程通信的消息队列
     *
     * @var object
     */
    private $msgQueue = null;

    /**
     * 所有 phpCronManager 管理的 task 实体
     *
     * @var array
     */
    private $tasks = [];

    /**
     * phpCronManager worker 进程容器
     *
     * @var array
     */
    private $workers = [];

    /**
     * phpCronManager 配置实体类
     * 包含 phpCronManager 和 task 的配置
     *
     * @var object
     */
    private $config = null;

    /**
     * phpCronManager 日志类实体
     *
     * @var object
     */
    private $logger = null;

    /*  工作进程数 */
    private $worker_processes = 2;

    private function __construct() {

    }

    public static function main() {
        $manager = new static();
        $manager->run();
    }

    protected function run() {

        global $argv;

        if (empty($argv[2]) || !in_array($argv[2], ['start', 'stop', 'restart', 'status'])) {
            $argv[2] = 'status';
        }

        $this->setPidFile();

        switch ($argv[2]) {
            case 'start':
                if ($this->checkMasterRunning()) {
                    print_error(PHPCronManager::processName . ' 已经在运行');
                    return false;
                }
                print_line(PHPCronManager::processName . " 开始启动");
                $this->bootstrap();
                break;
            case 'stop':
                if (!$this->checkMasterRunning()) {
                    print_error(PHPCronManager::processName . ' 还未启动');
                    return false;
                }
                $this->stop();
                break;
            case 'restart':
                if (!$this->checkMasterRunning()) {
                    print_error(PHPCronManager::processName . ' 还未启动');
                    return false;
                }
                $this->restart();
                break;

            case 'status':
                $pid = $this->getPid();
                if ($pid) {
                    print_ok("Daemon #{$pid} is running");
                } else {
                    print_error("Daemon is not running");
                }
                break;
        }
    }

    /**
     * 启动器
     * @param type $taskUUId
     */
    protected function bootstrap($taskUUId = '') {
        $this->pid = $this->daemon();

        if ($this->pid < 0) {
            Log::write('设置进程为守护进程失败', Log::ERR);
            exit(1);
        }

        if ($this->writePid() == false) {
            Log::write("写主进程PID文件失败，PID文件: {$this->pidFile}", Log::ERR);
            exit(1);
        }

        \Log::write('开始初始化待执行任务', Log::INFO);

        /* 获取要执行的任务 */
        $config_tasks = C('config_tasks');

        foreach ($config_tasks as $uuid => $taskConfig) {

            Log::write("开始初始化任务: {$uuid}", Log::INFO);

            try {
                $task = new PHPCron_Task($taskConfig);
                $task->setUUID($uuid);
                $task->setLastRunTime();
                $this->tasks[$uuid] = $task;

                Log::write("任务 {$uuid} 初始化成功", Log::INFO);
            } catch (\Exception $e) {
                Log::write("任务 {$uuid} 初始化失败，错误信息: {$e->getMessage()}", Log::WARN);
                continue;
            }
        }

        Log::write("待执行任务初始化完成，总任务数: " . count($this->tasks), Log::INFO);
        Log::write("开始初始化Worker进程", Log::INFO);

        for ($i = 0; $i < $this->worker_processes; $i++) {
            $pid = pcntl_fork();

            if ($pid == -1) {
                Log::write('Fork worker进程失败，进入重试阶段', Log::WARN);
                continue;
            }

            if ($pid > 0) {
                $worker = new PHPCron_Worker();
                $worker->setPPId($this->getPid());
                $worker->setPId($pid);
                $this->workers[$pid] = $worker;
                unset($worker);
            } else {
                // cli_set_process_title(self::processName . ': worker process');

                declare(ticks = 1);
                pcntl_signal(SIGUSR1, array($this, 'doTaskHandler'));

                Log::write('Worker进程就绪，进程Id:' . posix_getpid(), Log::INFO);

                while (true) {
                    sleep(30);
                }
                exit(0);
            }
        }

        // 分发...
        // pcntl_signal_dispatch();

        Log::write('初始化Worker进程完成，Worker进程数:' . count($this->workers), Log::INFO);
        Log::write('Master开始安装信号处理器', Log::INFO);

        declare(ticks = 1);
        pcntl_signal(SIGUSR1, array($this, "restartHandler"));
        pcntl_signal(SIGUSR2, array($this, "stopHandler"));

        Log::write("Master就绪，进程Id: {$this->getPid()}", Log::INFO);

        /* 循环所有任务 */
        while (count($this->workers) > 0) {
            foreach ($this->tasks as $uuid => $task) {
                if (time() - $task->getLastRunTime() > $task->getDelayTime()) {
                    $task->setLastRunTime();
                    Queue::getInstance()->qpush(self::processName, $uuid);

                    foreach ($this->workers as $worker) {
                        if (!$worker->checkRunning()) {
                            $worker->setRunning(true);
                            posix_kill($worker->getPid(), SIGUSR1);
                            break;
                        }
                    }
                }
            }

            $ret = pcntl_waitpid(0, $status, WNOHANG);
            if (pcntl_wifexited($status)) { //正常退出
                if (isset($this->workers[$ret])) {
                    unset($this->workers[$ret]);
                }
            } else if (pcntl_wifsignaled($status)) { //因未捕获信号退出
                if (isset($this->workers[$ret])) {
                    unset($this->workers[$ret]);
                }
            }

            sleep(1);
        }

        $this->clearPidFile();
    }

    protected function stop($taskUUId = '') {
        if (($pid = $this->getPid()) > 0) {
            posix_kill($pid, SIGUSR2);
        }
    }

    protected function restart() {
        if (($pid = $this->getPid()) > 0) {
            posix_kill($pid, SIGUSR1);
        }
    }

    protected function restartHandler($signo) {
        if (($pid = $this->getPid()) > 0) {
            posix_kill($pid, SIGUSR1);
        }
    }

    protected function stopHandler($signo) {
        foreach ($this->workers as $worker) {
            posix_kill($worker->getPid(), SIGKILL);
        }
    }

    protected function doTaskHandler($signo) {

        while (true) {
            $taskUUID = Queue::getInstance()->qpop(self::processName);
            if (($taskUUID) && isset($this->tasks[$taskUUID])) {
                $task = $this->tasks[$taskUUID];
                $task->run();
            } else {
                sleep(1);
            }
        }
    }

    protected function checkMasterRunning() {
        clearstatcache();

        if (file_exists($this->pidFile)) {
            $pid = intval(file_get_contents($this->pidFile));
            return file_exists("/proc/{$pid}") ? true : false;
        }
        return false;
    }

    protected function getPid() {
        clearstatcache();

        if (file_exists($this->pidFile)) {
            $pid = intval(file_get_contents($this->pidFile));
            return file_exists("/proc/{$pid}") ? $pid : 0;
        }
        return 0;
    }

    protected function writePid() {
        return file_put_contents($this->pidFile, $this->pid);
    }

    protected function setPidFile() {
        $this->pidFile = '/tmp/' . self::processName . '.pid';
    }

    protected function clearPidFile() {
        if (file_exists($this->pidFile)) {
            unlink($this->pidFile);
        }
    }

    /**
     * 设置进程为守护进程
     * 成功返回守护进程Id，失败返回-1
     *
     * @return int
     */
    protected function daemon() {
        \Log::write("开始将Master进程设置为Daemon模式", Log::INFO);

        //step1 让 daemon 进程在子进程中执行
        $pid = pcntl_fork();
        if ($pid == -1) {
            \Log::write('"第1次Fork失败"', Log::ERR);
            return -1;
        }

        if ($pid > 0) {
            exit(0);
        }

        //step2 使子进程脱离控制终端，登录会话和进程组并使子进程成为新的进程组组长
        $sid = posix_setsid();
        if ($sid < 0) {
            \Log::write('重置会话Id错误', Log::ERR);
            return -1;
        }
        unset($sid);

        //step3 子进程重新 fork 出新的子进程，自己退出，新的子进程不再是进程组组长
        //从而禁止了进程可以重新打开控制终端
        $pid = pcntl_fork();
        if ($pid == -1) {
            \Log::write('"第2次Fork失败"', Log::ERR);
            return -1;
        }

        if ($pid > 0) {
            exit(0);
        }

        //step4 关闭从父进程继承来的已打开的资源，节省资源
        //close stdin, stdout, stderr
        //step５ 修改 daemon 进程的工作目录
        $workDir = '/tmp/';
        if (!is_dir($workDir)) {
            return -1;
        }

        if (!posix_access($workDir, POSIX_R_OK | POSIX_W_OK)) {
            return -1;
        }
        chdir($workDir);

        //step6 重设 daemon 进程的文件创建掩码
        umask(0);

        //step8 设置 Master 进程名称
        // cli_set_process_title(self::processName . ': master process ( main )');

        Log::write("Master进入Daemon模式", Log::INFO);

        return posix_getpid();
    }

    protected function log($level, $message) {
        if (is_array($message))
            $message = json_encode($message);
        if ($this->logger) {
            $this->logger->log($level, $message);
        } else {
            print '[' . strtoupper($level) . '] ' . date('Y-m-d H:i:s', time()) . " {$message}\n";
        }
    }

}

class PHPCron_Worker {

    private $ppid;
    private $pid;
    private $running = false;

    public function __construct() {

    }

    public function setPPId($ppid) {
        $this->ppid = $ppid;
        return $this;
    }

    public function setPId($pid) {
        $this->pid = $pid;
        return $this;
    }

    public function getPid() {
        return $this->pid;
    }

    public function setRunning($running) {
        $this->running = $running;
        return $this;
    }

    public function checkRunning() {
        return $this->running;
    }

}

class PHPCron_Task {

    private $uuid = '';
    private $scriptFile = '';
    private $delayTime = 0;
    private $lastRunTime = 0;

    public function __construct($config) {
        if (!isset($config['script'])) {
            throw new \Exception("Task script file not set");
        }
        $this->scriptFile = $config['script'];

        if (!isset($config['delay'])) {
            throw new \Exception("Task delay time not set");
        }
        $this->delayTime = $config['delay'];
    }

    public function run() {
        $this->setLastRunTime();
        exec($this->getScriptFile());
    }

    public function getDelayTime() {
        return $this->delayTime;
    }

    public function setLastRunTime() {
        $this->lastRunTime = time();
        return $this;
    }

    public function getLastRunTime() {
        return $this->lastRunTime;
    }

    public function getScriptFile() {
        return $this->scriptFile;
    }

    public function setUUID($uuid) {
        $this->uuid = $uuid;

        return $this;
    }

    public function getUUId() {
        return $this->uuid;
    }

}
