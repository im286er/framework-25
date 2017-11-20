<?php

namespace framework\cli;

use Pagon\ChildProcess;
use Pagon\EventEmitter;

/**

  CREATE TABLE phpcron
  (
  id serial,
  cron_name character varying(64), -- 任务名称
  command character varying(512), -- 命令
  exec_time character varying(20), -- 执行时间
  online_time timestamp without time zone, -- 任务开始时间
  offline_time timestamp without time zone, -- 任务下线时间
  status integer DEFAULT 0, -- 是否启用
  note text, -- 备注
  ctime timestamp without time zone DEFAULT now(),
  mtime timestamp without time zone DEFAULT now(),
  CONSTRAINT phpcron_pkey PRIMARY KEY (id)
  );

  COMMENT ON TABLE phpcron  IS '计划任务';
  COMMENT ON COLUMN phpcron.cron_name IS '任务名称';
  COMMENT ON COLUMN phpcron.command IS '命令';
  COMMENT ON COLUMN phpcron.exec_time IS '执行时间';
  COMMENT ON COLUMN phpcron.online_time IS '任务开始时间';
  COMMENT ON COLUMN phpcron.offline_time IS '任务下线时间';
  COMMENT ON COLUMN phpcron.status IS '是否启用';
  COMMENT ON COLUMN phpcron.note IS '备注';

 */
class phpcron extends EventEmitter {

    protected $_is_run = false;
    protected $options = array();
    protected $pidFile;
    protected $pid;
    public $tasks = array();

    /**
     * @param array $options
     * @throws \InvalidArgumentException
     */
    public function __construct(array $options = array()) {

        /* Reset opcache. */
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        $this->options = $options + $this->options;

        $this->pidFile = '/tmp/' . get_class($this) . '.pid';

        $this->start_time = time();
        $this->process = new ChildProcess();
    }

    /**
     *
     * Exec the command and return code
     *
     * @param string $cmd
     * @param string $stdout
     * @param string $stderr
     * @param int    $timeout
     * @return int|null
     */
    public static function exec($cmd, &$stdout, &$stderr, $timeout = 3600) {
        if ($timeout <= 0) {
            $timeout = 3600;
        }

        $descriptors = [
            1 => ["pipe", "w"],
            2 => ["pipe", "w"]
        ];

        $null = null;
        $pipes = null;
        $stdout = $stderr = $status = null;
        $process = proc_open($cmd, $descriptors, $pipes);
        $time_end = time() + $timeout;
        if (is_resource($process)) {
            do {
                $time_left = $time_end - time();
                $read = array($pipes[1]);
                stream_select($read, $null, $null, $time_left, NULL);
                $stdout .= fread($pipes[1], 2048);
            } while (!feof($pipes[1]) && $time_left > 0);
            fclose($pipes[1]);
            if ($time_left <= 0) {
                proc_terminate($process);
                $stderr = 'process terminated for timeout.';
                return -1;
            }
            while (!feof($pipes[2])) {
                $stderr .= fread($pipes[2], 2048);
            }
            fclose($pipes[2]);
            $status = proc_close($process);
        }
        return $status;
    }

    /**
     * 获取计划任务列表
     *
     * @return array
     */
    public function getNeedsExecTasks() {

        $this->tasks = [];

        $json = RestClient::getInstance()->get('backend/phpcron/method/front_get_list/', []);
        if ($json && $json['ret'] == 200) {
            $task_config = $json['data'];
        } else {
            return false;
        }

        foreach ($task_config as $row) {
            if (empty($row['online_time']) || strtotime($row['online_time']) <= time()) {
                if (empty($row['offline_time']) || strtotime($row['offline_time']) >= time()) {
                    /* 分析执行时间 */
                    $cron = \Cron\CronExpression::factory($row['exec_time']);
                    if ($cron->isDue()) {
                        $this->tasks[$row['id']] = $row;
                    }
                }
            }
        }
        return true;
    }

    /**
     * 设置进程为守护进程
     * 成功返回守护进程Id，失败返回-1
     *
     * @return int
     */
    protected function daemon() {
        CliHelper::print_line("开始将Master进程设置为Daemon模式");

        //step1 让 daemon 进程在子进程中执行
        $pid = pcntl_fork();
        if ($pid == -1) {
            \Log::write('"第1次Fork失败"');
            return -1;
        }

        if ($pid > 0) {
            exit(0);
        }

        //step2 使子进程脱离控制终端，登录会话和进程组并使子进程成为新的进程组组长
        $sid = posix_setsid();
        if ($sid < 0) {
            \Log::write('重置会话Id错误');
            return -1;
        }
        unset($sid);

        //step3 子进程重新 fork 出新的子进程，自己退出，新的子进程不再是进程组组长
        //从而禁止了进程可以重新打开控制终端
        $pid = pcntl_fork();
        if ($pid == -1) {
            \Log::write('"第2次Fork失败"');
            return -1;
        }

        if ($pid > 0) {
            exit(0);
        }

        //step6 重设 daemon 进程的文件创建掩码
        umask(0);

        CliHelper::print_line("Master进入Daemon模式");

        return posix_getpid();
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

    /**
     * Start to run
     */
    public function run() {
        if ($this->_is_run) {
            throw new \RuntimeException("Already running!");
        }

        $this->_is_run = true;
        $this->emit('run');

        if ($pid = $this->getPid()) {
            CliHelper::print_error(get_class($this) . " Daemon #{$pid} has already started");
            return false;
        }

        $this->pid = $this->daemon();

        if ($this->pid < 0) {
            CliHelper::print_error('设置进程为守护进程失败');
            exit(1);
        }

        /* 写入 pid */
        if ($this->writePid() == false) {
            CliHelper::print_error("写主进程PID文件失败，PID文件: {$this->pidFile}");
            exit(1);
        }

        CliHelper::print_ok("{$this->pid}　运行成功！");

        while (true) {

            $current_time = mktime(date('H'), date('i'), 0);

            // 载入任务
            $this->getNeedsExecTasks();
            $this->emit('getNeedsExecTasks');

            foreach ($this->tasks as $task) {
                $this->dispatch($task);
            }

            /* 休息等待下次运行 */
            $sleep = 60 - (time() - $current_time);
            if ($sleep > 0) {
                sleep($sleep);
            }
        }
    }

    /**
     * 分发运行
     */
    protected function dispatch($task) {

        $this->emit('execute', $task);
        $that = $this;

        $this->process->parallel(function () use ($task, $that) {
            $status = self::exec($task['command'], $stdout, $stderr);
            $that->emit('executed', $task, array($status, $stdout, $stderr));
        }
        );
    }

}
