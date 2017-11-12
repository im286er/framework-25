<?php

/**
 * https://github.com/coooold/CurlFuture
 */

/**
 * 异步HttpFuture，实现http请求的延迟执行
 */
class HttpFuture extends CurlFuture {

    /**
     * 构造方法，传入url和对应设置，目前仅支持get方法
     *
     * @param $url 请求url地址
     * @param $options = array(),
     * 		header:头信息(Array),
     * 		proxy_url,
     * 		timeout:超时时间，可以小于1
     * 		post_data: string|array post数据
     * 	@return Future
     */
    public function __construct($url, $options = []) {
        $mt = CurlFutureTaskManager::getInstance();

        $ch = $mt->addTask($url, $options);

        $this->callback = function($data)use($mt, $ch) {
            return $mt->fetch($ch);
        };
    }

}

/**
 * Future类，提供延迟执行的基础方法
 */
class CurlFuture {

    protected $callback = null;
    protected $nextFuture = null;

    /**
     * 构造函数，创建一个延迟执行的方法，在fetch的时候才真正执行
     * @param @callback 一个可执行的函数
     */
    public function __construct($callback) {
        assert(is_callable($callback));
        $this->callback = $callback;
    }

    /**
     * 链式执行的函数，避免大量回调，上一个future执行的结果会作为下一个future执行结果的参数来执行
     * @param @callback 一个可执行的函数
     */
    public function then($callback) {
        if ($this->nextFuture) {
            $this->nextFuture->then($callback);
        } else {
            $this->nextFuture = new self($callback);
        }

        return $this;
    }

    /**
     * future真正执行的方法，一直执行到future链到最后一个，并返回最后一个的执行结果
     * @param @input 初始输入参数
     */
    public function fetch($input = null) {
        $ret = call_user_func_array($this->callback, array($input));
        if ($this->nextFuture) {
            return $this->nextFuture->fetch($ret);
        } else {
            return $ret;
        }
    }

}

/**
 * Task类，封装每个curl handle的输入输出方法，如果需要日志、异常处理，可以放在这个地方
 */
class CurlFutureTask {

    public $url;
    public $ch; //curl handle
    protected $curlOptions = array();

    public function __construct($url, $options) {
        $this->url = $url;
        $ch = curl_init();


        $curlOptions = array(
            CURLOPT_TIMEOUT => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $url,
        );

        //这个地方需要合并cat的头信息
        $headers = isset($options['header']) ? $options['header'] : array();
        $curlOptions[CURLOPT_HTTPHEADER] = $headers;

        if (isset($options['proxy_url']) && $options['proxy_url']) {
            $curlOptions[CURLOPT_PROXY] = $options['proxy_url'];
        }

        //设置超时时间
        $timeout = isset($options['timeout']) ? $options['timeout'] : 1;
        if ($timeout < 1) {
            $curlOptions[CURLOPT_TIMEOUT_MS] = intval($timeout * 1000);
            $curlOptions[CURLOPT_NOSIGNAL] = 1;
        } else {
            $curlOptions[CURLOPT_TIMEOUT] = $timeout;
        }

        // 如果需要post数据
        if (isset($options['post_data']) && $options['post_data']) {
            $curlOptions[CURLOPT_POST] = true;

            curl_setopt($ch, CURLOPT_POST, true);
            $postData = $options['post_data'];
            if (is_array($options['post_data'])) {
                $postData = http_build_query($options['post_data']);
            }
            $curlOptions[CURLOPT_POSTFIELDS] = $postData;
        }

        curl_setopt_array($ch, $curlOptions);

        $this->ch = $ch;
    }

    /**
     * 请求完成后调用，可以在这个函数里面加入日志与统计布点，返回http返回结果
     * @return 成功string，失败false
     */
    public function complete() {
        return $this->getContent();
    }

    /**
     * 如果curl已经完成，通过这个函数读取内容
     * @return 成功string，失败false
     */
    private function getContent() {
        $error = curl_errno($this->ch);
        if ($error !== 0) {
            return false;
        }

        return curl_multi_getcontent($this->ch);
    }

}

/**
 * 封装了MultiCurl的类，实现了curl并行与轮转请求
 */
class CurlFutureTaskManager {

    /**
     * @var curl_multi_handle
     */
    protected $multiHandle;

    /**
     * 正在执行的任务
     */
    protected $runningTasks = array();

    /**
     * 已经完成的任务
     */
    protected $finishedTasks = array();

    /**
     * select的默认timeout时间，对于高版本curl扩展，这个没用用处
     */
    const SELECT_TIMEOUT = 1; //select超时时间1s

    protected function __construct() {
        $this->multiHandle = curl_multi_init();
    }

    function __destruct() {
        curl_multi_close($this->multiHandle);
    }

    /**
     * 添加curl任务，options参考HttpFuture::__construct
     * @return curl_handle
     */
    public function addTask($url, $options) {
        $req = new CurlFutureTask($url, $options);
        $ch = $req->ch;

        $this->runningTasks[(int) $ch] = array(
            'return' => false,
            'req' => $req,
            'ch' => $ch,
        );

        curl_multi_add_handle($this->multiHandle, $ch);

        return $ch;
    }

    /**
     * 如果ch未完成，阻塞并且并行执行curl请求，直到对应ch完成，返回对应结果
     * @return string
     */
    public function fetch($ch) {
        $chKey = (int) $ch;
        $this->debug("fetch " . (int) $ch);

        //如果两个队列里面都没有，那么退出
        if (!array_key_exists($chKey, $this->runningTasks) && !array_key_exists($chKey, $this->finishedTasks))
            return false;

        $active = 1;
        do {
            //如果任务完成了，那么退出
            if (array_key_exists($chKey, $this->finishedTasks))
                break;

            //执行multiLoop，直到该任务完成
            $active = $this->multiLoop();
            //如果执行出错，那么停止循环
            if ($active === false)
                break;
        }while (1);

        return $this->finishTask($ch);
    }

    /**
     * 循环一次multi任务
     * @return bool true:可以继续执行 false:已经循环结束，无法继续执行
     */
    protected function multiLoop() {
        //echo '.';
        $active = 1;

        // fix for https://bugs.php.net/bug.php?id=63411
        // see https://github.com/petewarden/ParallelCurl/blob/master/parallelcurl.php
        // see http://blog.marchtea.com/archives/109
        while (curl_multi_exec($this->multiHandle, $active) === CURLM_CALL_MULTI_PERFORM);

        $ret = 0;
        //等待socket操作
        $ret = curl_multi_select($this->multiHandle, self::SELECT_TIMEOUT);

        //处理已经完成的句柄
        while ($info = curl_multi_info_read($this->multiHandle)) {
            $ch = $info['handle'];
            $this->debug('get content' . (int) $ch);

            $task = $this->runningTasks[(int) $ch];
            $task['return'] = $task['req']->complete();

            unset($this->runningTasks[(int) $ch]);
            $this->finishedTasks[(int) $ch] = $task;
            curl_multi_remove_handle($this->multiHandle, $ch);
        }

        return $active;
    }

    /**
     * 完成任务，执行任务回调
     * @return mixed 输出该http请求的内容
     */
    protected function finishTask($ch) {
        $this->debug("finishTask " . (int) $ch);

        $ch = (int) $ch;
        $task = $this->finishedTasks[$ch];
        unset($this->finishedTasks[$ch]);
        return $task['return'];
    }

    protected function debug($s) {
        //echo time()." {$s}\n";
    }

    static protected $instance;

    static public function getInstance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

}
