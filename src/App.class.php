<?php

class App {

    /**
     * 应用程序初始化
     */
    static public function init() {
        /* 对用户传入的变量进行转义操作。 */
        $_GET = transcribe($_GET);
        $_POST = transcribe($_POST);
        $_COOKIE = transcribe($_COOKIE);
        $_REQUEST = transcribe($_REQUEST);

        /* 注册自动加载 */
        Loader::register();

        /* 异常处理类 */
        set_exception_handler(['App', 'exception_handle']);
        /* 自定义错误处理函数，设置后 error_reporting 将失效。因为要保证 ajax 输出格式，所以必须触发 error_handle */
        set_error_handler(['App', 'error_handle'], -1);

        /* 设置默认时区 */
        date_default_timezone_set('Asia/Shanghai');

        /* 定义时间戳 */
        define('TIMESTAMP', time());
        define('START_TIME', microtime(true));
        define('START_MEM', memory_get_usage());

        //URL调度
        Dispatcher::dispatch();

        return;
    }

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

        // true 表示不执行 PHP 内部错误处理程序, false 表示执行PHP默认处理
        //return DEBUG ? FALSE : TRUE;
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

                if (Request::getInstance()->isAjax() == true) {
                    ob_clean();
                    $json = ['ret' => 500, 'data' => null, 'msg' => "500 Internal Server Error {$errno} {$s} "];
                    ajax_return($json);
                }
                break;
            case E_WARNING:
                // 抛出异常，记录到日志
                $errnostr = isset($errortype[$errno]) ? $errortype[$errno] : 'Unknonw';
                $s = "[$errnostr] : $errstr in File $errfile, Line: $errline";
                Log::write($s, Log::WARN);
                break;
            case E_NOTICE:
                // 抛出异常，记录到日志
                $errnostr = isset($errortype[$errno]) ? $errortype[$errno] : 'Unknonw';
                $s = "[$errnostr] : $errstr in File $errfile, Line: $errline";
                Log::write($s, Log::NOTICE);
                break;
            default:
                break;
        }
        return false;
    }

    public static function exception_handle($e) {
        Log::write($e->getMessage() . ' File: ' . $e->getFile() . ' [' . $e->getLine() . ']', Log::EMERG);

        if (Request::getInstance()->isAjax() == true) {
            $json = ['ret' => 500, 'data' => null, 'msg' => $e->getMessage()];
            ajax_return($json);
        }
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
            if (class_exists('_emptyAction')) {
                // 如果定义了_empty操作 则调用
                $class_name = '_emptyAction';
            } else {
                if (Request::getInstance()->isAjax() == true) {
                    $json = ['ret' => 404, 'data' => null, 'msg' => 'Controller 不存在!'];
                    ajax_return($json);
                } else {
                    // 显示 404 错误
                    http_response_code(404);
                    exit();
                }
            }
        }
        $module = new $class_name;
        if (!is_callable([$module, $action])) {
            if (is_callable([$module, '_empty'])) {
                $action = '_empty';
            } else {
                // 显示 404 错误
                if (Request::getInstance()->isAjax() == true) {
                    Log::write("{$module}-{$action}\t不存在！");
                    $json = ['ret' => 404, 'data' => null, 'msg' => 'Action 不存在!'];
                    ajax_return($json);
                } else {
                    // 显示 404 错误
                    http_response_code(404);
                    exit();
                }
            }
        }

        try {
            //执行当前操作
            $method = new ReflectionMethod($module, $action);
            if ($method->isPublic()) {
                $class = new ReflectionClass($module);
                // 前置操作
                if ($class->hasMethod('_before_' . $action)) {
                    $before = $class->getMethod('_before_' . $action);
                    if ($before->isPublic()) {
                        $before->invoke($module);
                    }
                }
                $method->invoke($module);
                // 后置操作
                if ($class->hasMethod('_after_' . $action)) {
                    $after = $class->getMethod('_after_' . $action);
                    if ($after->isPublic()) {
                        $after->invoke($module);
                    }
                }
            } else {
                // 操作方法不是Public 抛出异常
                throw new ReflectionException();
            }
        } catch (ReflectionException $e) {
            if (Request::getInstance()->isAjax() == true) {
                $json = ['ret' => 500, 'data' => null, 'msg' => $e->getTraceAsString()];
                ajax_return($json);
            }
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
