<?php

namespace framework\core;

/**
 * 控制器基类 抽象类
 */
abstract class Action {

    /**
     * 视图实例对象
     * @var view
     * @access protected
     */
    protected $view = null;

    /**
     * 控制器参数
     * @var config
     * @access protected
     */
    protected $config = array();

    // 初始化
    function __initialize() {
        
    }

    /**
     * 架构函数 取得模板对象实例
     * @access public
     */
    public function __construct() {
        $this->view = View::getInstance();

        //控制器初始化
        $this->__initialize();
    }

    /**
     * 模板显示
     * 调用内置的模板引擎显示方法，
     * @param type $tpl     指定要调用的模板文件
     * @param type $dir     指定要调用的模板目录
     */
    protected function display($tpl, $dir = null) {
        return $this->view->fetch($tpl, $dir);
    }

    /**
     *  获取输出页面内容
     * 调用内置的模板引擎fetch方法，
     * @param type $tpl     指定要调用的模板文件
     * @param type $dir     指定要调用的模板目录
     * @return string
     */
    protected function fetch($tpl, $dir = null) {
        return $this->view->fetch($tpl, $dir);
    }

    /**
     * 模板变量赋值
     * @access protected
     * @param mixed $name 要显示的模板变量
     * @param mixed $value 变量的值
     * @return Action
     */
    protected function assign($name, $value = '') {
        $this->view->assign($name, $value);
        return $this;
    }

    public function __set($name, $value) {
        $this->assign($name, $value);
    }

    /**
     * 取得模板显示变量的值
     * @access protected
     * @param string $name 模板显示变量
     * @return mixed
     */
    public function get($name = '') {
        return $this->view->get($name);
    }

    public function __get($name) {
        return $this->get($name);
    }

    /**
     * 检测模板变量的值
     * @access public
     * @param string $name 名称
     * @return boolean
     */
    public function __isset($name) {
        return $this->get($name);
    }

    /**
     * 操作错误跳转的快捷方法
     * @access protected
     * @param string $message 错误信息
     * @param string $jumpUrl 页面跳转地址
     * @param mixed $ajax 是否为Ajax方式 当数字时指定跳转时间
     * @return void
     */
    protected function error($message = '', $jumpUrl = '', $ajax = false) {
        $this->dispatchJump($message, 0, $jumpUrl, $ajax);
    }

    /**
     * 操作成功跳转的快捷方法
     * @access protected
     * @param string $message 提示信息
     * @param string $jumpUrl 页面跳转地址
     * @param mixed $ajax 是否为Ajax方式 当数字时指定跳转时间
     * @return void
     */
    protected function success($message = '', $jumpUrl = '', $ajax = false) {
        $this->dispatchJump($message, 1, $jumpUrl, $ajax);
    }

    /**
     * Ajax方式返回数据到客户端
     * @access protected
     * @param mixed $data 要返回的数据
     * @param String $type AJAX返回数据格式
     * @return void
     */
    public function ajaxReturn($data, $type = 'JSON') {
        switch (strtoupper($type)) {
            case 'JSON' :
                // 返回JSON数据格式到客户端 包含状态信息
                $json = json_encode($data, JSON_UNESCAPED_UNICODE);
                Response::getInstance()->header('Content-Type', 'application/json; charset=utf-8')->write($json)->send();
            case 'JSONP':
                // 返回JSON数据格式到客户端 包含状态信息
                $callback = get('callback', '', 't');
                $json = json_encode($data, JSON_UNESCAPED_UNICODE);
                Response::getInstance()->header('Content-Type', 'application/javascript; charset=utf-8')->write($callback . '(' . $json . ');')->send();
            case 'EVAL' :
                // 返回可执行的js脚本
                Response::getInstance()->write($data)->send();
            default :
                // 用于扩展其他返回格式数据
                $json = json_encode($data, JSON_UNESCAPED_UNICODE);
                Response::getInstance()->header('Content-Type', 'application/json; charset=utf-8')->write($json)->send();
        }
        return;
    }

    /**
     * 跳转(URL重定向）
     * @access protected
     * @param string $url 跳转的URL表达式
     * @param integer $delay 延时跳转的时间 单位为秒
     * @param string $msg 跳转提示信息
     * @return void
     */
    protected function redirect($url, $delay = 0, $msg = '') {
        if ($delay == 0) {
            Response::getInstance()->clear()->status(302)->header('Location', $url)->write($url)->send();
        } else {
            $this->delay_redirect($url, $delay, $msg);
        }
    }

    /**
     * Stops processing and returns a given response.
     *
     * @param int $code HTTP status code
     * @param string $message Response message
     */
    public function halt($code = 200, $message = '') {
        Response::getInstance()->clear()->status($code)->write($message)->send();
        exit();
    }

