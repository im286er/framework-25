<?php

namespace framework\nosql;

use framework\core\Config;
use framework\core\Exception;

/**
 * Redis缓存驱动，适合单机部署、有前端代理实现高可用的场景，性能最好
 * 有需要在业务层实现读写分离、或者使用RedisCluster的需求，请使用Redisd驱动
 *
 * 要求安装phpredis扩展：https://github.com/phpredis/phpredis/
 */
class Redis {

    private $conf;
    private $group = '_cache_';
    private $prefix = 'vvjob_';
    private static $ver = [];
    private $link;

    /**
     * 是否连接server
     */
    private $isConnected = false;

    /**
     * 重连次数
     * @var int
     */
    private $reConnected = 0;

    /**
     * 最大重连次数,默认为3次
     * @var int
     */
    private $maxReConnected = 3;

    public function __construct() {

        if (!extension_loaded('redis')) {
            throw new Exception('当前环境不支持: redis');
        }

        $this->conf = Config::get('redis_cache');

        if (empty($this->conf)) {
            throw new Exception('请配置 redis !');
        }

        $this->connect();
    }

    private function connect() {
        $this->link = new \Redis;

        if ($this->conf['persistent']) {
            $this->link->pconnect($this->conf['host'], $this->conf['port'], $this->conf['timeout'], 'persistent_id_' . $this->conf['select']);
        } else {
            $this->link->connect($this->conf['host'], $this->conf['port'], $this->conf['timeout']);
        }

        if ('' != $this->conf['password']) {
            $this->link->auth($this->conf['password']);
        }

        if (0 != $this->conf['select']) {
            $this->link->select($this->conf['select']);
        }

        //如果获取服务器池的统计信息返回false,说明服务器池中有不可用服务器
        try {
            if ($this->link->info() === false) {
                $this->isConnected = false;
            } else {
                $this->isConnected = true;
            }
        } catch (\Exception $ex) {
            $this->isConnected = false;
        }
    }

    /**
     * 检查驱动是否可用
     * @return boolean      是否可用
     */
    public function is_available() {
        if (!$this->isConnected && $this->reConnected < $this->maxReConnected) {
            try {
                if ($this->link->ping() == '+PONG') {
                    $this->isConnected = true;
                }
            } catch (\RedisException $ex) {
                /* 记录连接异常 */
                $this->connect();
            }
            if (!$this->isConnected) {
                $this->reConnected++;
            }
            //如果重连成功,重连次数置为0
            else {
                $this->reConnected = 0;
            }
        }
        return $this->isConnected;
    }

