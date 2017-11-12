<?php

class Url {

    // 解析URL后缀
    public static function parseSuffix($suffix) {
        if ($suffix) {
            $suffix = true === $suffix ? Config::get('URL_HTML_SUFFIX') : $suffix;
            if ($pos = strpos($suffix, '|')) {
                $suffix = substr($suffix, 0, $pos);
            }
        }
        return (empty($suffix) || 0 === strpos($suffix, '.')) ? $suffix : '.' . $suffix;
    }

    /**
     * URL组装 支持不同URL模式
     * @param string $url URL表达式，格式：'[分组/模块/操作#锚点@域名]?参数1=值1&参数2=值2...'
     * @param string|array $vars 传入的参数，支持数组和字符串
     * @param string $suffix 伪静态后缀，默认为true表示获取配置值
     * @param boolean $domain 是否显示域名
     * @param boolean $common_param 常见参数形式
     * @return string
     */
    public static function build($url = '', $vars = '', $suffix = true, $domain = false, $common_param = false) {
        // 解析URL
        $info = parse_url($url);
        $url = !empty($info['path']) ? $info['path'] : ACTION_NAME;
        if (isset($info['fragment'])) { // 解析锚点
            $anchor = $info['fragment'];
            if (false !== strpos($anchor, '?')) { // 解析参数
                list($anchor, $info['query']) = explode('?', $anchor, 2);
            }
            if (false !== strpos($anchor, '@')) { // 解析域名
                list($anchor, $host) = explode('@', $anchor, 2);
            }
        } elseif (false !== strpos($url, '@')) { // 解析域名
            list($url, $host) = explode('@', $info['path'], 2);
        }
        // 解析子域名
        if (isset($host)) {
            $domain = $host . (strpos($host, '.') ? '' : strstr($_SERVER['HTTP_HOST'], '.'));
        } elseif ($domain === true) {
            $domain = $_SERVER['HTTP_HOST'];
            if (C('APP_SUB_DOMAIN_DEPLOY')) { // 开启子域名部署
                $domain = $domain == 'localhost' ? 'localhost' : 'www' . strstr($_SERVER['HTTP_HOST'], '.');
                // '子域名'=>array('项目[/分组]');
                foreach (C('APP_SUB_DOMAIN_RULES') as $key => $rule) {
                    if (false === strpos($key, '*') && 0 === strpos($url, $rule[0])) {
                        $domain = $key . strstr($domain, '.'); // 生成对应子域名
                        $url = substr_replace($url, '', 0, strlen($rule[0]));
                        break;
                    }
                }
            }
        }

        // 解析参数
        if (is_string($vars)) { // aaa=1&bbb=2 转换成数组
            parse_str($vars, $vars);
        } elseif (!is_array($vars)) {
            $vars = array();
        }
        if (isset($info['query'])) { // 解析地址里面参数 合并到vars
            parse_str($info['query'], $params);
            $vars = array_merge($params, $vars);
        }

        // URL组装
        $depr = '/';
        if ($url) {
            if (0 === strpos($url, '/')) {// 定义路由
                $route = true;
                $url = substr($url, 1);
                if ('/' != $depr) {
                    $url = str_replace('/', $depr, $url);
                }
            } else {
                if ('/' != $depr) { // 安全替换
                    $url = str_replace('/', $depr, $url);
                }
                // 解析分组、模块和操作
                $url = trim($url, $depr);
                $path = explode($depr, $url);
                $var = array();
                $var['a'] = !empty($path) ? array_pop($path) : ACTION_NAME;
                $var['c'] = !empty($path) ? array_pop($path) : MODULE_NAME;

                if (!C('APP_SUB_DOMAIN_DEPLOY')) {
                    if (!empty($path)) {
                        $group = array_pop($path);
                        $var['app'] = $group;
                    } else {
                        if (GROUP_NAME != 'www') {
                            $var['app'] = GROUP_NAME;
                        }
                    }
                }
            }
        }

        if (isset($route)) {
            $url = '/' . rtrim($url, $depr);
        } else {
            $url = '/' . implode($depr, array_reverse($var));
        }

        // URL后缀
        $suffix = in_array($url, ['/', '']) ? '' : self::parseSuffix($suffix);

        // 参数组装
        if (!empty($vars)) {
            // 添加参数
            if ($common_param) {
                $vars = urldecode(http_build_query($vars));
                $url .= $suffix . '?' . $vars;
            } else {
                foreach ($vars as $var => $val) {
                    if ('' !== trim($val))
                        $url .= $depr . $var . $depr . urlencode($val);
                }
                $url .= $suffix;
            }
        }else {
            $url .= $suffix;
        }

        if (isset($anchor)) {
            $url .= '#' . $anchor;
        }
        if ($domain) {
            $url = (Request::getInstance()->isSsl() ? 'https://' : 'http://') . $domain . $url;
        }
        return $url;
    }

}
