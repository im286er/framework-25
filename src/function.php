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
        case 'XML':
            // 返回xml格式数据
            header('Content-Type:text/xml; charset=utf-8');
            exit(xml_encode($data));
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
