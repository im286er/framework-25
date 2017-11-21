<?php

namespace framework\core;

class App {

    /**
     * 应用程序初始化
     */
    static public function init() {
        /* 开始时间与内存 */
        define('START_TIME', microtime(true));
        define('START_MEM', memory_get_usage());

        /* 异常处理类 */
        set_exception_handler("\\framework\\core\\App::exception_handle");
        /* 自定义错误处理函数，设置后 error_reporting 将失效。因为要保证 ajax 输出格式，所以必须触发 error_handle */
        set_error_handler("\\framework\\core\\App::error_handle");

        /* 设置默认时区 */
        date_default_timezone_set('Asia/Shanghai');

        /* 定义时间戳 */
        define('TIMESTAMP', time());

        /* 设置 session 配置 */
        if (Config::get('memcached_cache')) {
            ini_set("session.save_handler", "memcache");
            ini_set("session.gc_maxlifetime", "28800"); // 8 小时
            $save_path = "";
            foreach (Config::get('memcached_cache') as $key => $conf) {
                $save_path = empty($save_path) ? "tcp://{$conf['host']}:{$conf['port']}" : $save_path . ",tcp://{$conf['host']}:{$conf['port']}";
            }
            ini_set("session.save_path", $save_path);
        }

        /* 注册自动加载 */
        Loader::register();

        //URL调度
        Dispatcher::dispatch();
    }

    /**
     * 出错处理
     * @param type $errno
     * @param type $errstr
     * @param type $errfile
     * @param type $errline
     * @throws \Exception
     */
    public static function error_handle($errno, $errstr, $errfile, $errline) {
        $errortype = [
            E_ERROR => 'Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_DEPRECATED => 'Deprecated',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_USER_DEPRECATED => 'User Deprecated',
            E_STRICT => 'Runtime Notice',
            E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
        ];

        // 判断错误级别，决定是否退出。

        switch ($errno) {
            case E_ERROR:
            case E_PARSE:
            case E_USER_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
                // 抛出异常，记录到日志
                $errnostr = isset($errortype[$errno]) ? $errortype[$errno] : 'Unknonw';
                $s = "[$errnostr] : $errstr in File $errfile, Line: $errline";
                Log::write($s, Log::EMERG);

                ob_clean();
                $json = ['ret' => 500, 'data' => null, 'msg' => "500 Internal Server Error {$errno} {$s} "];
                ajax_return($json);
                break;
            case E_WARNING:
                // 记录到日志
                $errnostr = isset($errortype[$errno]) ? $errortype[$errno] : 'Unknonw';
                $s = "[$errnostr] : $errstr in File $errfile, Line: $errline";
                Log::write($s, Log::WARN);
                break;
            case E_NOTICE:
                // 记录到日志
//                $errnostr = isset($errortype[$errno]) ? $errortype[$errno] : 'Unknonw';
//                $s = "[$errnostr] : $errstr in File $errfile, Line: $errline";
//                Log::write($s, Log::NOTICE);
                break;
            default:
                break;
        }
    }

    /**
     * 异常处理
     * @param type $e
     * @throws \Exception
     */
    public static function exception_handle($e) {
        $msg = $e->getMessage() . ' File: ' . $e->getFile() . ' [' . $e->getLine() . ']';
        Log::write($msg, Log::EMERG);

        $json = ['ret' => 500, 'data' => null, 'msg' => $msg];
        ajax_return($json);
    }

    /**
     * 执行应用程序
     * @access public
     * @return void
     */
    static public function exec() {
        $class_name = MODULE_NAME . "Action";
        $action = ACTION_NAME;

        if (!class_exists($class_name)) {
            /* 重试一次命名空间 */
            $class_name = GROUP_NAME . "\\action\\" . MODULE_NAME . 'Action';
            if (!class_exists($class_name)) {
                if (class_exists('_emptyAction')) {
                    /* 如果定义了_empty操作 则调用 */
                    $class_name = '_emptyAction';
                } else {
                    $json = ['ret' => 404, 'data' => null, 'msg' => 'Action 不存在!'];
                    ajax_return($json);
                }
            }
        }

        $module = new $class_name;
        if (!is_callable([$module, $action])) {
            if (is_callable([$module, '_empty'])) {
                $action = '_empty';
            } else {
                $json = ['ret' => 404, 'data' => null, 'msg' => "{$module}-{$action} 不存在!"];
                ajax_return($json);
            }
        }

        try {
            /* 执行当前操作 */
            $method = new \ReflectionMethod($module, $action);
            if ($method->isPublic()) {
                $class = new \ReflectionClass($module);
                $method->invoke($module);
            } else {
                /* 操作方法不是Public 抛出异常 */
                throw new \ReflectionException();
            }
        } catch (\ReflectionException $e) {
            $json = ['ret' => 500, 'data' => null, 'msg' => $e->getTraceAsString()];
            ajax_return($json);
        }
        return;
    }

    /**
     * 运行应用实例 入口文件使用的快捷方法
     * @access public
     * @return void
     */
    static public function run() {
        App::init();
        App::exec();
        return;
    }

}
