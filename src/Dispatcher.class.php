<?php

/**
 * 调度
 */
class Dispatcher {

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
        if (C('APP_SUB_DOMAIN_DEPLOY') && (php_sapi_name() != "cli")) {
            $rules = C('APP_SUB_DOMAIN_RULES');
            /* 完整域名或者IP配置 */
            if (isset($rules[$_SERVER['HTTP_HOST']])) {
                /* 当前完整域名 */
                define('APP_DOMAIN', $_SERVER['HTTP_HOST']);
                $rule = $rules[APP_DOMAIN];
            } else {
                if (strpos(C('APP_DOMAIN_SUFFIX'), '.')) { // com.cn net.cn
                    $domain = array_slice(explode('.', $_SERVER['HTTP_HOST']), 0, -3);
                } else {
                    $domain = array_slice(explode('.', $_SERVER['HTTP_HOST']), 0, -2);
                }
                if (!empty($domain)) {
                    $subDomain = implode('.', $domain);
                    /* 当前完整子域名 */
                    define('SUB_DOMAIN', $subDomain);
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
            define('GROUP_NAME', strtolower($_GET['app']));
            /* 载入应用路由 */
            Config::load(APP_PATH . GROUP_NAME . '/routes.php');
        }

        // URL后缀
        define('__EXT__', strtolower(pathinfo($_SERVER['PATH_INFO'], PATHINFO_EXTENSION)));
        // 去除URL后缀
        $_SERVER['PATH_INFO'] = preg_replace(C('URL_HTML_SUFFIX') ? '/\.(' . trim(C('URL_HTML_SUFFIX'), '.') . ')$/i' : '/\.' . __EXT__ . '$/i', '', $_SERVER['PATH_INFO']);

        // 检测路由规则 如果没有则按默认规则调度URL
        if (!Route::routerCheck()) {
            $paths = explode('/', trim($_SERVER['PATH_INFO'], '/'));
            $var = [];
            if (!isset($_GET['app'])) {
                $var['app'] = $paths[0] ? array_shift($paths) : 'www';
                $var['app'] = strtolower($var['app']);
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

        /* 没有开始子部署 检查转入是否合法 */
        if (empty($_GET['app']) || preg_match('/^[A-Za-z](\/|\w)*$/', $_GET['app']) == false) {
            $_GET['app'] = 'www';
        }

        if (!defined('GROUP_NAME')) {
            define('GROUP_NAME', strtolower($_GET['app']));
        }

        /* 加载公共方法 */
        require_cache(APP_PATH . GROUP_NAME . '/function.php');

        /* 引入应用配置文件 */
        Config::load(APP_PATH . GROUP_NAME . '/config.php');

        /* 安全检测 */
        if (empty($_GET['c'])) {
            $_GET['c'] = 'default';
        }
        if (empty($_GET['a'])) {
            $_GET['a'] = 'index';
        }

        define('MODULE_NAME', strtolower($_GET['c']));
        define('ACTION_NAME', strtolower($_GET['a']));
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

}