    /**
     * Sends an HTTP 404 response when a URL is not found.
     */
    public function not_found() {
        Response::getInstance()->clear()->status(404)->write('<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN"><html><head><title>404 Not Found</title></head><body bgcolor="white"><h1>404 Not Found</h1><p>The requested URL was not found on this server.<hr/>Powered by ChinaCache' . str_repeat(' ', 512) . '</body></html>')->send();
    }

    /**
     * Handles ETag HTTP caching.
     *
     * @param string $id ETag identifier
     * @param string $type ETag type
     */
    public function etag($id, $type = 'strong') {
        $id = (($type === 'weak') ? 'W/' : '') . $id;

        Response::getInstance()->header('ETag', $id);

        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $id) {
            $this->halt(304);
        }
    }

    /**
     * Handles last modified HTTP caching.
     *
     * @param int $time Unix timestamp
     */
    public function last_modified($time) {
        $this->response()->header('Last-Modified', gmdate('D, d M Y H:i:s \G\M\T', $time));

        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) === $time) {
            $this->halt(304);
        }
    }

    /**
     * 无 http 缓存
     */
    protected function no_cache() {
        Response::getInstance()->cache(false)->sendHeaders();
    }

    /**
     * Js 版 URL 跳转
     * @param string $url       将要跳转的URL地址
     * @param integer $time     跳转前的等待时间（秒）
     * @param string $msg       跳转前的提示信息
     * @param type $msg_type    消息类型， 0消息，1错误
     */
    function delay_redirect($url, $time = 0, $msg = '', $msg_type = 0) {
        //发送成功信息
        if ($msg_type == 0) {
            $this->view->assign('message', $msg); // 提示信息
        } else {
            $this->view->assign('error', $msg); // 提示信息
        }
        // 成功操作后默认停留1秒
        if (0 === $time) {
            $this->view->assign('waitSecond', '1');
        } else {
            $this->view->assign('waitSecond', $time);
        }
        // 默认操作成功自动返回操作前页面
        if (empty($url)) {
            $this->view->assign("jumpUrl", $_SERVER["HTTP_REFERER"]);
        } else {
            $this->view->assign("jumpUrl", $url);
        }

        $this->view->display('dispatch_jump.tpl.php', __DIR__ . '/../tpl/');
        exit();
    }

    /**
     * 默认跳转操作 支持错误导向和正确跳转
     * 调用模板显示 默认为public目录下面的success页面
     * 提示页面为可配置 支持模板标签
     * @param string $message 提示信息
     * @param Boolean $status 状态
     * @param string $jumpUrl 页面跳转地址
     * @param mixed $ajax 是否为Ajax方式 当数字时指定跳转时间
     * @access private
     * @return void
     */
    private function dispatchJump($message, $status = 1, $jumpUrl = '', $ajax = false) {
        if (true === $ajax || Request::getInstance()->isAjax() == true) {// AJAX提交
            $data = is_array($ajax) ? $ajax : [];
            $data['msg'] = $message;
            $data['ret'] = $status;
            $data['data'] = $jumpUrl;
            $this->ajaxReturn($data);
        }
        if (is_int($ajax)) {
            $this->view->assign('waitSecond', $ajax);
        }
        if (!empty($jumpUrl)) {
            $this->view->assign('jumpUrl', $jumpUrl);
        }

        // 提示标题
        $this->view->assign('msgTitle', $status ? '操作成功' : '操作失败');

        $this->view->assign('status', $status);   // 状态
        if ($status) { //发送成功信息
            $this->view->assign('message', $message); // 提示信息
            // 成功操作后默认停留1秒
            if (!isset($this->waitSecond)) {
                $this->view->assign('waitSecond', '1');
            }
            // 默认操作成功自动返回操作前页面
            if (!isset($this->jumpUrl)) {
                $this->view->assign("jumpUrl", $_SERVER["HTTP_REFERER"]);
            }
            $this->view->display('dispatch_jump.tpl.php', __DIR__ . '/../tpl/');
        } else {
            $this->view->assign('error', $message); // 提示信息
            //发生错误时候默认停留3秒
            if (!isset($this->waitSecond)) {
                $this->view->assign('waitSecond', '3');
            }
            // 默认发生错误的话自动返回上页
            if (!isset($this->jumpUrl)) {
                $this->view->assign('jumpUrl', "javascript:history.back(-1);");
            }

            $this->view->display('dispatch_jump.tpl.php', __DIR__ . '/../tpl/');
        }
        // 中止执行  避免出错后继续执行
        exit();
    }

    /**
     * 析构方法
     * @access public
     */
    public function __destruct() {
        unset($this->view);
    }

}
