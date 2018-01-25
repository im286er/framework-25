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
            return ob_get_clean();
        } else {
            throw new Exception("Template file not found: {$file}.", 404);
        }
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
     * 清除赋值
     * @param type $key
     */
    public function clear($key = null) {
        if (is_null($key)) {
            $this->_vars = [];
        } else {
            unset($this->_vars[$key]);
        }
    }

    /**
     * Displays escaped output.
     *
     * @param string $str String to escape
     * @return string Escaped string
     */
    public function e($str) {
        echo htmlentities($str);
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
