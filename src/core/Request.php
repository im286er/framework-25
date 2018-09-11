<?php

namespace framework\core;

/**
 * HTTP 请求
 */
class Request {

    /**
     * 请求类型
     * @var string
     */
    protected $method;

    /**
     * 域名（含协议及端口）
     * @var string
     */
    protected $domain;

    /**
     * 泛域名
     * @var string
     */
    protected $panDomain;

    /**
     * 当前URL地址
     * @var string
     */
    protected $url;

    /**
     * 基础URL
     * @var string
     */
    protected $baseUrl;

    /**
     * 当前执行的文件
     * @var string
     */
    protected $baseFile;

    /**
     * 访问的ROOT地址
     * @var string
     */
    protected $root;

    /**
     * pathinfo
     * @var string
     */
    protected $pathinfo;

    /**
     * pathinfo（不含后缀）
     * @var string
     */
    protected $path;

    /**
     * 当前请求参数
     * @var array
     */
    protected $param = [];

    /**
     * 当前GET参数
     * @var array
     */
    protected $get = [];

    /**
     * 当前POST参数
     * @var array
     */
    protected $post = [];

    /**
     * 当前REQUEST参数
     * @var array
     */
    protected $request = [];

    /**
     * 当前PUT参数
     * @var array
     */
    protected $put;

    /**
     * 当前SESSION参数
     * @var array
     */
    protected $session = [];

    /**
     * 当前COOKIE参数
     * @var array
     */
    protected $cookie = [];

    /**
     * 当前SERVER参数
     * @var array
     */
    protected $server = [];

    /**
     * 当前ENV参数
     * @var array
     */
    protected $env = [];

    /**
     * 当前HEADER参数
     * @var array
     */
    protected $header = [];

