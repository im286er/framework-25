<?php

/**
 * 缓存类  memcached
 */
class Cache {

    private $conf;
    private $group = '_cache_';
    private $prefix = 'vvjob_';
    private static $ver = [];
    private $link = [];

    public function __construct() {

        ini_set('memcache.hash_function', 'crc32');
        ini_set('memcache.hash_strategy', 'consistent');

        $this->conf = C('memcached_cache');
        if (empty($this->conf)) {
            throw new Exception('请配置 memcache !');
        }

        $this->link = new Memcache;
        /* 连接 memcached 缓存服务器 */
        foreach ($this->conf as $k => $conf) {
            $this->link->addServer($conf['host'], $conf['port']);
        }
    }

    public static function getInstance() {
        static $obj;
        if (!$obj) {
            $obj = new self();
        }
        return $obj;
    }

    public function __call($name, $arguments) {
        return $this;
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
        $key = $this->prefix . '_ver_' . $this->group;
        return intval($this->link->get($key));
    }

    /**
     * 按分组清空缓存
     * @param type $group
     * @return type
     */
    public function clear($group = '_cache_') {
        $this->group = $group;

        /* 获取旧版本号 */
        self::$ver[$this->group] = date('mdHis') + rand(1000, 9999);

        /* 写入新版本号 */
        $key = $this->prefix . '_ver_' . $this->group;
        $this->link->set($key, self::$ver[$this->group]);
        return self::$ver[$this->group];
    }

    /**
     * 获取有分组的缓存
     * @param type $cache_id
     * @return type
     */
    public function get($cache_id) {
        $key = $this->prefix . self::$ver[$this->group] . '_' . $this->group . '_' . $cache_id;
        return $this->link->get($key);
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

        if ($expire == 0) {
            // 缓存 3.5 天
            return $this->link->set($key, $var, MEMCACHE_COMPRESSED, 302400);
        } else {
            // 有时间限制
            if ($expire >= 2592000) {
                $expire = 0;
            }
            return $this->link->set($key, $var, MEMCACHE_COMPRESSED, $expire);
        }
    }

    /**
     * 删除有分组的缓存
     * @param type $cache_id
     * @return type
     */
    public function delete($cache_id) {
        $key = $this->prefix . self::$ver[$this->group] . '_' . $this->group . '_' . $cache_id;
        return $this->link->delete($key);
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
        $rs = $this->link->get($key);
        if ($rs) {
            return false;
        }
        return $this->link->set($key, '1', 0, $ttl);
    }

    /**
     * 判断键名是否有锁标记;<br>
     * 此方法可用于防止惊群现象发生,在get方法获取键值无效时,判断键名是否有锁标记
     * @param string $cache_id   键名
     * @return boolean      是否加锁
     */
    public function is_lock($cache_id) {
        $key = "lock_{$cache_id}";
        return (boolean) $this->link->get($key);
    }

    /**
     * 对指定键名移除锁标记
     * @param string $cache_id      键名
     * @return boolean              是否成功
     */
    public function unlock($cache_id) {
        $key = "lock_{$cache_id}";
        return $this->link->delete($key);
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
        if ($expire == 0) {
            // 缓存 7 天
            return $this->link->set($key, $var, MEMCACHE_COMPRESSED, 604800);
        } else {
            // 有时间限制
            if ($expire >= 2592000) {
                $expire = 0;
            }
            return $this->link->set($key, $var, MEMCACHE_COMPRESSED, $expire);
        }
    }

    /**
     * 简单获取缓存
     * @param type $cache_id
     * @return type
     */
    public function simple_get($cache_id) {
        $key = $this->prefix . $cache_id;
        return $this->link->get($key);
    }

    /**
     * 简单删除缓存
     * @param type $cache_id
     * @return type
     */
    public function simple_delete($cache_id) {
        $key = $this->prefix . $cache_id;
        return $this->link->delete($key);
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
        $count = $this->link->get($key);
        if ($count) {
            if ($count > $max_count) {
                return false;
            }
        } else {
            $count = 1;
        }
        $count += 1;
        $this->link->set($key, $count, 0, $ttl);
        return true;
    }

    /**
     * 获取状态
     * @return type
     */
    public function get_stats() {

        $stats = $this->link->getStats();
        return [
            'hits' => $stats['get_hits'],
            'misses' => $stats['get_misses'],
            'uptime' => $stats['uptime'],
            'memory_usage' => $stats['bytes'],
            'memory_available' => $stats['limit_maxbytes'],
        ];
    }

}
