<?php

namespace framework\core;

/**
 * 路由解析类
 */
class Route {

    /**
     * 路由检测
     */
    public static function routerCheck() {
        $regx = trim($_SERVER['PATH_INFO'], '/');

        if (empty($regx)) {
            return false;
        }
        /* URL映射定义（静态路由） */
        $maps = Config::getInstance()->get('URL_MAP_RULES');
        if (isset($maps[$regx])) {
            $var = self::parseUrl($maps[$regx]);
            $_GET = array_merge($var, $_GET);
            return true;
        }
        /* 路由处理 */
        $routes = Config::getInstance()->get('routes');

        if (empty($routes)) {
            return false;
        }

        foreach ($routes as $rule => $route) {
            if (0 === strpos($rule, '/') && preg_match($rule, $regx, $matches)) {
                /* 正则路由 */
                return self::parseRegex($matches, $route, $regx);
            } else {
                /* 规则路由 */
                $len1 = substr_count($regx, '/');
                $len2 = substr_count($rule, '/');

                if ($len1 >= $len2 || strpos($rule, '[')) {
                    if ('$' == substr($rule, -1, 1)) {
                        /* 完整匹配 */
                        if ($len1 != $len2) {
                            continue;
                        } else {
                            $rule = substr($rule, 0, -1);
                        }
                    }
                    $match = self::checkUrlMatch($regx, $rule);
                    if (false !== $match) {
                        return self::parseRule($rule, $route, $regx);
                    }
                }
            }
        }
        return false;
    }

    /**
     * 检测URL和规则路由是否匹配
     * @param type $regx
     * @param type $rule
     * @return boolean
     */
    private static function checkUrlMatch($regx, $rule) {
        $m1 = explode('/', $regx);
        $m2 = explode('/', $rule);
        $var = [];
        foreach ($m2 as $key => $val) {
            if (0 === strpos($val, '[:')) {
                $val = substr($val, 1, -1);
            }

            if (':' == substr($val, 0, 1)) {/* 动态变量 */
                if ($pos = strpos($val, '|')) {
                    /* 使用函数过滤 */
                    $val = substr($val, 1, $pos - 1);
                }
                if (strpos($val, '\\')) {
                    $type = substr($val, -1);
                    if ('d' == $type) {
                        if (isset($m1[$key]) && !is_numeric($m1[$key]))
                            return false;
                    }
                    $name = substr($val, 1, -2);
                }elseif ($pos = strpos($val, '^')) {
                    $array = explode('-', substr(strstr($val, '^'), 1));
                    if (in_array($m1[$key], $array)) {
                        return false;
                    }
                    $name = substr($val, 1, $pos - 1);
                } else {
                    $name = substr($val, 1);
                }
                $var[$name] = isset($m1[$key]) ? $m1[$key] : '';
            } elseif (0 !== strcasecmp($val, $m1[$key])) {
                return false;
            }
        }
        /* 成功匹配后返回URL中的动态变量数组 */
        return $var;
    }

    /** 解析规范的路由地址
      地址格式 [控制器/操作?]参数1=值1&参数2=值2...
     */
    private static function parseUrl($url) {
        $var = [];
        if (false !== strpos($url, '?')) {
            /* [控制器/操作?]参数1=值1&参数2=值2... */
            $info = parse_url($url);
            $info['path'] = trim($info['path'], '/');
            $path = explode('/', $info['path']);
            parse_str($info['query'], $var);
        } elseif (strpos($url, '/')) {
            /* [控制器/操作] */
            $path = explode('/', $url);
        } else {
            /* 参数1=值1&参数2=值2... */
            parse_str($url, $var);
        }
        if (isset($path)) {
            $var['a'] = array_pop($path);
            if (!empty($path)) {
                $var['c'] = array_pop($path);
            }
            if (!empty($path)) {
                $var['app'] = array_pop($path);
            }
        }
        return $var;
    }

