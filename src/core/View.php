<?php

namespace framework\core;

/**
 *  简单展示模板类
 */
class View {

    // 模板赋值
    public $_var = [];

    public function fetch($tpl, $dir = null) {
        if (null === $dir) {
            $dir = Config::get('template_dir');
        }
        if ($dir) {
            $dir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
        }
        $file = $dir . $tpl;

        if (file_exists($file) && (is_file($file))) {
            ob_start();
            ob_implicit_flush(0);
            @extract($this->_var);
            include($file);
            $content = ob_get_clean();
        } else {
            $error_txt = "Error: Could not load template {$file} !";
            Log::write($error_txt);
            trigger_error($error_txt);
            $content = $error_txt;
        }
        return $content;
    }

    public function display($tpl, $dir = null) {
        header('Content-Type:text/html; charset=utf-8');
        $content = $this->fetch($tpl, $dir);
        echo $content;
    }

    /**
     * 注册变量
     *
     * @access  public
     * @param   mix      $tpl_var
     * @param   mix      $value
     *
     * @return  void
     */
    public function assign($key, $value = null) {
        if (!$key) {
            return false;
        }
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->_var[$k] = $v;
            }
        } else {
            $this->_var[$key] = $value;
        }
        return true;
    }

    /**
     * 获取模板变量
     *
     * @param string $name
     * @return NULL|mixed
     */
    public function get($name = '') {
        return $name === '' ? $this->_vars : ($this->_vars[$name] ? $this->_vars[$name] : NULL);
    }

    /**
     * 清除全部赋值
     */
    public function clearAllAssign() {
        $this->_vars = [];
    }

    /**
     * 清除赋值
     *
     * @param string ...$vars
     */
    public function clearAssign(...$vars) {
        array_map(function ($var) {
            unset($this->_vars[$var]);
        }, $vars);
    }

    /**
     * 不缓存的头部设置
     */
    public function noCache() {
        $stamp = gmdate('D, d M Y H:i:s', TIMESTAMP) . ' GMT';
        header('Expires: Tue, 13 Mar 1979 18:00:00 GMT');
        header('Last-Modified: ' . $stamp);
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
    }

    public static function getInstance() {
        static $obj;
        if (!$obj) {
            $obj = new self();
        }
        return $obj;
    }

    public function __destruct() {
        $this->_var = [];
    }

}
