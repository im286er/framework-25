<?php

namespace framework\core;

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
            define('GROUP_NAME', strtolower($_GET['app']));
            /* 载入分组配置 */
            Config::load(APP_PATH . GROUP_NAME . '/config/');
        } else {
            /* 不是域名部署 */
            /* 默认规则调度URL */
            $paths = explode('/', trim($_SERVER['PATH_INFO'], '/'));

            if (Config::get('APP_GROUP_LIST') && !isset($_GET['app'])) {
                $app = in_array(strtolower($paths[0]), explode(',', strtolower(Config::get('APP_GROUP_LIST')))) ? array_shift($paths) : 'www';
                if (Config::get('APP_GROUP_DENY') && in_array(strtolower($app), explode(',', strtolower(Config::get('APP_GROUP_DENY'))))) {
                    // 禁止直接访问分组
                    exit;
                }
            }

            /* 定义分组应用 */
            define('GROUP_NAME', $app);
            /* 载入分组配置 */
            Config::load(APP_PATH . GROUP_NAME . '/config/');
        }

        // URL后缀
        define('__EXT__', strtolower(pathinfo($_SERVER['PATH_INFO'], PATHINFO_EXTENSION)));
        // 去除URL后缀
        $_SERVER['PATH_INFO'] = preg_replace(Config::get('URL_HTML_SUFFIX') ? '/\.(' . trim(Config::get('URL_HTML_SUFFIX'), '.') . ')$/i' : '/\.' . __EXT__ . '$/i', '', $_SERVER['PATH_INFO']);

        // 检测路由规则 如果没有则按默认规则调度URL
        if (!Route::routerCheck()) {
            /* 默认规则调度URL */
            $paths = explode('/', trim($_SERVER['PATH_INFO'], '/'));
            $var = [];
            if (Config::get('APP_GROUP_LIST') && !isset($_GET['app'])) {
                $var['app'] = in_array(strtolower($paths[0]), explode(',', strtolower(Config::get('APP_GROUP_LIST')))) ? array_shift($paths) : 'www';
                if (Config::get('APP_GROUP_DENY') && in_array(strtolower($var['app']), explode(',', strtolower(Config::get('APP_GROUP_DENY'))))) {
                    // 禁止直接访问分组
                    exit;
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

        if (!defined('GROUP_NAME')) {
            define('GROUP_NAME', strtolower($_GET['app']));
        }

        if (empty($_GET['c'])) {
            $_GET['c'] = 'index';
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
