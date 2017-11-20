<?php

/**
 * 获取和设置配置参数 支持批量定义
 * @param string|array $name 配置变量
 * @param mixed $value 配置值
 * @param string $range 作用域
 * @return type
 */
function C($name = '', $value = null, $range = '') {
    if (is_null($value) && is_string($name)) {
        return Config::get($name, $range);
    } else {
        return Config::set($name, $value, $range);
    }
}

/**
 * 获取输入参数 支持过滤和默认值
 * 使用方法:
 * <code>
 * I('id',0); 获取id参数 自动判断get或者post
 * I('post.name','','htmlspecialchars'); 获取$_POST['name']
 * I('get.'); 获取$_GET
 * </code>
 *
 * @param string $name
 *            变量的名称 支持指定类型
 * @param mixed $default
 *            不存在的时候默认值
 * @param mixed $filter
 *            参数过滤方法
 * @return mixed
 */
function I($name, $default = '', $filter = null) {
    if (strpos($name, '.')) { // 指定参数来源
        list ($method, $name) = explode('.', $name);
    } else { // 默认为自动判
        $method = 'param';
    }
    switch (strtolower($method)) {
        case 'get':
            $input = & $_GET;
            break;
        case 'post':
            $input = & $_POST;
            break;
        case 'put':
            parse_str(file_get_contents('php://input'), $input);
            break;
        case 'param':
            switch ($_SERVER['REQUEST_METHOD']) {
                case 'POST':
                    $input = $_POST;
                    break;
                case 'PUT':
                    parse_str(file_get_contents('php://input'), $input);
                    break;
                default:
                    $input = $_GET;
            }
            break;
        case 'request':
            $input = & $_REQUEST;
            break;
        case 'session':
            $input = & $_SESSION;
            break;
        case 'cookie':
            $input = & $_COOKIE;
            break;
        case 'server':
            $input = & $_SERVER;
            break;
        case 'globals':
            $input = & $GLOBALS;
            break;
        default:
            return NULL;
    }

    if (empty($name)) { // 获取全部变量
        $data = $input;
    } elseif (isset($input[$name])) { // 取值操作
        $data = $input[$name];
        $filters = isset($filter) ? $filter : null;
        if ($filters) {
            $data = $filters($data); // 参数过滤
        }
    } else { // 变量默认值
        $data = isset($default) ? $default : NULL;
    }
    return $data;
}

/**
 * $_GET简写
 * @param string $name
 * @param string $val
 * @param type $filter
 * @return <mixed, NULL, unknown>
 */
function get($name, $val = '', $filter = null) {
    return I("get." . $name, $val, $filter);
}

/**
 * $_POST简写
 * @param string $name
 * @param string $val
 * @param type $filter
 * @return <mixed, NULL, unknown>
 */
function post($name, $val = '', $filter = null) {
    return I("post." . $name, $val, $filter);
}

/**
 *  过滤请求变量
 */
function transcribe($aList) {
    $gpcList = [];
    if (is_array($aList)) {
        foreach ($aList as $key => $value) {
            if (is_array($value)) {
                $decodedKey = addslashes($key);
                $decodedValue = transcribe($value);
            } else {
                $decodedKey = addslashes($key);
                $decodedValue = addslashes($value);
            }
            $gpcList[$decodedKey] = $decodedValue;
        }
    }
    return $gpcList;
}

/**
 * 反转义
 * @param type $aList
 * @return type
 */
function strip_transcribe($aList) {
    $gpcList = [];
    if (is_array($aList)) {
        foreach ($aList as $key => $value) {
            if (is_array($value)) {
                $decodedKey = stripcslashes($key);
                $decodedValue = strip_transcribe($value);
            } else {
                $decodedKey = stripcslashes($key);
                $decodedValue = stripcslashes($value);
            }
            $gpcList[$decodedKey] = $decodedValue;
        }
    }
    return $gpcList;
}

/**
 * Ajax方式返回数据到客户端
 *
 * @access protected
 * @param mixed $json 要返回的数据
 * @param String $type AJAX返回数据格式
 * @return void
 */
