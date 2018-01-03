<?php

namespace framework\core;

class App {

    public static $app_name;
    public static $module_name;
    public static $action_name;

    /**
     * 应用程序初始化
     */
    static public function init() {
        /* 异常处理类 */
        set_exception_handler("\\framework\\core\\App::exception_handle");
        /* 自定义错误处理函数，设置后 error_reporting 将失效。因为要保证 ajax 输出格式，所以必须触发 error_handle */
        set_error_handler("\\framework\\core\\App::error_handle");

        /* 设置默认时区 */
        date_default_timezone_set('Asia/Shanghai');

        /* 注册自动加载 */
        Loader::register();

        //URL调度
        self::dispatch();
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
                self::ajax_return($json);
                break;
            case E_WARNING:
                // 记录到日志
                $errnostr = isset($errortype[$errno]) ? $errortype[$errno] : 'Unknonw';
                $s = "[$errnostr] : $errstr in File $errfile, Line: $errline";
                Log::write($s, Log::WARN);
                break;
            case E_NOTICE:
                // 记录到日志
                $errnostr = isset($errortype[$errno]) ? $errortype[$errno] : 'Unknonw';
                $s = "[$errnostr] : $errstr in File $errfile, Line: $errline";
                Log::write($s, Log::NOTICE);
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
        self::ajax_return($json);
    }