    /**
     * 调用 Redis 自带方法
     * @param type $method
     * @param type $args
     * @return type
     * @throws \Exception
     */
    public function __call($method, $args) {
        if (method_exists($this->link, $method)) {
            return call_user_func_array(array($this->link, $method), $args);
        } else {
            throw new \Exception(__CLASS__ . ":{$method} is not exists!");
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
     * 设置缓存分组
     * @param type $group
     * @return $this
     */
    public function group($group = '_cache_') {
        $this->group = $group;
        /* 命令行下，不能采用静态变量缓存 */
        if (php_sapi_name() == "cli") {
            self::$ver[$this->group] = $this->getVer();
            return $this;
        }
        if (empty(self::$ver[$this->group])) {
            self::$ver[$this->group] = $this->getVer();
        }
        return $this;
    }

    private function getVer() {
        try {
            $key = $this->prefix . '_ver_' . $this->group;
            $ver = $this->link->get($key);
            if (empty($ver)) {
                $ver = $this->link->incrby("cache_ver_{$key}", 1);
                /* 设置到 memcached */
                self::$ver[$this->group] = intval($ver);
                $this->link->set($key, self::$ver[$this->group]);
            } else {
                /* 正常返回 */
                self::$ver[$this->group] = intval($ver);
                return self::$ver[$this->group];
            }
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
        }
        return 0;
    }

    /**
     * 按分组清空缓存
     * @param type $group
     * @return type
     */
    public function clear($group = '_cache_') {
        $this->group = $group;
        $key = $this->prefix . '_ver_' . $this->group;

        /* 获取新版本号 */
        $ver = $this->link->incrby("cache_ver_{$key}", 1);
        self::$ver[$this->group] = intval($ver);

        /* 写入 memcached 新版本号 */
        try {
            $this->link->set($key, self::$ver[$this->group]);
            return self::$ver[$this->group];
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
        }
        return false;
    }

    /**
     * 获取有分组的缓存
     * @access public
     * @param string $cache_id 缓存变量名
     * @param mixed  $default 默认值
     * @return mixed
     */
    public function get($cache_id, $default = false) {
        try {
            $key = $this->prefix . self::$ver[$this->group] . '_' . $this->group . '_' . $cache_id;
            $value = $this->link->get($key);

            if (is_null($value) || false === $value) {
                return $default;
            }

            try {
                $result = 0 === strpos($value, 'serialize:') ? unserialize(substr($value, 10)) : $value;
            } catch (\Exception $e) {
                $result = $default;
            }

            return $result;
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
        }
        return false;
    }

    /**
     * 设置有分组的缓存
     * @param type $cache_id    缓存 key
     * @param type $var         缓存值
     * @param type $expire      有效期(秒)
     * @return boolean
     */
    public function set($cache_id, $var, $expire = 0) {
        $key = $this->prefix . self::$ver[$this->group] . '_' . $this->group . '_' . $cache_id;

        $var = is_scalar($var) ? $var : 'serialize:' . serialize($var);

        try {
            if ($expire == 0) {
                // 缓存 3.5 天
                return $this->link->setex($key, 302400, $var);
            } else {
                // 有时间限制
                return $this->link->setex($key, $expire, $var);
            }
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
        }
        return false;
    }

    /**
     * 删除有分组的缓存
     * @param type $cache_id
     * @return type
     */
    public function delete($cache_id) {
        try {
            $key = $this->prefix . self::$ver[$this->group] . '_' . $this->group . '_' . $cache_id;
            return $this->link->delete($key);
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
        }
        return false;
    }

    /**
     * 对指定键名设置锁标记（此锁并不对键值做修改限制,仅为键名的锁标记）;
     * 此方法可用于防止惊群现象发生,在get方法获取键值无效时,先判断键名是否有锁标记,
     * 如果已加锁,则不获取新值;
     * 如果未加锁,则先设置锁，若设置失败说明锁已存在，若设置成功则获取新值,设置新的缓存
     * @param string $cache_id   键名
     * @param int $ttl     加锁时间
     * @return boolean      是否成功
     */
    public function lock($cache_id, $ttl = 5) {
        $key = "lock_{$cache_id}";
        try {
            $rs = $this->link->get($key);
            if ($rs) {
                return false;
            }
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
            return false;
        }

        try {
            return $this->link->setex($key, $ttl, '1');
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
        }
        return false;
    }

    /**
     * 判断键名是否有锁标记;<br>
     * 此方法可用于防止惊群现象发生,在get方法获取键值无效时,判断键名是否有锁标记
     * @param string $cache_id   键名
     * @return boolean      是否加锁
     */
    public function is_lock($cache_id) {
        $key = "lock_{$cache_id}";
        try {
            return (boolean) $this->link->get($key);
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
        }
        return true;
    }

    /**
     * 对指定键名移除锁标记
     * @param string $cache_id      键名
     * @return boolean              是否成功
     */
    public function unlock($cache_id) {
        $key = "lock_{$cache_id}";
        try {
            return $this->link->delete($key);
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
        }
        return false;
    }

    /**
     * 简单设置缓存
     * @param type $cache_id    缓存 key
     * @param type $var         缓存值
     * @param type $expire      有效期(秒)
     * @return
     */
    public function simple_set($cache_id, $var, $expire = 0) {
        $key = $this->prefix . $cache_id;
        $var = is_scalar($var) ? $var : 'serialize:' . serialize($var);

        try {
            if ($expire == 0) {
                return $this->link->set($key, $var);
            } else {
                // 有时间限制
                return $this->link->setex($key, $expire, $var);
            }
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
        }
        return false;
    }

    /**
     * 简单获取缓存
     * @param type $cache_id
     * @return type
     */
    public function simple_get($cache_id) {
        $key = $this->prefix . $cache_id;
        try {
            $value = $this->link->get($key);

            if (is_null($value) || false === $value) {
                return false;
            }

            try {
                $result = 0 === strpos($value, 'serialize:') ? unserialize(substr($value, 10)) : $value;
            } catch (\Exception $e) {
                $result = false;
            }

            return $result;
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
        }
        return false;
    }

    /**
     * 简单删除缓存
     * @param type $cache_id
     * @return type
     */
    public function simple_delete($cache_id) {
        $key = $this->prefix . $cache_id;
        try {
            return $this->link->delete($key);
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
        }
        return false;
    }

    /**
     * 操作次数限制函数: 限制 user_id 在 period 秒内能操作 action 最多 max_count 次.
     * 如果超过限制, 返回 false.
     * @param type $uid
     * @param type $action
     * @param type $max_count
     * @param type $period
     * @return boolean
     */
    public function act_limit($uid, $action, $max_count, $period) {
        $timestamp = time();
        $expire = intval($timestamp / $period) * $period + $period;
        $ttl = $expire - $timestamp;
        $key = "act_limit|{$uid}|{$action}";
        try {
            $count = $this->link->get($key);
            if ($count) {
                if ($count > $max_count) {
                    return false;
                }
            } else {
                $count = 1;
            }
            $count += 1;
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
            return false;
        }

        try {
            $this->link->setex($key, $ttl, $count);
            return true;
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
        }
        return false;
    }

    /**
     * 自增缓存（针对数值缓存）
     * @access public
     * @param  string    $key 缓存变量名
     * @param  int       $step 步长
     * @return false|int
     */
    public function simple_inc($key, $step = 1) {
        return $this->link->incrby($key, $step);
    }

    /**
     * 自减缓存（针对数值缓存）
     * @access public
     * @param  string    $key 缓存变量名
     * @param  int       $step 步长
     * @return false|int
     */
    public function simple_dec($key, $step = 1) {
        return $this->link->decrby($key, $step);
    }

    /**
     * 从队列尾部加入
     * @param type $name
     * @param type $data
     * @return type
     */
    public function queue_push($name = 'task_queue', $data = []) {

        $data = is_scalar($data) ? $data : 'serialize:' . serialize($data);

        return $this->link->rPush($name, $data);
    }

    /**
     * 从队列首部弹出
     * @param type $name
     * @return boolean
     */
    public function queue_pop($name = 'task_queue') {
        $value = $this->link->lPop($name);
        if (is_null($value) || false === $value) {
            return false;
        }
        try {
            $result = 0 === strpos($value, 'serialize:') ? unserialize(substr($value, 10)) : $value;
        } catch (\Exception $e) {
            $result = false;
        }
        return $result;
    }

    /**
     * 从队列首部弹出多个
     * @param type $name
     * @param type $size
     * @return boolean
     */
    public function queue_multi_pop($name = 'task_queue', $size = 1) {
        if ($size == 1) {
            return $this->queue_pop($name);
        }

        $total = $this->queue_size($name);
        if ($total == 0) {
            return false;
        }

        $max = min($size, $total);

        $data = [];

        for ($i = 0; $i < $max; $i++) {
            $value = $this->queue_pop($name);
            if ($value) {
                $data[$i] = $value;
            }
        }

        if (empty($data)) {
            return false;
        }

        return $data;
    }

    /**
     * 查看队列数量
     * @param type $name
     * @return int
     */
    public function queue_size($name = 'task_queue') {
        $rs = $this->link->lLen($name);
        if ($rs) {
            return $rs;
        }
        return 0;
    }

    /**
     * 获取状态
     * @return type
     */
    public function get_stats() {
        try {
            return $this->link->info();
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
        }
        return false;
    }

}
