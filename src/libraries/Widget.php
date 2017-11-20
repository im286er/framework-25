<?php

namespace framework\libraries;

/**
 * Widget类 抽象类
 */
abstract class Widget {

    public $data = [];

    /**
     * 渲染输出 render方法是Widget唯一的接口
     * 使用字符串返回 不能有任何输出
     * @access public
     * @param mixed $data  要渲染的数据
     * @return string
     */
    abstract public function render($data);

    /**
     * 渲染模板输出 供render方法内部调用
     * @access public
     * @param string $templateFile  模板文件
     * @param mixed $var  模板变量
     * @return string
     */
    protected function renderFile($templateFile = '') {
        ob_start();
        ob_implicit_flush(0);
        if (!is_file($templateFile)) {
            // 自动定位模板文件
            $name = substr(get_class($this), 0, -6);
            $filename = empty($templateFile) ? $name : $templateFile;
            $templateFile = Config::get('template_dir') . 'widget/' . $filename . '.tpl.php';
            if (!is_file($templateFile)) {
                throw new Exception(' template not exist [' . $templateFile . ']');
            }
        }
        // 使用PHP模板
        if (!empty($this->data)) {
            extract($this->data, EXTR_OVERWRITE);
        }
        // 直接载入PHP模板
        include $templateFile;

        $content = ob_get_clean();
        return $content;
    }

}