    /**
     * Ajax方式返回数据到客户端
     *
     * @access protected
     * @param mixed $json 要返回的数据
     * @param String $type AJAX返回数据格式
     * @return void
     */
    static public function ajax_return($json, $type = '') {
        switch (strtoupper($type)) {
            case 'JSON' :
                // 返回JSON数据格式到客户端 包含状态信息
                header('Content-Type:application/json; charset=utf-8');
                exit(json_encode($json, JSON_UNESCAPED_UNICODE));
            case 'JSONP':
                // 返回JSON数据格式到客户端 包含状态信息
                header('Content-Type:application/json; charset=utf-8');
                $callback = get('callback', '', 't');
                exit($callback . '(' . json_encode($json, JSON_UNESCAPED_UNICODE) . ');');
            case 'EVAL' :
                // 返回可执行的js脚本
                header('Content-Type:text/html; charset=utf-8');
                exit($json);
            default :
                // 用于扩展其他返回格式数据
                header('Content-Type:application/json; charset=utf-8');
                exit(json_encode($json, JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 分发
     */
    public static function dispatch() {

        if (php_sapi_name() === "cli") {
            /* CLI模式下 index.php module/controller/action/params/... */
            $_SERVER['PATH_INFO'] = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : '';
        } else {
            /* Web 模式 */
            $uri = $_SERVER['REQUEST_URI'];
            if (($pos = strpos($uri, '?')) !== false) {
                $uri = substr($uri, 0, $pos);
            }
            $_SERVER['PATH_INFO'] = self::secure_path($uri);
            unset($uri);
        }

        /*  子域名部署 */
        if (Config::get('APP_SUB_DOMAIN_DEPLOY') && (php_sapi_name() != "cli")) {
            $rules = Config::get('APP_SUB_DOMAIN_RULES');
            /* 完整域名或者IP配置 */
            if (isset($rules[$_SERVER['HTTP_HOST']])) {
                /* 当前完整域名 */
                $rule = $rules[$_SERVER['HTTP_HOST']];
            } else {
                if (strpos(Config::get('APP_DOMAIN_SUFFIX'), '.')) { // com.cn net.cn
                    $domain = array_slice(explode('.', $_SERVER['HTTP_HOST']), 0, -3);
                } else {
                    $domain = array_slice(explode('.', $_SERVER['HTTP_HOST']), 0, -2);
                }
                if (!empty($domain)) {
                    /* 当前完整子域名 */
                    $subDomain = implode('.', $domain);
                    /* 二级域名 */
                    $domain2 = array_pop($domain);
                    /* 存在三级域名 */
                    if ($domain) {
                        $domain3 = array_pop($domain);
                    }
                    /* 子域名 */
                    if (isset($rules[$subDomain])) {
                        $rule = $rules[$subDomain];
                    } elseif (isset($rules['*.' . $domain2]) && !empty($domain3)) {
                        /* 泛三级域名 */
                        $rule = $rules['*.' . $domain2];
                        $panDomain = $domain3;
                    } elseif (isset($rules['*']) && !empty($domain2) && 'www' != $domain2) {
                        /* 泛二级域名 */
                        $rule = $rules['*'];
                        $panDomain = $domain2;
                    }
                }
            }

            if (!empty($rule)) {
                /* 子域名部署规则 '子域名'=>array('分组名/[模块名]','var1=a&var2=b'); */
                $array = explode('/', $rule[0]);
                $module = array_pop($array);
                /* 分析模块名 */
                if (!empty($module)) {
                    $_GET['c'] = $module;
                }
                /* 分析应用名 */
                if (!empty($array)) {
                    $_GET['app'] = array_pop($array);
                }
                /* 传入参数 */
                if (isset($rule[1])) {
                    parse_str($rule[1], $parms);
                    $_GET = array_merge($_GET, $parms);
                }
            }

            /* 检查是否合法 */
            if (empty($_GET['app']) || preg_match('/^[A-Za-z](\/|\w)*$/', $_GET['app']) == false) {
                $_GET['app'] = 'www';
            }

            /* 定义分组应用 */
            self::$app_name = strtolower($_GET['app']);
        } else {
            /* 不是域名部署 */
            /* 默认规则调度URL */
            $paths = explode('/', trim($_SERVER['PATH_INFO'], '/'));

            if (Config::get('APP_GROUP_LIST') && !isset($_GET['app'])) {
                $app = in_array(strtolower($paths[0]), explode(',', strtolower(Config::get('APP_GROUP_LIST')))) ? array_shift($paths) : 'www';
                if (Config::get('APP_GROUP_DENY') && in_array(strtolower($app), explode(',', strtolower(Config::get('APP_GROUP_DENY'))))) {
                    // 禁止直接访问分组
                    exit();
                }
            }
            /* 定义分组应用 */
            self::$app_name = $app;
        }

        /* 载入分组配置 */
        Config::load(APP_PATH . self::$app_name . '/config/');


        if (defined('is_workerman') == true) {
            $uri = $_SERVER['REQUEST_URI'];
            if (($pos = strpos($uri, '?')) !== false) {
                $uri = substr($uri, 0, $pos);
            }
            $_SERVER['PATH_INFO'] = self::secure_path($uri);
            unset($uri);

            /*  子域名部署 */
            if (Config::get('APP_SUB_DOMAIN_DEPLOY')) {
                $rules = Config::get('APP_SUB_DOMAIN_RULES');
                /* 完整域名或者IP配置 */
                if (isset($rules[$_SERVER['SERVER_NAME']])) {
                    /* 当前完整域名 */
                    $rule = $rules[$_SERVER['SERVER_NAME']];
                } else {
                    if (strpos(Config::get('APP_DOMAIN_SUFFIX'), '.')) { // com.cn net.cn
                        $domain = array_slice(explode('.', $_SERVER['SERVER_NAME']), 0, -3);
                    } else {
                        $domain = array_slice(explode('.', $_SERVER['SERVER_NAME']), 0, -2);
                    }
                    if (!empty($domain)) {
                        /* 当前完整子域名 */
                        $subDomain = implode('.', $domain);
                        /* 二级域名 */
                        $domain2 = array_pop($domain);
                        /* 存在三级域名 */
                        if ($domain) {
                            $domain3 = array_pop($domain);
                        }
                        /* 子域名 */
                        if (isset($rules[$subDomain])) {
                            $rule = $rules[$subDomain];
                        } elseif (isset($rules['*.' . $domain2]) && !empty($domain3)) {
                            /* 泛三级域名 */
                            $rule = $rules['*.' . $domain2];
                            $panDomain = $domain3;
                        } elseif (isset($rules['*']) && !empty($domain2) && 'www' != $domain2) {
                            /* 泛二级域名 */
                            $rule = $rules['*'];
                            $panDomain = $domain2;
                        }
                    }
                }

                if (!empty($rule)) {
                    /* 子域名部署规则 '子域名'=>array('分组名/[模块名]','var1=a&var2=b'); */
                    $array = explode('/', $rule[0]);
                    $module = array_pop($array);
                    /* 分析模块名 */
                    if (!empty($module)) {
                        $_GET['c'] = $module;
                    }
                    /* 分析应用名 */
                    if (!empty($array)) {
                        $_GET['app'] = array_pop($array);
                    }
                    /* 传入参数 */
                    if (isset($rule[1])) {
                        parse_str($rule[1], $parms);
                        $_GET = array_merge($_GET, $parms);
                    }
                }

                /* 检查是否合法 */
                if (empty($_GET['app']) || preg_match('/^[A-Za-z](\/|\w)*$/', $_GET['app']) == false) {
                    $_GET['app'] = 'www';
                }

                /* 定义分组应用 */
                self::$app_name = strtolower($_GET['app']);
            } else {
                /* 不是域名部署 */
                /* 默认规则调度URL */
                $paths = explode('/', trim($_SERVER['PATH_INFO'], '/'));

                if (Config::get('APP_GROUP_LIST') && !isset($_GET['app'])) {
                    $app = in_array(strtolower($paths[0]), explode(',', strtolower(Config::get('APP_GROUP_LIST')))) ? array_shift($paths) : 'www';
                    if (Config::get('APP_GROUP_DENY') && in_array(strtolower($app), explode(',', strtolower(Config::get('APP_GROUP_DENY'))))) {
                        // 禁止直接访问分组
                        exit();
                    }
                }

                /* 定义分组应用 */
                self::$app_name = $app;
            }

            /* 载入分组配置 */
            Config::load(APP_PATH . self::$app_name . '/config/');
        }

        // URL后缀
        $_ext = strtolower(pathinfo($_SERVER['PATH_INFO'], PATHINFO_EXTENSION));

        // 去除URL后缀
        $_SERVER['PATH_INFO'] = preg_replace(Config::get('URL_HTML_SUFFIX') ? '/\.(' . trim(Config::get('URL_HTML_SUFFIX'), '.') . ')$/i' : '/\.' . $_ext . '$/i', '', $_SERVER['PATH_INFO']);

        // 检测路由规则 如果没有则按默认规则调度URL
        if (!Route::routerCheck()) {
            /* 默认规则调度URL */
            $paths = explode('/', trim($_SERVER['PATH_INFO'], '/'));
            $var = [];
            if (Config::get('APP_GROUP_LIST') && !isset($_GET['app'])) {
                $var['app'] = in_array(strtolower($paths[0]), explode(',', Config::get('APP_GROUP_LIST'))) ? strtolower(array_shift($paths)) : 'www';
                if (Config::get('APP_GROUP_DENY') && in_array($var['app'], explode(',', Config::get('APP_GROUP_DENY')))) {
                    // 禁止直接访问分组
                    exit();
                }
            }
            if (!isset($_GET['c'])) {
                /* 还没有定义模块名称 */
                $var['c'] = array_shift($paths);
            }
            $var['a'] = array_shift($paths);
            // 解析剩余的URL参数
            preg_replace_callback('/(\w+)\/([^\/]+)/', function($match) use(&$var) {
                $var[$match[1]] = strip_tags($match[2]);
            }, implode('/', $paths));

            $_GET = array_merge($var, $_GET);
        }

        if (empty(self::$app_name)) {
            self::$app_name = strtolower($_GET['app']);
        }

        if (empty($_GET['c'])) {
            $_GET['c'] = 'index';
        }
        if (empty($_GET['a'])) {
            $_GET['a'] = 'index';
        }

        self::$module_name = strtolower($_GET['c']);
        self::$action_name = strtolower($_GET['a']);
    }

    /**
     *  安全路径
     */
    private static function secure_path($path) {
        $path = preg_replace('/[\.]+/', '.', $path);
        $path = preg_replace('/[\/]+/', '/', $path);
        $path = str_replace(array('./', '\'', '"', '<', '>'), '', $path);
        return $path;
    }

    /**
     * 执行应用程序
     * @access public
     * @return void
     */
    static public function exec() {
        $class_name = self::$app_name . "\\action\\" . self::$module_name;
        $action = self::$action_name;

        if (!class_exists($class_name)) {
            if (class_exists('_empty')) {
                /* 如果定义了_empty操作 则调用 */
                $class_name = '_empty';
            } else {
                return ['ret' => 404, 'data' => null, 'msg' => "{$class_name}\\{$action} 不存在!"];
            }
        }

        $module = new $class_name;
        if (!is_callable([$module, $action])) {
            if (is_callable([$module, '_empty'])) {
                $action = '_empty';
            } else {
                return ['ret' => 404, 'data' => null, 'msg' => "{$class_name}\\{$action} 不存在!"];
            }
        }

        try {
            /* 执行当前操作 */
            $method = new \ReflectionMethod($module, $action);
            if ($method->isPublic()) {
                $class = new \ReflectionClass($module);
                return $method->invoke($module);
            } else {
                /* 操作方法不是Public 抛出异常 */
                throw new \ReflectionException();
            }
        } catch (\ReflectionException $e) {
            return ['ret' => 500, 'data' => null, 'msg' => $e->getTraceAsString()];
        }

        return false;
    }

    /**
     * 运行应用实例 入口文件使用的快捷方法
     * @access public
     * @return void
     */
    static public function run() {
        App::init();
        return App::exec();
    }

}
