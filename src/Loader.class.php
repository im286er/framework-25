<?php

class Loader {

    /**
     * 自动加载
     * @param type $class
     * @return boolean
     */
    public static function auto_load($class) {
        $class_file = $class . '.class.php';
        /* 加载框架自带 class */
        $dirs = [
            /* Db */
            __DIR__ . '/db/Driver/',
            __DIR__ . '/db/Model/',
            /* SSDB */
            __DIR__ . '/ssdb/',
            /* 公用 class */
            __DIR__ . '/libraries/',
            /* 命令行 */
            __DIR__ . '/cli/',
        ];
        foreach ($dirs as $dir) {
            $file = $dir . $class_file;
            if ((is_file($file)) && (file_exists($file))) {
                require($file);
                return true;
            }
        }

        if (substr($class, -5) == 'Model') {
            /* 加载数据层 */
            $class_file = 'model/' . $class_file;
        } elseif (substr($class, -7) == 'Service') {
            /* 加载服务层 */
            $class_file = 'service/' . $class_file;
        } elseif (substr($class, -5) == 'Logic') {
            /* 加载逻辑层 */
            $class_file = 'logic/' . $class_file;
        } elseif (substr($class, -6) == 'Action') {
            /* 加载控制 */
            $class_file = 'action/' . $class_file;
        } elseif ((substr($class, -6) == 'Widget') && ($class != 'Widget')) {
            /* 加载Widget */
            $class_file = 'widget/' . $class_file;
        } else {
            /* 默认应用下公共 class */
            $class_file = 'class/' . $class_file;
        }
        /* 判断是否启用分组 */
        if (defined('GROUP_NAME')) {
            $file = APP_PATH . GROUP_NAME . '/' . $class_file;
        } else {
            $file = APP_PATH . $class_file;
        }
        if ((is_file($file)) && (file_exists($file))) {
            require $file;
            return true;
        }
        /* 加载公共 */
        $file = APP_PATH . 'common/' . $class_file;
        if ((is_file($file)) && (file_exists($file))) {
            require $file;
            return true;
        }
        return false;
    }

    /**
     * 字符串命名风格转换
     * type 0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
     * @param string  $name 字符串
     * @param integer $type 转换类型
     * @param bool    $ucfirst 首字母是否大写（驼峰规则）
     * @return string
     */
    public static function parseName($name, $type = 0, $ucfirst = true) {
        if ($type) {
            $name = preg_replace_callback('/_([a-zA-Z])/', function ($match) {
                return strtoupper($match[1]);
            }, $name);
            return $ucfirst ? ucfirst($name) : lcfirst($name);
        } else {
            return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
        }
    }

    // 注册自动加载机制
    public static function register($autoload = '') {
        // 注册框架自动加载
        spl_autoload_register('\Loader::auto_load', true, true);

        // Composer自动加载支持
        if (is_dir(ROOT_PATH . 'vendor/composer')) {
            require_cache(ROOT_PATH . 'vendor/autoload.php');
        }
    }

}
