<?php

/**
 * HTTP 请求
 */
class Request {

    /**
     * 请求方法
     *
     * @var string
     */
    protected $method;

    /**
     * 请求的 URI
     *
     * @var string
     */
    protected $requestUri;

    /**
     * PATH_INFO
     *
     * @var string
     */
    protected $pathInfo;

    /**
     * 基地址
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * 基路径
     *
     * @var string
     */
    protected $basePath;

    /**
     * 当前过滤器
     *
     * @var array
     */
    protected $filters = [];

    /**
     * 匹配的路由参数
     *
     * @var array
     */
    protected $params = [];
    // php://input
    protected $input;

    /**
     * @var array 资源类型
     */
    protected $mimeType = [
        'xml' => 'application/xml,text/xml,application/x-xml',
        'json' => 'application/json,text/x-json,application/jsonrequest,text/json',
        'js' => 'text/javascript,application/javascript,application/x-javascript',
        'css' => 'text/css',
        'rss' => 'application/rss+xml',
        'yaml' => 'application/x-yaml,text/yaml',
        'atom' => 'application/atom+xml',
        'pdf' => 'application/pdf',
        'text' => 'text/plain',
        'png' => 'image/png',
        'jpg' => 'image/jpg,image/jpeg,image/pjpeg',
        'gif' => 'image/gif',
        'csv' => 'text/csv',
        'html' => 'text/html,application/xhtml+xml,*/*',
    ];

    public static function getInstance() {
        static $obj;
        if (!$obj) {
            $obj = new self();
        }
        return $obj;
    }

    /**
     * 获取运行服务器 IP
     * @return type
     */
    public function get_server_ip() {
        if (isset($_SERVER['SERVER_ADDR'])) {
            return $_SERVER['SERVER_ADDR'];
        }
        if (isset($_SERVER['LOCAL_ADDR'])) {
            return $_SERVER['LOCAL_ADDR'];
        }
        return getenv('SERVER_ADDR');
    }

    /**
     * 获取请求的方法
     *
     * @return string
     */
    public function method() {
        if (is_null($this->method)) {
            $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';

            if ($method == 'POST') {
                if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
                    $method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
                } else {
                    $method = isset($_POST['_method']) ? strtoupper($_POST['_method']) : $method;
                }
            }

            $this->method = $method;
        }

