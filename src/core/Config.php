<?php

namespace framework\core;

/**
 *
 * 网站配置
 */
class Config {

    /**
     * 配置参数
     * @var array
     */
    protected $config = [];

    /**
     * 配置前缀
     * @var string
     */
    protected $prefix = 'app';

    /**
     * 构造方法
     * @access public
     */
    public function __construct() {
        
    }

    public static function getInstance() {
        static $obj;
        if (!$obj) {
            $obj = new self();
        }
        return $obj;
    }

    // 设定配置参数的作用域
    public function range($prefix) {
        $this->prefix = $prefix;
        if (!isset($this->config[$prefix])) {
            $this->config[$prefix] = [];
        }
    }

    /**
     * 加载配置文件（PHP格式）
     *
     * @param string $file 配置文件名
     * @param string $name 配置名（如设置即表示二级配置）
     * @param string $prefix  作用域
     * @return mixed
     */
    public function load($file, $name = '', $prefix = '') {
        $prefix = $prefix ?: $this->prefix;
        if (!isset($this->config[$prefix])) {
            $this->config[$prefix] = [];
        }
        /* 如果是文件 */
        if (is_file($file)) {
            return $this->set(include $file, $name, $prefix);
        }
        /* 如果是文件夹 */
        if (is_dir($file)) {
            chdir($file);
            $configFiles = glob("*.php");
            foreach ($configFiles as $single_file) {
                $this->set(include $file . $single_file, $name, $prefix);
            }
        }
        /* 默认处理 */
        return $this->config[$prefix];
    }

    /**
     * 检测配置是否存在
     *
     * @param string $name 配置参数名（支持二级配置 .号分割）
     * @param string $prefix  作用域
     * @return bool
     */
    public function has($name, $prefix = '') {
        $prefix = $prefix ?: $this->prefix;
        $name = strtolower($name);

        if (!strpos($name, '.')) {
            return isset($this->config[$prefix][$name]);
        } else {
            // 二维数组设置和获取支持
            $name = explode('.', $name);
            return isset($this->config[$prefix][$name[0]][$name[1]]);
        }
    }

    /**
     * 获取配置参数 为空则获取所有配置
     *
     * @param string $name 配置参数名（支持二级配置 .号分割）
     * @param string $prefix  作用域
     * @return mixed
     */
    public function get($name = null, $prefix = '') {
        $prefix = $prefix ?: $this->prefix;
        // 无参数时获取所有
        if (empty($name) && isset($this->config[$prefix])) {
            return $this->config[$prefix];
        }
        $name = strtolower($name);
        if (!strpos($name, '.')) {
            // 判断环境变量
            if (isset($_ENV[$name])) {
                return $_ENV[$name];
            }
            return isset($this->config[$prefix][$name]) ? $this->config[$prefix][$name] : null;
        } else {
            // 二维数组设置和获取支持
            $name = explode('.', $name);
            // 判断环境变量
            if (isset($_ENV[$name[0] . '_' . $name[1]])) {
                return $_ENV[$name[0] . '_' . $name[1]];
            }
            return isset($this->config[$prefix][$name[0]][$name[1]]) ? $this->config[$prefix][$name[0]][$name[1]] : null;
        }
    }

    /**
     * 设置配置参数 name为数组则为批量设置
     *
     * @param string $name 配置参数名（支持二级配置 .号分割）
     * @param mixed $value 配置值
     * @param string $prefix  作用域
     * @return mixed
     */
    public function set($name, $value = null, $prefix = '') {
        $prefix = $prefix ?: $this->prefix;
        if (!isset($this->config[$prefix])) {
            $this->config[$prefix] = [];
        }
        if (is_string($name)) {
            $name = strtolower($name);
            if (!strpos($name, '.')) {
                $this->config[$prefix][$name] = $value;
            } else {
                // 二维数组设置和获取支持
                $name = explode('.', $name);
                $this->config[$prefix][$name[0]][$name[1]] = $value;
            }
            return;
        } elseif (is_array($name)) {
            // 批量设置
            if (!empty($value)) {
                return $this->config[$prefix][$value] = array_change_key_case($name);
            } else {
                return $this->config[$prefix] = array_merge($this->config[$prefix], array_change_key_case($name));
            }
        } else {
            // 为空直接返回 已有配置
            return $this->config[$prefix];
        }
    }

    /**
     * 重置配置参数
     * @access public
     * @param  string    $prefix  配置前缀名
     * @return void
     */
    public function reset($prefix = '') {
        if ('' === $prefix) {
            $this->config = [];
        } else {
            $this->config[$prefix] = [];
        }
    }

    /**
     * 设置配置
     * @access public
     * @param  string    $name  参数名
     * @param  mixed     $value 值
     */
    public function __set($name, $value) {
        return $this->set($name, $value);
    }

    /**
     * 获取配置参数
     * @access public
     * @param  string $name 参数名
     * @return mixed
     */
    public function __get($name) {
        return $this->get($name);
    }

    /**
     * 检测是否存在参数
     * @access public
     * @param  string $name 参数名
     * @return bool
     */
    public function __isset($name) {
        return $this->has($name);
    }

}