function ajax_return($json, $type = '') {
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
 * 优化的require_once
 * @param string $filename 文件地址
 * @return boolean
 */
function require_cache($filename) {
    static $_importFiles = [];
    if (!isset($_importFiles[$filename])) {
        if (is_file($filename) && file_exists($filename)) {
            require $filename;
            $_importFiles[$filename] = true;
        } else {
            $_importFiles[$filename] = false;
        }
    }
    return $_importFiles[$filename];
}

/**
 * 浏览器友好的变量输出
 * @param $var
 */
function dump($var) {
    if (php_sapi_name() === "cli") {
        var_dump($var);
    } else {
        echo '<pre style="text-align:left">';
        var_dump($var);
        echo '</pre>';
    }
    return null;
}

/**
 * URL组装 支持不同URL模式
 * @param string $url URL表达式，格式：'[分组/模块/操作#锚点@域名]?参数1=值1&参数2=值2...'
 * @param string|array $vars 传入的参数，支持数组和字符串
 * @param string $suffix 伪静态后缀，默认为true表示获取配置值
 * @param boolean $domain 是否显示域名
 * @return string
 */
function U($url = '', $vars = '', $suffix = true, $domain = false, $common_param = false) {
    return Url::build($url, $vars, $suffix, $domain, $common_param);
}

/**
 * Js 版 URL 跳转
 * @param string $url       将要跳转的URL地址
 * @param integer $time     跳转前的等待时间（秒）
 * @param string $msg       跳转前的提示信息
 * @param type $msg_type    消息类型， 0消息，1错误
 */
function redirect($url, $time = 0, $msg = '', $msg_type = 0) {
    $tpl = View::getInstance();

    //发送成功信息
    if ($msg_type == 0) {
        $tpl->assign('message', $msg); // 提示信息
    } else {
        $tpl->assign('error', $msg); // 提示信息
    }
    // 成功操作后默认停留1秒
    if (0 === $time) {
        $tpl->assign('waitSecond', '1');
    } else {
        $tpl->assign('waitSecond', $time);
    }
    // 默认操作成功自动返回操作前页面
    if (empty($url)) {
        $tpl->assign("jumpUrl", $_SERVER["HTTP_REFERER"]);
    } else {
        $tpl->assign("jumpUrl", $url);
    }

    $tpl->display('dispatch_jump.tpl.php', __DIR__ . '/tpl/');
    exit;
}

/**
 * Cookie 设置、获取、清除
 * 1 获取cookie: cookie('name')
 * 2 清空当前设置前缀的所有cookie: cookie(null)
 * 3 删除指定前缀所有cookie: cookie(null,'think_') | 注：前缀将不区分大小写
 * 4 设置cookie: cookie('name','value') | 指定保存时间: cookie('name','value',3600)
 * 5 删除cookie: cookie('name',null)

 * $option 可用设置prefix,expire,path,domain,httponly
 * 支持数组形式对参数设置:cookie('name','value',array('expire'=>1,'prefix'=>'think_','httponly'=>true))
 * 支持query形式字符串对参数设置:cookie('name','value','prefix=tp_&expire=10000')
 */
function cookie($name, $value = '', $option = null) {
    // 默认设置
    $config = [
        'prefix' => 'vvjob_', // cookie 名称前缀
        'expire' => 1296000, // cookie 保存时间 15 天
        'path' => '/', // cookie 保存路径
        'domain' => '', // cookie 有效域名
        'secure' => false,
        'httponly' => false
    ];
    // 参数设置(会覆盖黙认设置)
    if (!empty($option)) {
        if (is_numeric($option)) {
            $option = ['expire' => $option];
        } elseif (is_string($option)) {
            parse_str($option, $option);
        }
        $config = array_merge($config, array_change_key_case($option));
    }
    /* 清除指定前缀的所有cookie */
    if (is_null($name)) {
        if (empty($_COOKIE)) {
            return;
        }
        /* 要删除的cookie前缀，不指定则删除config设置的指定前缀 */
        $prefix = empty($value) ? $config['prefix'] : $value;
        if (!empty($prefix)) {
            /* 如果前缀为空字符串将不作处理直接返回 */
            foreach ($_COOKIE as $key => $val) {
                if (0 === stripos($key, $prefix)) {
                    setcookie($key, '', TIMESTAMP - 3600, $config['path'], $config['domain'], $config['secure'], $config['httponly']);
                    unset($_COOKIE[$key]);
                }
            }
        }
        /* 无差别清除 cookie */
        if (!empty($_COOKIE)) {
            foreach ($_COOKIE as $key => $val) {
                setcookie($key, '', TIMESTAMP - 3600, $config['path'], $config['domain'], $config['secure'], $config['httponly']);
                unset($_COOKIE[$key]);
            }
        }
        return;
    }
    $name = $config['prefix'] . $name;
    if ('' === $value) {
        return isset($_COOKIE[$name]) ? $_COOKIE[$name] : null; // 获取指定Cookie
    } else {
        if (is_null($value)) {
            setcookie($name, '', TIMESTAMP - 3600, $config['path'], $config['domain'], $config['secure'], $config['httponly']);
            unset($_COOKIE[$name]); // 删除指定cookie
        } else {
            // 设置cookie
            $expire = !empty($config['expire']) ? TIMESTAMP + intval($config['expire']) : 0;
            setcookie($name, $value, $expire, $config['path'], $config['domain'], $config['secure'], $config['httponly']);
            $_COOKIE[$name] = $value;
        }
    }
}

/**
 * session管理函数
 * @param string $name session名称
 * @param mixed  $value session值
 * @return mixed
 */
function session($name, $value = '') {
    if ('' === $value) {
        if (0 === strpos($name, '[')) { // session 操作
            if ('[pause]' == $name) { // 暂停session
                session_write_close();
            } elseif ('[start]' == $name) { // 启动session
                session_start();
            } elseif ('[destroy]' == $name) { // 销毁session
                $_SESSION = [];
                session_unset();
            } elseif ('[regenerate]' == $name) { // 重新生成id
                session_regenerate_id();
            }
        } elseif (0 === strpos($name, '?')) { // 检查session
            $name = substr($name, 1);
            return isset($_SESSION[$name]);
        } elseif (is_null($name)) { // 清空session
            $_SESSION = [];
        } else {
            return isset($_SESSION[$name]) ? $_SESSION[$name] : null;
        }
    } elseif (is_null($value)) { // 删除session
        if ('[session_id]' == $name) { // session_id
            if (isset($_REQUEST['PHPSESSID'])) {
                session_id($_REQUEST['PHPSESSID']);
            }
        } else {
            unset($_SESSION[$name]);
        }
    } else { // 设置session
        if ('[session_id]' == $name) { // session_id
            if (!empty($value)) {
                session_id($value);
            }
        } else {
            $_SESSION[$name] = $value;
        }
    }
}

/**
 * 渲染输出Widget
 * @param string $name Widget名称
 * @param array $data 传人的参数
 * @param boolean $return 是否返回内容
 * @return void
 */
function W($name, $data = [], $return = false) {
    $class_name = $name . 'Widget';

    if (class_exists($class_name)) {
        $widget = new $class_name;
        $content = $widget->render($data);
        if ($return) {
            return $content;
        } else {
            echo $content;
        }
    } else {
        if ($return) {
            return false;
        } else {
            echo '';
        }
    }
}

/**
 * 根据PHP各种类型变量生成唯一标识号
 * @param mixed $mix 变量
 * @return string
 */
function to_guid_string($mix) {
    if (is_object($mix)) {
        return spl_object_hash($mix);
    } elseif (is_resource($mix)) {
        $mix = get_resource_type($mix) . strval($mix);
    } else {
        $mix = serialize($mix);
    }
    return md5($mix);
}

/**
 * 获得一个延迟执行curl的类
 * @param $url 请求url地址
 * @param $options = array(),
 * 		header:头信息(Array),
 * 		proxy_url,
 * 		timeout:超时时间，可以小于1
 * 		post_data: string|array post数据
 * @return CurlFuture\HttpFuture
 * @author fang
 * @version 2015年11月25日09:45:05
 */
function curl_future($url, $options = array()) {
    return new HttpFuture($url, $options);
}

/**
 * 程序执行时间
 *
 * @return	int	单位ms
 */
function execute_time() {
    return ((microtime(true) - START_TIME) * 1000);
}

/**
 * 程序执行占用内存
 * @return int 　　单位 kb
 */
function execute_mem() {
    return number_format((memory_get_usage() - START_MEM) / 1024);
}

/**
 * 字符串命名风格转换
 * type 0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
 * @param string  $name 字符串
 * @param integer $type 转换类型
 * @param bool    $ucfirst 首字母是否大写（驼峰规则）
 * @return string
 */
function parse_name($name, $type = 0, $ucfirst = true) {
    if ($type) {
        $name = preg_replace_callback('/_([a-zA-Z])/', function ($match) {
            return strtoupper($match[1]);
        }, $name);
        return $ucfirst ? ucfirst($name) : lcfirst($name);
    } else {
        return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
    }
}