    /**
     * 资源类型定义
     * @var array
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
        'image' => 'image/png,image/jpg,image/jpeg,image/pjpeg,image/gif,image/webp,image/*',
        'csv' => 'text/csv',
        'html' => 'text/html,application/xhtml+xml,*/*',
    ];

    /**
     * 当前请求内容
     * @var string
     */
    protected $content;

    /**
     * 全局过滤规则
     * @var array
     */
    protected $filter;

    /**
     * 扩展方法
     * @var array
     */
    protected $hook = [];

    /**
     * php://input内容
     * @var string
     */
    // php://input
    protected $input;

    /**
     * 请求安全Key
     * @var string
     */
    protected $secureKey;

    /**
     * 架构函数
     * @access public
     * @param  array  $options 参数
     */
    public function __construct($options = []) {
        foreach ($options as $name => $item) {
            if (property_exists($this, $name)) {
                $this->$name = $item;
            }
        }

        // 保存 php://input
        $this->input = file_get_contents('php://input');
    }

    public function __call($method, $args) {
        if (array_key_exists($method, $this->hook)) {
            array_unshift($args, $this);
            return call_user_func_array($this->hook[$method], $args);
        } else {
            throw new Exception('method not exists:' . $method);
        }
    }

    /**
     * Hook 方法注入
     * @access public
     * @param  string|array  $method 方法名
     * @param  mixed         $callback callable
     * @return void
     */
    public function hook($method, $callback = null) {
        if (is_array($method)) {
            $this->hook = array_merge($this->hook, $method);
        } else {
            $this->hook[$method] = $callback;
        }
    }

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
     * 检测是否使用手机访问(不建议使用，可采用 https://github.com/serbanghita/Mobile-Detect )
     * @access public
     * @return bool
     */
    public function isMobile() {
        if (isset($_SERVER['HTTP_VIA']) && stristr($_SERVER['HTTP_VIA'], "wap")) {
            return true;
        } elseif (isset($_SERVER['HTTP_ACCEPT']) && strpos(strtoupper($_SERVER['HTTP_ACCEPT']), "VND.WAP.WML")) {
            return true;
        } elseif (isset($_SERVER['HTTP_X_WAP_PROFILE']) || isset($_SERVER['HTTP_PROFILE'])) {
            return true;
        } elseif (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/(blackberry|configuration\/cldc|hp |hp-|htc |htc_|htc-|iemobile|kindle|midp|mmp|motorola|mobile|nokia|opera mini|opera |Googlebot-Mobile|YahooSeeker\/M1A1-R2D2|android|iphone|ipod|mobi|palm|palmos|pocket|portalmmm|ppc;|smartphone|sonyericsson|sqh|spv|symbian|treo|up.browser|up.link|vodafone|windows ce|xda |xda_)/i', $_SERVER['HTTP_USER_AGENT'])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 判断是微信浏览器
     * @return boolean
     */
    public function isWeixin() {
        $agent = $this->get_user_agent();
        if (empty($agent)) {
            return false;
        }

        if (strpos($agent, 'MicroMessenger') !== false) {
            return true;
        }

        return false;
    }

    /**
     * 判断是App
     * @return boolean
     */
    public function isApp() {
        $agent = $this->get_user_agent();
        if (empty($agent)) {
            return false;
        }

        if (strpos($agent, 'app') !== false) {
            return true;
        }

        return false;
    }

    /**
     * 判断是否为蜘蛛抓取网站
     */
    public function isRobot() {
        $agent = $this->get_user_agent();
        if (empty($agent)) {
            return false;
        }

        $agent = strtolower($agent);
        $keys = array('bot', 'slurp', 'spider', 'crawl', 'curl');
        foreach ($keys as $key) {
            if (strpos($agent, $key) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * 创建一个URL请求
     * @access public
     * @param  string    $uri URL地址
     * @param  string    $method 请求类型
     * @param  array     $params 请求参数
     * @param  array     $cookie
     * @param  array     $files
     * @param  array     $server
     * @param  string    $content
     * @return Request
     */
    public function create($uri, $method = 'GET', $params = [], $cookie = [], $files = [], $server = [], $content = null) {
        $server['PATH_INFO'] = '';
        $server['REQUEST_METHOD'] = strtoupper($method);
        $info = parse_url($uri);

        if (isset($info['host'])) {
            $server['SERVER_NAME'] = $info['host'];
            $server['HTTP_HOST'] = $info['host'];
        }

        if (isset($info['scheme'])) {
            if ('https' === $info['scheme']) {
                $server['HTTPS'] = 'on';
                $server['SERVER_PORT'] = 443;
            } else {
                unset($server['HTTPS']);
                $server['SERVER_PORT'] = 80;
            }
        }

        if (isset($info['port'])) {
            $server['SERVER_PORT'] = $info['port'];
            $server['HTTP_HOST'] = $server['HTTP_HOST'] . ':' . $info['port'];
        }

        if (isset($info['user'])) {
            $server['PHP_AUTH_USER'] = $info['user'];
        }

        if (isset($info['pass'])) {
            $server['PHP_AUTH_PW'] = $info['pass'];
        }

        if (!isset($info['path'])) {
            $info['path'] = '/';
        }

        $options = [];
        $queryString = '';

        $options[strtolower($method)] = $params;

        if (isset($info['query'])) {
            parse_str(html_entity_decode($info['query']), $query);
            if (!empty($params)) {
                $params = array_replace($query, $params);
                $queryString = http_build_query($params, '', '&');
            } else {
                $params = $query;
                $queryString = $info['query'];
            }
        } elseif (!empty($params)) {
            $queryString = http_build_query($params, '', '&');
        }

        if ($queryString) {
            parse_str($queryString, $get);
            $options['get'] = isset($options['get']) ? array_merge($get, $options['get']) : $get;
        }

        $server['REQUEST_URI'] = $info['path'] . ('' !== $queryString ? '?' . $queryString : '');
        $server['QUERY_STRING'] = $queryString;
        $options['cookie'] = $cookie;
        $options['param'] = $params;
        $options['file'] = $files;
        $options['server'] = $server;
        $options['url'] = $server['REQUEST_URI'];
        $options['baseUrl'] = $info['path'];
        $options['pathinfo'] = '/' == $info['path'] ? '/' : ltrim($info['path'], '/');
        $options['method'] = $server['REQUEST_METHOD'];
        $options['domain'] = isset($info['scheme']) ? $info['scheme'] . '://' . $server['HTTP_HOST'] : '';
        $options['content'] = $content;

        foreach ($options as $name => $item) {
            if (property_exists($this, $name)) {
                $this->$name = $item;
            }
        }

        return $this;
    }

    /**
     * 设置或获取当前包含协议的域名
     * @access public
     * @param  string $domain 域名
     * @return string|$this
     */
    public function domain($domain = null) {
        if (!is_null($domain)) {
            $this->domain = $domain;
            return $this;
        } elseif (!$this->domain) {
            $this->domain = $this->scheme() . '://' . $this->host();
        }

        return $this->domain;
    }

    /**
     * 设置或获取当前泛域名的值
     * @access public
     * @return string|$this
     */
    public function panDomain($domain = null) {
        if (is_null($domain)) {
            return $this->panDomain;
        } else {
            $this->panDomain = $domain;
            return $this;
        }
    }

    /**
     * 设置或获取当前完整URL 包括QUERY_STRING
     * @access public
     * @param  string|true $url URL地址 true 带域名获取
     * @return string|$this
     */
    public function url($url = null) {
        if (!is_null($url) && true !== $url) {
            $this->url = $url;
            return $this;
        } elseif (!$this->url) {
            if ($this->isCli()) {
                $this->url = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : '';
            } elseif (isset($_SERVER['HTTP_X_REWRITE_URL'])) {
                $this->url = $_SERVER['HTTP_X_REWRITE_URL'];
            } elseif (isset($_SERVER['REQUEST_URI'])) {
                $this->url = $_SERVER['REQUEST_URI'];
            } elseif (isset($_SERVER['ORIG_PATH_INFO'])) {
                $this->url = $_SERVER['ORIG_PATH_INFO'] . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
            } else {
                $this->url = '';
            }
        }

        return true === $url ? $this->domain() . $this->url : $this->url;
    }

    /**
     * 设置或获取当前URL 不含QUERY_STRING
     * @access public
     * @param  string $url URL地址
     * @return string|$this
     */
    public function baseUrl($url = null) {
        if (!is_null($url) && true !== $url) {
            $this->baseUrl = $url;
            return $this;
        } elseif (!$this->baseUrl) {
            $str = $this->url();
            $this->baseUrl = strpos($str, '?') ? strstr($str, '?', true) : $str;
        }

        return true === $url ? $this->domain() . $this->baseUrl : $this->baseUrl;
    }

    /**
     * 设置或获取当前执行的文件 SCRIPT_NAME
     * @access public
     * @param  string $file 当前执行的文件
     * @return string|$this
     */
    public function baseFile($file = null) {
        if (!is_null($file) && true !== $file) {
            $this->baseFile = $file;
            return $this;
        } elseif (!$this->baseFile) {
            $url = '';
            if (!$this->isCli()) {
                $script_name = basename($_SERVER['SCRIPT_FILENAME']);
                if (basename($_SERVER['SCRIPT_NAME']) === $script_name) {
                    $url = $_SERVER['SCRIPT_NAME'];
                } elseif (basename($_SERVER['PHP_SELF']) === $script_name) {
                    $url = $_SERVER['PHP_SELF'];
                } elseif (isset($_SERVER['ORIG_SCRIPT_NAME']) && basename($_SERVER['ORIG_SCRIPT_NAME']) === $script_name) {
                    $url = $_SERVER['ORIG_SCRIPT_NAME'];
                } elseif (($pos = strpos($_SERVER['PHP_SELF'], '/' . $script_name)) !== false) {
                    $url = substr($_SERVER['SCRIPT_NAME'], 0, $pos) . '/' . $script_name;
                } elseif (isset($_SERVER['DOCUMENT_ROOT']) && strpos($_SERVER['SCRIPT_FILENAME'], $_SERVER['DOCUMENT_ROOT']) === 0) {
                    $url = str_replace('\\', '/', str_replace($_SERVER['DOCUMENT_ROOT'], '', $_SERVER['SCRIPT_FILENAME']));
                }
            }
            $this->baseFile = $url;
        }

        return true === $file ? $this->domain() . $this->baseFile : $this->baseFile;
    }

    /**
     * 设置或获取URL访问根地址
     * @access public
     * @param  string $url URL地址
     * @return string|$this
     */
    public function root($url = null) {
        if (!is_null($url) && true !== $url) {
            $this->root = $url;
            return $this;
        } elseif (!$this->root) {
            $file = $this->baseFile();
            if ($file && 0 !== strpos($this->url(), $file)) {
                $file = str_replace('\\', '/', dirname($file));
            }
            $this->root = rtrim($file, '/');
        }

        return true === $url ? $this->domain() . $this->root : $this->root;
    }

    /**
     * 获取URL访问根目录
     * @access public
     * @return string
     */
    public function rootUrl() {
        $base = $this->root();
        $root = strpos($base, '.') ? ltrim(dirname($base), DIRECTORY_SEPARATOR) : $base;

        if ('' != $root) {
            $root = '/' . ltrim($root, '/');
        }

        return $root;
    }

    /**
     * 获取当前请求URL的pathinfo信息（含URL后缀）
     * @access public
     * @return string
     */
    public function pathinfo() {
        if (is_null($this->pathinfo)) {
            if ($this->isCli()) {
                // CLI模式下 index.php module/controller/action/params/...
                $_SERVER['PATH_INFO'] = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : '';
            }

            // 分析PATHINFO信息
            if (!isset($_SERVER['PATH_INFO'])) {
                /* 兼容PATH_INFO获取 */
                foreach (['ORIG_PATH_INFO', 'REDIRECT_PATH_INFO', 'REDIRECT_URL'] as $type) {
                    if (!empty($_SERVER[$type])) {
                        $_SERVER['PATH_INFO'] = (0 === strpos($_SERVER[$type], $_SERVER['SCRIPT_NAME'])) ?
                                substr($_SERVER[$type], strlen($_SERVER['SCRIPT_NAME'])) : $_SERVER[$type];
                        break;
                    }
                }
            }

            $this->pathinfo = empty($_SERVER['PATH_INFO']) ? '/' : ltrim($_SERVER['PATH_INFO'], '/');
        }

        return $this->pathinfo;
    }

    /**
     * 获取当前请求URL的pathinfo信息(不含URL后缀)
     * @access public
     * @return string
     */
    public function path() {
        if (is_null($this->path)) {
            $suffix = Config::getInstance()->get('url_html_suffix');
            $pathinfo = $this->pathinfo();
            if (false === $suffix) {
                // 禁止伪静态访问
                $this->path = $pathinfo;
            } elseif ($suffix) {
                // 去除正常的URL后缀
                $this->path = preg_replace('/\.(' . ltrim($suffix, '.') . ')$/i', '', $pathinfo);
            } else {
                // 允许任何后缀访问
                $this->path = preg_replace('/\.' . $this->ext() . '$/i', '', $pathinfo);
            }
        }

        return $this->path;
    }

    /**
     * 当前URL的访问后缀
     * @access public
     * @return string
     */
    public function ext() {
        return pathinfo($this->pathinfo(), PATHINFO_EXTENSION);
    }

    /**
     * 获取当前请求的时间
     * @access public
     * @param  bool $float 是否使用浮点类型
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
     * 设置资源类型
     * @access public
     * @param  string|array  $type 资源类型名
     * @param  string        $val 资源类型
     * @return void
     */
    public function mimeType($type, $val = '') {
        if (is_array($type)) {
            $this->mimeType = array_merge($this->mimeType, $type);
        } else {
            $this->mimeType[$type] = $val;
        }
    }

    /**
     * 当前的请求类型
     * @access public
     * @param  bool $method  true 获取原始请求类型
     * @return string
     */
    public function method($method = false) {
        if (true === $method) {
            // 获取原始请求类型
            return $this->isCli() ? 'GET' : (isset($this->server['REQUEST_METHOD']) ? $this->server['REQUEST_METHOD'] : $_SERVER['REQUEST_METHOD']);
        } elseif (!$this->method) {
            /* 表单请求类型伪装变量 */
            if (isset($_POST['_method'])) {
                $this->method = strtoupper($_POST['_method']);
                $this->{$this->method}($_POST);
            } elseif (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
                $this->method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
            } else {
                $this->method = $this->isCli() ? 'GET' : (isset($this->server['REQUEST_METHOD']) ? $this->server['REQUEST_METHOD'] : $_SERVER['REQUEST_METHOD']);
            }
        }

        return $this->method;
    }

    /**
     * 是否为GET请求
     * @access public
     * @return bool
     */
    public function isGet() {
        return $this->method() == 'GET';
    }

    /**
     * 是否为POST请求
     * @access public
     * @return bool
     */
    public function isPost() {
        return $this->method() == 'POST';
    }

    /**
     * 是否为PUT请求
     * @access public
     * @return bool
     */
    public function isPut() {
        return $this->method() == 'PUT';
    }

    /**
     * 是否为DELTE请求
     * @access public
     * @return bool
     */
    public function isDelete() {
        return $this->method() == 'DELETE';
    }

    /**
     * 是否为HEAD请求
     * @access public
     * @return bool
     */
    public function isHead() {
        return $this->method() == 'HEAD';
    }

    /**
     * 是否为PATCH请求
     * @access public
     * @return bool
     */
    public function isPatch() {
        return $this->method() == 'PATCH';
    }

    /**
     * 是否为OPTIONS请求
     * @access public
     * @return bool
     */
    public function isOptions() {
        return $this->method() == 'OPTIONS';
    }

    /**
     * 是否为cli
     * @access public
     * @return bool
     */
    public function isCli() {
        return PHP_SAPI == 'cli';
    }

    /**
     * 是否为cgi
     * @access public
     * @return bool
     */
    public function isCgi() {
        return strpos(PHP_SAPI, 'cgi') === 0;
    }

    /**
     * 获取当前请求的参数
     * @access public
     * @param  mixed         $name 变量名
     * @param  mixed         $default 默认值
     * @param  string|array  $filter 过滤方法
     * @return mixed
     */
    public function param($name = '', $default = null, $filter = '') {
        if (empty($this->param)) {
            $method = $this->method(true);

            // 自动获取请求变量
            switch ($method) {
                case 'POST':
                    $vars = $this->post(false);
                    break;
                case 'PUT':
                case 'DELETE':
                case 'PATCH':
                    $vars = $this->put(false);
                    break;
                default:
                    $vars = [];
            }

            // 当前请求参数和URL地址中的参数合并
            $this->param = array_merge($this->get(false), $vars);
        }

        if (true === $name) {
            // 获取包含文件上传信息的数组
            $file = $this->file();
            $data = is_array($file) ? array_merge($this->param, $file) : $this->param;
            return $this->input($data, '', $default, $filter);
        }

        return $this->input($this->param, $name, $default, $filter);
    }

    /**
     * 设置获取GET参数
     * @access public
     * @param  mixed         $name 变量名
     * @param  mixed         $default 默认值
     * @param  string|array  $filter 过滤方法
     * @return mixed
     */
    public function get($name = '', $default = null, $filter = '') {
        if (empty($this->get)) {
            $this->get = $_GET;
        }

        if (is_array($name)) {
            $this->param = [];
            return $this->get = array_merge($this->get, $name);
        }

        return $this->input($this->get, $name, $default, $filter);
    }

    /**
     * 设置获取POST参数
     * @access public
     * @param  mixed         $name 变量名
     * @param  mixed         $default 默认值
     * @param  string|array  $filter 过滤方法
     * @return mixed
     */
    public function post($name = '', $default = null, $filter = '') {
        if (empty($this->post)) {
            $content = $this->input;
            if (empty($_POST) && false !== strpos($this->contentType(), 'application/json')) {
                $this->post = (array) json_decode($content, true, JSON_BIGINT_AS_STRING, 512);
            } else {
                $this->post = $_POST;
            }
        }

        if (is_array($name)) {
            $this->param = [];
            return $this->post = array_merge($this->post, $name);
        }

        return $this->input($this->post, $name, $default, $filter);
    }

    /**
     * 设置获取PUT参数
     * @access public
     * @param  mixed             $name 变量名
     * @param  mixed             $default 默认值
     * @param  string|array      $filter 过滤方法
     * @return mixed
     */
    public function put($name = '', $default = null, $filter = '') {
        if (is_null($this->put)) {
            $content = $this->input;
            if (false !== strpos($this->contentType(), 'application/json')) {
                $this->put = (array) json_decode($content, true, JSON_BIGINT_AS_STRING, 512);
            } else {
                parse_str($content, $this->put);
            }
        }

        if (is_array($name)) {
            $this->param = [];
            return $this->put = is_null($this->put) ? $name : array_merge($this->put, $name);
        }

        return $this->input($this->put, $name, $default, $filter);
    }

    /**
     * 设置获取DELETE参数
     * @access public
     * @param  mixed             $name 变量名
     * @param  mixed             $default 默认值
     * @param  string|array      $filter 过滤方法
     * @return mixed
     */
    public function delete($name = '', $default = null, $filter = '') {
        return $this->put($name, $default, $filter);
    }

    /**
     * 设置获取PATCH参数
     * @access public
     * @param  mixed             $name 变量名
     * @param  mixed             $default 默认值
     * @param  string|array      $filter 过滤方法
     * @return mixed
     */
    public function patch($name = '', $default = null, $filter = '') {
        return $this->put($name, $default, $filter);
    }

    /**
     * 获取request变量
     * @access public
     * @param  mixed         $name 数据名称
     * @param  string        $default 默认值
     * @param  string|array  $filter 过滤方法
     * @return mixed
     */
    public function request($name = '', $default = null, $filter = '') {
        if (empty($this->request)) {
            $this->request = $_REQUEST;
        }

        if (is_array($name)) {
            $this->param = [];
            return $this->request = array_merge($this->request, $name);
        }

        return $this->input($this->request, $name, $default, $filter);
    }

    /**
     * 获取session数据
     * @access public
     * @param  mixed         $name 数据名称
     * @param  string        $default 默认值
     * @param  string|array  $filter 过滤方法
     * @return mixed
     */
    public function session($name = '', $default = null, $filter = '') {

        if (empty($this->session)) {
            $this->session = Session::getInstance()->get();
        }

        if (is_array($name)) {
            return $this->session = array_merge($this->session, $name);
        }

        return $this->input($this->session, $name, $default, $filter);
    }

    /**
     * 获取cookie参数
     * @access public
     * @param  mixed         $name 数据名称
     * @param  string        $default 默认值
     * @param  string|array  $filter 过滤方法
     * @return mixed
     */
    public function cookie($name = '', $default = null, $filter = '') {
        $cookie = Cookie::getInstance();

        if (empty($this->cookie)) {
            $this->cookie = $cookie->get();
        }

        if (is_array($name)) {
            return $this->cookie = array_merge($this->cookie, $name);
        } elseif (!empty($name)) {
            $data = $cookie->has($name) ? $cookie->get($name) : $default;
        } else {
            $data = $this->cookie;
        }

        // 解析过滤器
        $filter = $this->getFilter($filter, $default);

        if (is_array($data)) {
            array_walk_recursive($data, [$this, 'filterValue'], $filter);
            reset($data);
        } else {
            $this->filterValue($data, $name, $filter);
        }

        return $data;
    }

    /**
     * 获取server参数
     * @access public
     * @param  mixed         $name 数据名称
     * @param  string        $default 默认值
     * @param  string|array  $filter 过滤方法
     * @return mixed
     */
    public function server($name = '', $default = null, $filter = '') {
        if (empty($this->server)) {
            $this->server = $_SERVER;
        }

        if (is_array($name)) {
            return $this->server = array_merge($this->server, $name);
        }

        return $this->input($this->server, false === $name ? false : strtoupper($name), $default, $filter);
    }

    /**
     * 获取环境变量
     * @access public
     * @param  mixed         $name 数据名称
     * @param  string        $default 默认值
     * @param  string|array  $filter 过滤方法
     * @return mixed
     */
    public function env($name = '', $default = null, $filter = '') {
        if (empty($this->env)) {
            $this->env = Container::get('env')->get();
        }

        if (is_array($name)) {
            return $this->env = array_merge($this->env, $name);
        }

        return $this->input($this->env, false === $name ? false : strtoupper($name), $default, $filter);
    }

    /**
     * 设置或者获取当前的Header
     * @access public
     * @param  string|array  $name header名称
     * @param  string        $default 默认值
     * @return string
     */
    public function header($name = '', $default = null) {
        if (empty($this->header)) {
            $header = [];
            if (function_exists('apache_request_headers') && $result = apache_request_headers()) {
                $header = $result;
            } else {
                $server = $this->server ?: $_SERVER;
                foreach ($server as $key => $val) {
                    if (0 === strpos($key, 'HTTP_')) {
                        $key = str_replace('_', '-', strtolower(substr($key, 5)));
                        $header[$key] = $val;
                    }
                }
                if (isset($server['CONTENT_TYPE'])) {
                    $header['content-type'] = $server['CONTENT_TYPE'];
                }
                if (isset($server['CONTENT_LENGTH'])) {
                    $header['content-length'] = $server['CONTENT_LENGTH'];
                }
            }
            $this->header = array_change_key_case($header);
        }

        if (is_array($name)) {
            return $this->header = array_merge($this->header, $name);
        }

        if ('' === $name) {
            return $this->header;
        }

        $name = str_replace('_', '-', strtolower($name));

        return isset($this->header[$name]) ? $this->header[$name] : $default;
    }

    /**
     * 获取变量 支持过滤和默认值
     * @access public
     * @param  array         $data 数据源
     * @param  string|false  $name 字段名
     * @param  mixed         $default 默认值
     * @param  string|array  $filter 过滤函数
     * @return mixed
     */
    public function input($data = [], $name = '', $default = null, $filter = '') {
        if (false === $name) {
            // 获取原始数据
            return $data;
        }

        $name = (string) $name;
        if ('' != $name) {
            // 解析name
            if (strpos($name, '/')) {
                list($name, $type) = explode('/', $name);
            } else {
                $type = 's';
            }
            // 按.拆分成多维数组进行判断
            foreach (explode('.', $name) as $val) {
                if (isset($data[$val])) {
                    $data = $data[$val];
                } else {
                    // 无输入数据，返回默认值
                    return $default;
                }
            }
            if (is_object($data)) {
                return $data;
            }
        }

        // 解析过滤器
        $filter = $this->getFilter($filter, $default);

        if (is_array($data)) {
            array_walk_recursive($data, [$this, 'filterValue'], $filter);
            reset($data);
        } else {
            $this->filterValue($data, $name, $filter);
        }

        if (isset($type) && $data !== $default) {
            // 强制类型转换
            $this->typeCast($data, $type);
        }

        return $data;
    }

    /**
     * 设置或获取当前的过滤规则
     * @access public
     * @param  mixed $filter 过滤规则
     * @return mixed
     */
    public function filter($filter = null) {
        if (is_null($filter)) {
            return $this->filter;
        } else {
            $this->filter = $filter;
        }
    }

    protected function getFilter($filter, $default) {
        if (is_null($filter)) {
            $filter = [];
        } else {
            $filter = $filter ?: $this->filter;
            if (is_string($filter) && false === strpos($filter, '/')) {
                $filter = explode(',', $filter);
            } else {
                $filter = (array) $filter;
            }
        }

        $filter[] = $default;

        return $filter;
    }

    /**
     * 递归过滤给定的值
     * @access public
     * @param  mixed     $value 键值
     * @param  mixed     $key 键名
     * @param  array     $filters 过滤方法+默认值
     * @return mixed
     */
    private function filterValue(&$value, $key, $filters) {
        $default = array_pop($filters);

        foreach ($filters as $filter) {
            if (is_callable($filter)) {
                // 调用函数或者方法过滤
                $value = call_user_func($filter, $value);
            } elseif (is_scalar($value)) {
                if (false !== strpos($filter, '/')) {
                    // 正则过滤
                    if (!preg_match($filter, $value)) {
                        // 匹配不成功返回默认值
                        $value = $default;
                        break;
                    }
                } elseif (!empty($filter)) {
                    // filter函数不存在时, 则使用filter_var进行过滤
                    // filter为非整形值时, 调用filter_id取得过滤id
                    $value = filter_var($value, is_int($filter) ? $filter : filter_id($filter));
                    if (false === $value) {
                        $value = $default;
                        break;
                    }
                }
            }
        }

        return $value;
    }

    /**
     * 强制类型转换
     * @access public
     * @param  string $data
     * @param  string $type
     * @return mixed
     */
    private function typeCast(&$data, $type) {
        switch (strtolower($type)) {
            // 数组
            case 'a':
                $data = (array) $data;
                break;
            // 数字
            case 'd':
                $data = (int) $data;
                break;
            // 浮点
            case 'f':
                $data = (float) $data;
                break;
            // 布尔
            case 'b':
                $data = (boolean) $data;
                break;
            // 字符串
            case 's':
            default:
                if (is_scalar($data)) {
                    $data = (string) $data;
                } else {
                    throw new \InvalidArgumentException('variable type error：' . gettype($data));
                }
        }
    }

    /**
     * 是否存在某个请求参数
     * @access public
     * @param  string    $name 变量名
     * @param  string    $type 变量类型
     * @param  bool      $checkEmpty 是否检测空值
     * @return mixed
     */
    public function has($name, $type = 'param', $checkEmpty = false) {
        if (empty($this->$type)) {
            $param = $this->$type();
        } else {
            $param = $this->$type;
        }

        // 按.拆分成多维数组进行判断
        foreach (explode('.', $name) as $val) {
            if (isset($param[$val])) {
                $param = $param[$val];
            } else {
                return false;
            }
        }

        return ($checkEmpty && '' === $param) ? false : true;
    }

    /**
     * 获取指定的参数
     * @access public
     * @param  string|array  $name 变量名
     * @param  string        $type 变量类型
     * @return mixed
     */
    public function only($name, $type = 'param') {
        $param = $this->$type();

        if (is_string($name)) {
            $name = explode(',', $name);
        }

        $item = [];
        foreach ($name as $key) {
            if (isset($param[$key])) {
                $item[$key] = $param[$key];
            }
        }

        return $item;
    }

    /**
     * 排除指定参数获取
     * @access public
     * @param  string|array  $name 变量名
     * @param  string        $type 变量类型
     * @return mixed
     */
    public function except($name, $type = 'param') {
        $param = $this->$type();
        if (is_string($name)) {
            $name = explode(',', $name);
        }

        foreach ($name as $key) {
            if (isset($param[$key])) {
                unset($param[$key]);
            }
        }

        return $param;
    }

    /**
     * 当前是否ssl
     * @access public
     * @return bool
     */
    public function isSsl() {
        $server = array_merge($_SERVER, $this->server);

        if (isset($server['HTTPS']) && ('1' == $server['HTTPS'] || 'on' == strtolower($server['HTTPS']))) {
            return true;
        } elseif (isset($server['REQUEST_SCHEME']) && 'https' == $server['REQUEST_SCHEME']) {
            return true;
        } elseif (isset($server['SERVER_PORT']) && ('443' == $server['SERVER_PORT'])) {
            return true;
        } elseif (isset($server['HTTP_X_FORWARDED_PROTO']) && 'https' == $server['HTTP_X_FORWARDED_PROTO']) {
            return true;
        }

        return false;
    }

    /**
     * 当前是否Ajax请求
     * @access public
     * @param  bool $ajax  true 获取原始ajax请求
     * @return bool
     */
    public function isAjax($ajax = false) {
        $value = $this->server('HTTP_X_REQUESTED_WITH', '', 'strtolower');
        $result = ('xmlhttprequest' == $value) ? true : false;

        if (true === $ajax) {
            return $result;
        } else {
            return $this->param('ajax') ? true : $result;
        }
    }

    /**
     * 当前是否Pjax请求
     * @access public
     * @param  bool $pjax  true 获取原始pjax请求
     * @return bool
     */
    public function isPjax($pjax = false) {
        $result = !is_null($this->server('HTTP_X_PJAX')) ? true : false;

        if (true === $pjax) {
            return $result;
        } else {
            return $this->param('pjax') ? true : $result;
        }
    }

    /**
     * 获取客户端IP地址
     * @access public
     * @param  integer   $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
     * @param  boolean   $adv 是否进行高级模式获取（有可能被伪装）
     * @return mixed
     */
    public function ip($type = 0, $adv = true) {
        $type = $type ? 1 : 0;

        if (isset($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif ($adv) {
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $pos = array_search('unknown', $arr);
                if (false !== $pos) {
                    unset($arr[$pos]);
                }
                $ip = trim(current($arr));
            } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (isset($_SERVER['REMOTE_ADDR'])) {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        // IP地址类型
        $ip_mode = (strpos($ip, ':') === false) ? 'ipv4' : 'ipv6';

        // IP地址合法验证
        if (filter_var($ip, FILTER_VALIDATE_IP) !== $ip) {
            $ip = ($ip_mode === 'ipv4') ? '0.0.0.0' : '::';
        }

        // 如果是ipv4地址，则直接使用ip2long返回int类型ip；如果是ipv6地址，暂时不支持，直接返回0
        $long_ip = ($ip_mode === 'ipv4') ? sprintf("%u", ip2long($ip)) : 0;

        $ip = [$ip, $long_ip];

        return $ip[$type];
    }

    /**
     * 当前URL地址中的scheme参数
     * @access public
     * @return string
     */
    public function scheme() {
        return $this->isSsl() ? 'https' : 'http';
    }

    /**
     * 当前请求URL地址中的query参数
     * @access public
     * @return string
     */
    public function query() {
        return $this->server('QUERY_STRING');
    }

    /**
     * 当前请求的host
     * @access public
     * @return string
     */
    public function host() {
        if (isset($_SERVER['HTTP_X_REAL_HOST'])) {
            return strtolower($_SERVER['HTTP_X_REAL_HOST']);
        }

        return strtolower($this->server('HTTP_HOST'));
    }

    /**
     * 当前请求URL地址中的port参数
     * @access public
     * @return integer
     */
    public function port() {
        return $this->server('SERVER_PORT', 80);
    }

    /**
     * 当前请求 SERVER_PROTOCOL
     * @access public
     * @return integer
     */
    public function protocol() {
        return $this->server('SERVER_PROTOCOL');
    }

    /**
     * 当前请求 REMOTE_PORT
     * @access public
     * @return integer
     */
    public function remotePort() {
        return $this->server('REMOTE_PORT');
    }

    /**
     * 当前请求 HTTP_CONTENT_TYPE
     * @access public
     * @return string
     */
    public function contentType() {
        $contentType = $this->server('CONTENT_TYPE');

        if ($contentType) {
            if (strpos($contentType, ';')) {
                list($type) = explode(';', $contentType);
            } else {
                $type = $contentType;
            }
            return trim($type);
        }

        return '';
    }

    /**
     * 设置或者获取当前请求的content
     * @access public
     * @return string
     */
    public function getContent() {
        if (is_null($this->content)) {
            $this->content = $this->input;
        }

        return $this->content;
    }

    /**
     * 获取当前请求的php://input
     * @access public
     * @return string
     */
    public function getInput() {
        return $this->input;
    }

    /**
     * 获取当前请求的安全Key
     * @access public
     * @return string
     */
    public function secureKey() {
        if (is_null($this->secureKey)) {
            $this->secureKey = uniqid('', true);
        }

        return $this->secureKey;
    }

}
