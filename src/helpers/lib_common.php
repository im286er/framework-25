<?php

/**
 * 字符串命名风格转换
 * type 0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
 * @param string $name 字符串
 * @param integer $type 转换类型
 * @return string
 */
function parse_name($name, $type = 0) {
    if ($type) {
        return ucfirst(preg_replace_callback('/_([a-zA-Z])/', function($match) {
                    return strtoupper($match[1]);
                }, $name));
    } else {
        return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
    }
}

/**
 * 循环创建目录
 * @param type $dir
 * @param type $mode
 * @return boolean
 */
function mk_dir($dir, $mode = 0755) {
    if (empty($dir)) {
        return false;
    }
    if (is_dir($dir) || @mkdir($dir, $mode)) {
        return true;
    }
    if (!mk_dir(dirname($dir), $mode)) {
        return false;
    }
    return @mkdir($dir, $mode);
}

/**
 * 目录列表
 *
 * @param	string	$dir		路径
 * @param	int		$parentid	父id
 * @param	array	$dirs		传入的目录
 * @return	array	返回目录列表
 */
function dir_tree($dir, $parentid = 0, $dirs = array()) {
    static $id;
    if ($parentid == 0) {
        $id = 0;
    }
    $list = glob($dir . '*');
    foreach ($list as $v) {
        if (is_dir($v)) {
            $id++;
            $dirs[$id] = array('id' => $id, 'parentid' => $parentid, 'name' => basename($v), 'dir' => $v . '/');
            $dirs = dir_tree($v . '/', $id, $dirs);
        }
    }
    return $dirs;
}

/**
 * 转化 \ 为 /
 *
 * @param	string	$path	路径
 * @return	string	路径
 */
function dir_path($path) {
    $path = str_replace('\\', '/', $path);
    if (substr($path, -1) != '/')
        $path = $path . '/';
    return $path;
}

/**
 * 列出目录下所有文件
 *
 * @param	string	$path		路径
 * @param	string	$exts		扩展名
 * @param	array	$list		增加的文件列表
 * @return	array	所有满足条件的文件
 */