        return $this->method;
    }

    /**
     * 获取请求的Scheme
     *
     * @return string
     */
    public function scheme() {
        return (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] === true)) ? 'https' : 'http';
    }

    /**
     * 当前请求的host
     * @access public
     * @return string
     */
    public function host() {
        return strtolower($_SERVER['HTTP_HOST']);
    }

    /**
     * 获取请求的端口
     *
     * @return string
     */
    public function port() {
        return isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : '80';
    }

    /**
     * 当前请求 SERVER_PROTOCOL
     * @access public
     * @return integer
     */
    public function protocol() {
        return isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
    }

    /**
     * 当前请求 REMOTE_PORT
     * 连接到服务器时所使用的端口
     * @access public
     * @return integer
     */
    public function remotePort() {
        return $_SERVER['REMOTE_PORT'];
    }

    /**
     * 返回客户端的HTTP
     * @access public
     * @return string
     */
    public function getHttpVersion() {
        static $_httpVersion = null;
        return $_httpVersion ?: ($_httpVersion = isset($_SERVER['SERVER_PROTOCOL']) && $_SERVER['SERVER_PROTOCOL'] === 'HTTP/1.0' ? '1.0' : '1.1');
    }

    /**
     * 获取请求的 path 部分
     *
     * @return string
     */
    public function pathname() {
        return $this->basePath();
    }

    /**
     * 获取请求的执行文件
     *
     * @return string
     */
    public function scriptName() {
        return isset($_SERVER['SCRIPT_NAME']) ? basename($_SERVER['SCRIPT_NAME']) : '';
    }

    /**
     * 获取请求的查询部分
     *
     * @return string
     */
    public function query() {
        return isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
    }

    /**
     * 获取 Header
     * Request::getInstance()->header('token');
     * @return string
     */
    public function header($header, $default = false) {
        $temp = 'HTTP_' . strtoupper(str_replace('-', '_', $header));
        if (isset($_SERVER[$temp])) {
            return $_SERVER[$temp];
        }

        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers[$header])) {
                return $headers[$header];
            }
            $header = strtolower($header);
            foreach ($headers as $key => $value) {
                if (strtolower($key) == $header) {
                    return $value;
                }
            }
        }

        return $default;
    }

    /**
     * 是否一个GET方法请求
     *
     * @return boolean
     */
    public function isGet() {
        return $this->method() === 'GET' ? true : false;
    }

    /**
     * 是否一个POST方法请求
     *
     * @return boolean
     */
    public function isPost() {
        return $this->method() === 'POST' ? true : false;
    }

    /**
     * 是否一个PUT方法请求
     *
     * @return boolean
     */
    public function isPut() {
        return $this->method() === 'PUT' ? true : false;
    }

    /**
     * 是否一个DELETE方法请求
     *
     * @return boolean
     */
    public function isDelete() {
        return $this->method() === 'DELETE' ? true : false;
    }

    /**
     * 是否一个PATCH方法请求
     *
     * @return boolean
     */
    public function isPatch() {
        return $this->method() === 'PATCH' ? true : false;
    }

    /**
     * 是否一个HEAD方法请求
     *
     * @return boolean
     */
    public function isHead() {
        return $this->method() === 'HEAD' ? true : false;
    }

    /**
     * 是否一个OPTIONS方法请求
     *
     * @return boolean
     */
    public function isOptions() {
        return $this->method() === 'OPTIONS' ? true : false;
    }

    /**
     * 是否一个 XMLHttpRequest 请求
     *
     * @return boolean
     */
    public function isAjax() {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            if ('xmlhttprequest' == strtolower($_SERVER['HTTP_X_REQUESTED_WITH']))
                return true;
        }
        // 判断Ajax方式提交
        $get_ajax = get('ajax', 0, 'intval');
        $post_ajax = post('ajax', 0, 'intval');

        if (($get_ajax == 1) || ( $post_ajax == 1)) {
            return true;
        }
        return false;
    }

    /**
     * 当前是否Pjax请求
     * @access public
     * @return bool
     */
    public function isPjax() {
        return (isset($_SERVER['HTTP_X_PJAX']) && $_SERVER['HTTP_X_PJAX']) ? true : false;
    }

    /**
     * 获取请求来源地址
     *
     * @return string
     */
    public function referer($default = null) {
        return isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $default;
    }

    /**
     * 获取客户端IP地址
     * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
     * @param boolean $adv 是否进行高级模式获取（有可能被伪装）
     * @return mixed
     */
    public function ip($type = 0, $adv = false) {
        $type = $type ? 1 : 0;
        static $ip = null;
        if (null !== $ip) {
            return $ip[$type];
        }

        if ($adv) {
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $pos = array_search('unknown', $arr);
                if (false !== $pos) {
                    unset($arr[$pos]);
                }
                $ip = trim($arr[0]);
            } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (isset($_SERVER['REMOTE_ADDR'])) {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        // IP地址合法验证
        $long = sprintf("%u", ip2long($ip));
        $ip = $long ? array($ip, $long) : array('0.0.0.0', 0);
        return $ip[$type];
    }

    /**
     * 添加过滤函数
     *
     * @return Request
     */
    public function filter() {
        $filters = func_get_args();

        foreach ($filters as $callable) {
            if (is_callable($callable)) {
                $this->filters[] = $callable;
            }
        }

        return $this;
    }

    /**
     * 过滤
     *
     * @param  mixed $value
     * @return mixed
     */
    protected function sanitize($value) {
        foreach ($this->filters as $callable) {
            $value = is_array($value) ? array_map($callable, $value) : call_user_func($callable, $value);
        }

        // 清除
        $this->filters = array();

        return $value;
    }

    /**
     * 获取当前请求的php://input
     * @access public
     * @return string
     */
    public function getInput() {
        return file_get_contents('php://input');
    }

    /**
     * 获取一个请求变量
     *
     * 首先查找 POST 变量，最后才查找 GET 变量
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function input($key = null, $default = null) {
        if (is_null($key)) {
            return array_merge($this->get(), $this->post());
        }

        $value = $this->post($key);
        $value = $value ? $value : $this->get($key, $default);

        return $this->sanitize($value);
    }

    /**
     * 获取一个 GET 变量
     *
     * @param  string $key
     * @param  mixed  $default
     * @return mixed
     */
    public function get($key = null, $default = null) {
        if (is_null($key)) {
            return $_GET;
        }

        $value = isset($_GET[$key]) ? $_GET[$key] : $default;

        return $this->sanitize($value);
    }

    /**
     * 获取一个 POST 变量
     *
     * @param  string $key
     * @param  mixed  $default
     * @return mixed
     */
    public function post($key = null, $default = null) {
        if (is_null($key)) {
            return $_POST;
        }

        $value = isset($_POST[$key]) ? $_POST[$key] : $default;

        return $this->sanitize($value);
    }

    /**
     * 获取请求的 $_SERVER
     *
     * @return string
     */
    public function server($key, $default = null) {
        return isset($_SERVER[$key]) ? $_SERVER[$key] : $default;
    }

    /**
     * 获取环境变量
     * @param string $key
     * @param string $default     默认值
     * @return mixed
     */
    public function env($key, $default = null) {
        return isset($_ENV[$key]) ? $_ENV[$key] : $default;
    }

    /**
     * 当前请求 HTTP_CONTENT_TYPE
     * @access public
     * @return string
     */
    public function contentType() {
        $contentType = $this->server('CONTENT_TYPE');
        if ($contentType) {
            list($type) = explode(';', $contentType);
            return trim($type);
        }
        return '';
    }

    /**
     * 获取当前请求的时间
     * @access public
     * @param bool $float 是否使用浮点类型
     * @return integer|float
     */
    public function time($float = false) {
        return $float ? $_SERVER['REQUEST_TIME_FLOAT'] : $_SERVER['REQUEST_TIME'];
    }

    /**
     * 当前请求的资源类型
     * @access public
     * @return false|string
     */
    public function type() {
        $accept = $this->server('HTTP_ACCEPT');
        if (empty($accept)) {
            return false;
        }

        foreach ($this->mimeType as $key => $val) {
            $array = explode(',', $val);
            foreach ($array as $k => $v) {
                if (stristr($accept, $v)) {
                    return $key;
                }
            }
        }
        return false;
    }

    /**
     * 获取匹配的路由的参数
     *
     * @param  string $key
     * @param  mixed  $default
     * @return mixed
     */
    public function params($key = null, $default = null) {
        if (is_null($key)) {
            return $this->params;
        }

        $value = isset($this->params[$key]) ? $this->params[$key] : $default;

        return $this->sanitize($value);
    }

    /**
     * 添加匹配路由的参数
     *
     * @param array $params
     */
    public function setParams($params = []) {
        $this->params = $params;
    }

    /**
     * 获取请求的 PATH_INFO
     *
     * @return string
     */
    public function pathInfo() {
        if (is_null($this->pathInfo)) {
            $this->pathInfo = $this->detectPathInfo();
        }

        return $this->pathInfo;
    }

    /**
     * 获取 URI
     *
     * @return string
     */
    public function uri() {
        $pathInfo = $this->pathInfo();
        return $pathInfo ? $pathInfo : '/';
    }

    /**
     * 获取请求的 URI
     *
     * @return string
     */
    public function url() {
        if (is_null($this->requestUri)) {
            $this->requestUri = $this->detectUrl();
        }

        return $this->requestUri;
    }

    /**
     * 获取当前页面完整URL地址
     * @return string
     */
    public function get_full_url() {
        $sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
        $php_self = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME'];
        $path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
        $relate_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $php_self . (isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : $path_info);
        return $sys_protocal . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') . $relate_url;
    }

    /**
     * 获取请求的域名
     * @return string
     */
    public function get_host() {
        return (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
    }

    /**
     * 获取基地址
     *
     * 自动检测从请求环境的基本URL
     * 采用了多种标准, 以检测请求的基本URL
     *
     * <code>
     * /site/demo/index.php
     * </code>
     *
     * @param boolean $raw 是否编码
     * @return string
     */
    public function baseUrl($raw = false) {
        if (is_null($this->baseUrl)) {
            $this->baseUrl = rtrim($this->detectBaseUrl(), '/');
        }

        return $raw == false ? urldecode($this->baseUrl) : $this->baseUrl;
    }

    /**
     * 获取基路径, 不包含请求文件名
     *
     * <code>
     * /site/demo/
     * </code>
     *
     * @return string
     */
    public function basePath() {
        if (is_null($this->basePath)) {
            $this->basePath = rtrim($this->detectBasePath(), '/');
        }

        return $this->basePath;
    }

    /**
     * 检测 baseURL 和查询字符串之间的 PATH_INFO
     *
     * @return string
     */
    protected function detectPathInfo() {
        // 如果已经包含 PATH_INFO
        if (!empty($_SERVER['PATH_INFO'])) {
            return $_SERVER['PATH_INFO'];
        }

        if ('/' === ($requestUri = $this->url())) {
            return '';
        }

        $baseUrl = $this->baseUrl();
        $baseUrlEncoded = urlencode($baseUrl);

        if ($pos = strpos($requestUri, '?')) {
            $requestUri = substr($requestUri, 0, $pos);
        }

        if (!empty($baseUrl)) {
            if (strpos($requestUri, $baseUrl) === 0) {
                $pathInfo = substr($requestUri, strlen($baseUrl));
            } elseif (strpos($requestUri, $baseUrlEncoded) === 0) {
                $pathInfo = substr($requestUri, strlen($baseUrlEncoded));
            } else {
                $pathInfo = $requestUri;
            }
        } else {
            $pathInfo = $requestUri;
        }

        return $pathInfo;
    }

    /**
     * 测出请求的URI
     *
     * @return string
     */
    protected function detectUrl() {
        if (isset($_SERVER['HTTP_X_ORIGINAL_URL'])) {
            // 带微软重写模块的IIS
            $requestUri = $_SERVER['HTTP_X_ORIGINAL_URL'];
        } elseif (isset($_SERVER['HTTP_X_REWRITE_URL'])) {
            // 带ISAPI_Rewrite的IIS
            $requestUri = $_SERVER['HTTP_X_REWRITE_URL'];
        } elseif (
                isset($_SERVER['IIS_WasUrlRewritten']) && $_SERVER['IIS_WasUrlRewritten'] == '1' && isset($_SERVER['UNENCODED_URL']) && $_SERVER['UNENCODED_URL'] != ''
        ) {
            // URL重写的IIS7：确保我们得到的未编码的URL(双斜杠的问题)
            $requestUri = $_SERVER['UNENCODED_URL'];
        } elseif (isset($_SERVER['REQUEST_URI'])) {
            $requestUri = $_SERVER['REQUEST_URI'];
            // 只使用URL路径, 不包含scheme、主机[和端口]或者http代理
            if ($requestUri) {
                $requestUri = preg_replace('#^[^/:]+://[^/]+#', '', $requestUri);
            }
        } elseif (isset($_SERVER['ORIG_PATH_INFO'])) { // IIS 5.0, CGI
            $requestUri = $_SERVER['ORIG_PATH_INFO'];
            if (!empty($_SERVER['QUERY_STRING'])) {
                $requestUri .= '?' . $_SERVER['QUERY_STRING'];
            }
        } else {
            $requestUri = '/';
        }

        return $requestUri;
    }

    /**
     * 自动检测从请求环境的基本 URL
     * 采用了多种标准, 以检测请求的基本 URL
     *
     * @return string
     */
    protected function detectBaseUrl() {
        $baseUrl = '';
        $fileName = isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '';
        $scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : null;
        $phpSelf = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : null;
        $origScriptName = isset($_SERVER['ORIG_SCRIPT_NAME']) ? $_SERVER['ORIG_SCRIPT_NAME'] : null;

        if ($scriptName !== null && basename($scriptName) === $fileName) {
            $baseUrl = $scriptName;
        } elseif ($phpSelf !== null && basename($phpSelf) === $fileName) {
            $baseUrl = $phpSelf;
        } elseif ($origScriptName !== null && basename($origScriptName) === $fileName) {
            $baseUrl = $origScriptName;
        } else {
            $baseUrl = '/';
            $basename = basename($fileName);
            if ($basename) {
                $path = ($phpSelf ? trim($phpSelf, '/') : '');
                $baseUrl .= substr($path, 0, strpos($path, $basename)) . $basename;
            }
        }

        // 请求的URI
        $requestUri = $this->url();

        // 与请求的URI一样?
        if (0 === strpos($requestUri, $baseUrl)) {
            return $baseUrl;
        }

        $baseDir = str_replace('\\', '/', dirname($baseUrl));
        if (0 === strpos($requestUri, $baseDir)) {
            return $baseDir;
        }

        $basename = basename($baseUrl);

        if (empty($basename)) {
            return '';
        }

        if (strlen($requestUri) >= strlen($baseUrl) && (false !== ($pos = strpos($requestUri, $baseUrl)) && $pos !== 0)
        ) {
            $baseUrl = substr($requestUri, 0, $pos + strlen($baseUrl));
        }

        return $baseUrl;
    }

    /**
     * 自动检测请求的基本路径
     * 使用不同的标准来确定该请求的基本路径。
     *
     * @return string
     */
    protected function detectBasePath() {
        $fileName = isset($_SERVER['SCRIPT_FILENAME']) ? basename($_SERVER['SCRIPT_FILENAME']) : '';
        $baseUrl = $this->baseUrl();

        if ($baseUrl === '') {
            return '';
        }

        if (basename($baseUrl) === $fileName) {
            return str_replace('\\', '/', dirname($baseUrl));
        }

        return $baseUrl;
    }

    /**
     * 当前是否ssl
     * @access public
     * @return bool
     */
    public function isSsl() {
        return (isset($_SERVER['HTTPS']) && ('1' == $_SERVER['HTTPS'] || 'on' == strtolower($_SERVER['HTTPS']))) || (isset($_SERVER['REQUEST_SCHEME']) && 'https' == $_SERVER['REQUEST_SCHEME']) || (isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'])) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' == $_SERVER['HTTP_X_FORWARDED_PROTO']) ? true : false;
    }

    /**
     * 获取客户端系统语言
     *
     * @access public
     * @return string
     */
    public function get_user_lang() {
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return htmlspecialchars($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        }
        return '';
    }

    /**
     * 获取当前页面的url来源
     *
     * @access public
     * @return string
     */
    public function get_url_source() {
        if (!empty($_SERVER['HTTP_REFERER'])) {
            return htmlspecialchars($_SERVER['HTTP_REFERER']);
        }
        return '';
    }

    /**
     * 获取客户端浏览器信息.
     *
     * @access public
     * @return string
     */
    public function get_user_agent() {
        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            return htmlspecialchars($_SERVER['HTTP_USER_AGENT']);
        }
        return '';
    }

    /**
     * 客户端类型
     * @return string
     */
    public function equipment() {
        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            return 'Unknown';
        }
        $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
        $is_pc = (strpos($agent, 'windows nt')) ? true : false;
        $is_mac = (strpos($agent, 'mac os')) ? true : false;
        $is_iphone = (strpos($agent, 'iphone')) ? true : false;
        $is_android = (strpos($agent, 'android')) ? true : false;
        $is_ipad = (strpos($agent, 'ipad')) ? true : false;
        if ($is_ipad) {
            return 'Pad';
        }
        if ($is_iphone) {
            return 'Mobile';
        }
        if ($is_android) {
            return 'Mobile';
        }
        if ($is_pc) {
            return 'PC';
        }
        if ($is_mac) {
            return 'PC';
        }
    }

    /**
     * 是否移动端
     * @return boolean
     */
    public function isMobile() {
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            if ($this->isRobot() == true) {
                return true;
            }
            $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
            if (strpos($agent, 'iphone') !== false) {
                return true;
            }
            if (strpos($agent, 'android') !== false) {
                return true;
            }
            if (strpos($agent, 'ipad') !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * 判断是微信浏览器
     * @return boolean
     */
    public function isWeixin() {
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * 判断是App
     * @return boolean
     */
    public function isApp() {
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            if (strpos($_SERVER['HTTP_USER_AGENT'], 'app') !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * 判断是否为蜘蛛抓取网站
     */
    public function isRobot() {
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
            $keys = array('bot', 'slurp', 'spider', 'crawl', 'curl');
            foreach ($keys as $key) {
                if (strpos($agent, $key) !== false) {
                    return true;
                }
            }
        }
        return false;
    }

}