    /**
      解析规则路由
      '路由规则'=>'[控制器/操作]?额外参数1=值1&额外参数2=值2...'
      '路由规则'=>array('[控制器/操作]','额外参数1=值1&额外参数2=值2...')
      '路由规则'=>'外部地址'
      '路由规则'=>array('外部地址','重定向代码')
      路由规则中 :开头 表示动态变量
      外部地址中可以用动态变量 采用 :1 :2 的方式
      'news/:month/:day/:id'=>array('News/read?cate=1','status=1'),
      'new/:id'=>array('/new.php?id=:1',301), 重定向
      @param type $rule
      @param type $route
      @param type $regx
      @return boolean
     */
    private static function parseRule($rule, $route, $regx) {
        /* 获取路由地址规则 */
        $url = is_array($route) ? $route[0] : $route;
        /* 获取URL地址中的参数 */
        $paths = explode('/', $regx);
        /* 解析路由规则 */
        $matches = [];
        $rule = explode('/', $rule);
        foreach ($rule as $item) {
            $fun = '';
            if (0 === strpos($item, '[:')) {
                $item = substr($item, 1, -1);
            }
            if (0 === strpos($item, ':')) {
                /* 动态变量获取 */
                if ($pos = strpos($item, '|')) {
                    /* 支持函数过滤 */
                    $fun = substr($item, $pos + 1);
                    $item = substr($item, 0, $pos);
                }
                if ($pos = strpos($item, '^')) {
                    $var = substr($item, 1, $pos - 1);
                } elseif (strpos($item, '\\')) {
                    $var = substr($item, 1, -2);
                } else {
                    $var = substr($item, 1);
                }
                $matches[$var] = !empty($fun) ? $fun(array_shift($paths)) : array_shift($paths);
            } else {
                /* 过滤URL中的静态变量 */
                array_shift($paths);
            }
        }

        if (0 === strpos($url, '/') || 0 === strpos($url, 'http')) {
            /* 路由重定向跳转 */
            if (strpos($url, ':')) {
                /* 传递动态参数 */
                $values = array_values($matches);
                $url = preg_replace_callback('/:(\d+)/', function($match) use($values) {
                    return $values[$match[1] - 1];
                }, $url);
            }
            header("Location: $url", true, (is_array($route) && isset($route[1])) ? $route[1] : 301);
            exit();
        } else {
            /* 解析路由地址 */
            $var = self::parseUrl($url);
            /* 解析路由地址里面的动态参数 */
            $values = array_values($matches);
            foreach ($var as $key => $val) {
                if (0 === strpos($val, ':')) {
                    $var[$key] = $values[substr($val, 1) - 1];
                }
            }
            $var = array_merge($matches, $var);
            /* 解析剩余的URL参数 */
            if (!empty($paths)) {
                preg_replace_callback('/(\w+)\/([^\/]+)/', function($match) use(&$var) {
                    $var[strtolower($match[1])] = strip_tags($match[2]);
                }, implode('/', $paths));
            }
            /* 解析路由自动传入参数 */
            if (is_array($route) && isset($route[1])) {
                if (is_array($route[1])) {
                    $params = $route[1];
                } else {
                    parse_str($route[1], $params);
                }
                $var = array_merge($var, $params);
            }
            $_GET = array_merge($var, $_GET);
        }
        return true;
    }

    /**
     * 解析正则路由
      '路由正则'=>'[控制器/操作]?参数1=值1&参数2=值2...'
      '路由正则'=>array('[控制器/操作]?参数1=值1&参数2=值2...','额外参数1=值1&额外参数2=值2...')
      '路由正则'=>'外部地址'
      '路由正则'=>array('外部地址','重定向代码')
      参数值和外部地址中可以用动态变量 采用 :1 :2 的方式
      '/new\/(\d+)\/(\d+)/'=>array('News/read?id=:1&page=:2&cate=1','status=1'),
      '/new\/(\d+)/'=>array('/new.php?id=:1&page=:2&status=1','301'), 重定向
     * @param type $matches
     * @param type $route
     * @param type $regx
     * @return boolean
     */
    private static function parseRegex($matches, $route, $regx) {
        /* 获取路由地址规则 */
        $url = is_array($route) ? $route[0] : $route;
        $url = preg_replace_callback('/:(\d+)/', function($match) use($matches) {
            return $matches[$match[1]];
        }, $url);
        if (0 === strpos($url, '/') || 0 === strpos($url, 'http')) {
            /* 路由重定向跳转 */
            header("Location: $url", true, (is_array($route) && isset($route[1])) ? $route[1] : 301);
            exit();
        } else {
            /* 解析路由地址 */
            $var = self::parseUrl($url);

            /* 解析剩余的URL参数 */
            $regx = substr_replace($regx, '', 0, strlen($matches[0]));
            if ($regx) {
                preg_replace_callback('/(\w+)\/([^\/]+)/', function($match) use(&$var) {
                    $var[strtolower($match[1])] = strip_tags($match[2]);
                }, $regx);
            }
            /*  解析路由自动传入参数 */
            if (is_array($route) && isset($route[1])) {
                if (is_array($route[1])) {
                    $params = $route[1];
                } else {
                    parse_str($route[1], $params);
                }
                $var = array_merge($var, $params);
            }
            $_GET = array_merge($var, $_GET);
        }
        return true;
    }

}