function dir_list($path, $exts = '', $list = array()) {
    $path = dir_path($path);
    $files = glob($path . '*');
    foreach ($files as $v) {
        if (!$exts || pathinfo($v, PATHINFO_EXTENSION) == $exts) {
            $list[] = $v;
            if (is_dir($v)) {
                $list = dir_list($v, $exts, $list);
            }
        }
    }
    return $list;
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
 * 打印一行
 * @param $msg
 */
function print_line($msg) {
    echo ("{$msg} \n");
}

/**
 * 终端高亮打印绿色
 * @param $message
 */
function print_ok($message) {
    printf("\033[32m\033[1m{$message}\033[0m\n");
}

/**
 * 终端高亮打印红色
 * @param $message
 */
function print_error($message) {
    printf("\033[31m\033[1m{$message}\033[0m\n");
}

/**
 * 终端高亮打印黄色
 * @param $message
 */
function print_warning($message) {
    printf("\033[33m\033[1m{$message}\033[0m\n");
}

/**
 * 获取和设置语言定义(不区分大小写)
 * @param string|array $name 语言变量
 * @param mixed $value 语言值或者变量
 * @return mixed
 */
function L($name = null, $value = null) {
    static $_lang = array();
    // 空参数返回所有定义
    if (empty($name))
        return $_lang;
    // 判断语言获取(或设置)
    // 若不存在,直接返回全大写$name
    if (is_string($name)) {
        $name = strtoupper($name);
        if (is_null($value)) {
            return isset($_lang[$name]) ? $_lang[$name] : $name;
        } elseif (is_array($value)) {
            // 支持变量
            $replace = array_keys($value);
            foreach ($replace as &$v) {
                $v = '{$' . $v . '}';
            }
            return str_replace($replace, $value, isset($_lang[$name]) ? $_lang[$name] : $name);
        }
        $_lang[$name] = $value; // 语言定义
        return null;
    }
    // 批量定义
    if (is_array($name))
        $_lang = array_merge($_lang, array_change_key_case($name, CASE_UPPER));
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
    $tpl = view::getInstance();

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

    $tpl->display('dispatch_jump.tpl.php', __DIR__ . '/../tpl/');
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
    $handler = SessionDriver::getInstance();
    session_set_save_handler($handler, true);

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
 * 不区分大小写的in_array实现
 * @param type $value
 * @param type $array
 * @return type
 */
function in_array_case($value, $array) {
    return in_array(strtolower($value), array_map('strtolower', $array));
}

/**
 * 安全过滤函数
 *
 * @param $string
 * @return string
 */
function safe_replace($string) {
    if (empty($string)) {
        return '';
    }
    $string = str_replace('%20', '', $string);
    $string = str_replace('%27', '', $string);
    $string = str_replace('%2527', '', $string);
    $string = str_replace('*', '', $string);
    $string = str_replace('"', '&quot;', $string);
    $string = str_replace("'", '', $string);
    $string = str_replace('"', '', $string);
    $string = str_replace(';', '', $string);
    $string = str_replace('<', '&lt;', $string);
    $string = str_replace('>', '&gt;', $string);
    $string = str_replace("{", '', $string);
    $string = str_replace('}', '', $string);
    $string = str_replace('\\', '', $string);
    return $string;
}

/*
  $data = array();
  $data[] = array('volume' => 67, 'edition' => 2);
  $data[] = array('volume' => 86, 'edition' => 1);
  $data[] = array('volume' => 85, 'edition' => 6);
  $data[] = array('volume' => 98, 'edition' => 2);
  $data[] = array('volume' => 86, 'edition' => 6);
  $data[] = array('volume' => 67, 'edition' => 7);
  arrlist_multisort($data, 'edition', TRUE);
 */

// 对多维数组排序
function arrlist_multisort(&$arrlist, $col, $asc = TRUE) {
    $colarr = array();
    foreach ($arrlist as $k => $arr) {
        $colarr[$k] = $arr[$col];
    }
    $asc = $asc ? SORT_ASC : SORT_DESC;
    array_multisort($colarr, $asc, $arrlist);
    return $arrlist;
}

// 对数组进行查找，排序，筛选，只支持一种条件排序
function arrlist_cond_orderby($arrlist, $cond = array(), $orderby = array(), $page = 1, $pagesize = 20) {
    $resultarr = array();
    // 根据条件，筛选结果
    if ($cond) {
        foreach ($arrlist as $key => $val) {
            $ok = TRUE;
            foreach ($cond as $k => $v) {
                if (!isset($val[$k]) || $val[$k] != $v) {
                    $ok = FALSE;
                    break;
                }
            }
            if ($ok)
                $resultarr[$key] = $val;
        }
    } else {
        $resultarr = $arrlist;
    }
    if ($orderby) {
        list($k, $v) = each($orderby);
        arrlist_multisort($resultarr, $k, $v == 1);
    }

    $start = ($page - 1) * $pagesize;
    return array_slice($resultarr, $start, $pagesize);
}

// 从一个二维数组中取出一个 key=>value 格式的一维数组
function arrlist_key_values($arrlist, $key, $value) {
    $return = array();
    if ($key) {
        foreach ((array) $arrlist as $arr) {
            $return[$arr[$key]] = $arr[$value];
        }
    } else {
        foreach ((array) $arrlist as $arr) {
            $return[] = $arr[$value];
        }
    }
    return $return;
}

// 将 key 更换为某一列的值，在对多维数组排序后，数字key会丢失，需要此函数
function arrlist_change_key(&$arrlist, $key, $pre = '') {
    $return = array();
    if (empty($arrlist))
        return $return;
    foreach ($arrlist as &$arr) {
        $return[$pre . '' . $arr[$key]] = $arr;
    }
    $arrlist = $return;
}

// 根据某一列的值进行 chunk
function arrlist_chunk($arrlist, $key) {
    $r = array();
    if (empty($arrlist))
        return $r;
    foreach ($arrlist as &$arr) {
        !isset($r[$arr[$key]]) AND $r[$arr[$key]] = array();
        $r[$arr[$key]][] = $arr;
    }
    return $return;
}

/**
 * 获取一个可用的图片服务器地址
 */
function get_http_image_server_url() {
    // 图片服务器地址
    $_config = explode(',', C('static_site_url'));
    $r = floor(mt_rand(0, count($_config) - 1));   // 每次随机
    $img_server = isset($_config[$r]) ? $_config[$r] : $_config[0];
    return $img_server;
}

/**
 * t函数用于过滤标签，输出没有html的干净的文本
 * @param string text 文本内容
 * @return string 处理后内容
 */
function t($text) {
    if (empty($text)) {
        return '';
    }
    $text = nl2br($text);
    $text = real_strip_tags($text);
    $text = addslashes($text);
    $text = trim($text);
    return $text;
}

function real_strip_tags($str, $allowable_tags = "") {
    $str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
    return strip_tags($str, $allowable_tags);
}

/**
 * 输出安全的html
 * @param text $text
 * @param text $tags
 */
function h($text, $tags = null) {
    $text = trim($text);
    if (empty($text)) {
        return '';
    }
    //完全过滤注释
    $text = preg_replace('/<!--?.*-->/', '', $text);
    //完全过滤动态代码
    $text = preg_replace('/<\?|\?' . '>/', '', $text);
    //完全过滤js
    $text = preg_replace('/<script?.*\/script>/', '', $text);

    $text = str_replace('[', '&#091;', $text);
    $text = str_replace(']', '&#093;', $text);
    $text = str_replace('|', '&#124;', $text);
    //过滤换行符
    $text = preg_replace('/\r?\n/', '', $text);
    //br
    $text = preg_replace('/<br(\s\/)?' . '>/i', '[br]', $text);
    $text = preg_replace('/(\[br\]\s*){10,}/i', '[br]', $text);
    //过滤危险的属性，如：过滤on事件lang js
    while (preg_match('/(<[^><]+)( lang|on|action|background|codebase|dynsrc|lowsrc)[^><]+/i', $text, $mat)) {
        $text = str_replace($mat[0], $mat[1], $text);
    }
    while (preg_match('/(<[^><]+)(window\.|javascript:|js:|about:|file:|document\.|vbs:|cookie)([^><]*)/i', $text, $mat)) {
        $text = str_replace($mat[0], $mat[1] . $mat[3], $text);
    }
    if (empty($tags)) {
        $tags = 'table|td|th|tr|i|b|u|strong|img|p|br|div|strong|em|ul|ol|li|dl|dd|dt|a';
    }
    //允许的HTML标签
    $text = preg_replace('/<(' . $tags . ')( [^><\[\]]*)>/i', '[\1\2]', $text);
    //过滤多余html
    $text = preg_replace('/<\/?(html|head|meta|link|base|basefont|body|bgsound|title|style|script|form|iframe|frame|frameset|applet|id|ilayer|layer|name|script|style|xml)[^><]*>/i', '', $text);
    //过滤合法的html标签
    while (preg_match('/<([a-z]+)[^><\[\]]*>[^><]*<\/\1>/i', $text, $mat)) {
        $text = str_replace($mat[0], str_replace('>', ']', str_replace('<', '[', $mat[0])), $text);
    }
    //转换引号
    while (preg_match('/(\[[^\[\]]*=\s*)(\"|\')([^\2=\[\]]+)\2([^\[\]]*\])/i', $text, $mat)) {
        $text = str_replace($mat[0], $mat[1] . '|' . $mat[3] . '|' . $mat[4], $text);
    }
    //过滤错误的单个引号
    while (preg_match('/\[[^\[\]]*(\"|\')[^\[\]]*\]/i', $text, $mat)) {
        $text = str_replace($mat[0], str_replace($mat[1], '', $mat[0]), $text);
    }
    //转换其它所有不合法的 < >
    $text = str_replace('<', '&lt;', $text);
    $text = str_replace('>', '&gt;', $text);
    $text = str_replace('"', '&quot;', $text);
    //反转换
    $text = str_replace('[', '<', $text);
    $text = str_replace(']', '>', $text);
    $text = str_replace('|', '"', $text);
    //过滤多余空格
    $text = str_replace('  ', ' ', $text);
    return $text;
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
 * XML编码
 * @param mixed $data 数据
 * @param string $root 根节点名
 * @param string $item 数字索引的子节点名
 * @param string $attr 根节点属性
 * @param string $id   数字索引子节点key转换的属性名
 * @param string $encoding 数据编码
 * @return string
 */
function xml_encode($data, $root = 'think', $item = 'item', $attr = '', $id = 'id', $encoding = 'utf-8') {
    if (is_array($attr)) {
        $_attr = array();
        foreach ($attr as $key => $value) {
            $_attr[] = "{$key}=\"{$value}\"";
        }
        $attr = implode(' ', $_attr);
    }
    $attr = trim($attr);
    $attr = empty($attr) ? '' : " {$attr}";
    $xml = "<?xml version=\"1.0\" encoding=\"{$encoding}\"?>";
    $xml .= "<{$root}{$attr}>";
    $xml .= data_to_xml($data, $item, $id);
    $xml .= "</{$root}>";
    return $xml;
}

/**
 * 数据XML编码
 * @param mixed  $data 数据
 * @param string $item 数字索引时的节点名称
 * @param string $id   数字索引key转换为的属性名
 * @return string
 */
function data_to_xml($data, $item = 'item', $id = 'id') {
    $xml = $attr = '';
    foreach ($data as $key => $val) {
        if (is_numeric($key)) {
            $id && $attr = " {$id}=\"{$key}\"";
            $key = $item;
        }
        $xml .= "<{$key}{$attr}>";
        $xml .= (is_array($val) || is_object($val)) ? data_to_xml($val, $item, $id) : $val;
        $xml .= "</{$key}>";
    }
    return $xml;
}

// --------------------------------------------------------------------

/**
 * Remove Invisible Characters
 *
 * This prevents sandwiching null characters
 * between ascii characters, like Java\0script.
 *
 * @access	public
 * @param	string
 * @return	string
 */
if (!function_exists('remove_invisible_characters')) {

    function remove_invisible_characters($str, $url_encoded = TRUE) {
        $non_displayables = [];

        // every control character except newline (dec 10)
        // carriage return (dec 13), and horizontal tab (dec 09)

        if ($url_encoded) {
            $non_displayables[] = '/%0[0-8bcef]/'; // url encoded 00-08, 11, 12, 14, 15
            $non_displayables[] = '/%1[0-9a-f]/'; // url encoded 16-31
        }

        $non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S'; // 00-08, 11, 12, 14-31, 127

        do {
            $str = preg_replace($non_displayables, '', $str, -1, $count);
        } while ($count);

        return $str;
    }

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
