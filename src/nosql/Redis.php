<?php

namespace framework\nosql;

use framework\core\Config;
use framework\core\Exception;

/**
 *  Redis 缓存驱动
 *
 * 要求安装phpredis扩展：https://github.com/phpredis/phpredis/
 *
 * https://github.com/nrk/predis
 *
 */
class Redis {

    private $conf;
    private $group = '_cache_';
    private $prefix = 'guipin_';
    private $tag = '_cache_';     /* 缓存标签 */
    private $ver = 0;
    private $link = [];
    private $hash;

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

        $this->conf = Config::getInstance()->get('redis_cache');

        if (empty($this->conf)) {
            throw new Exception('请配置 redis !');
        }

        $this->connect();
    }

    public static function getInstance() {
        static $obj;
        if (!$obj) {
            $obj = new self();
        }
        return $obj;
    }

    private function connect() {

        $this->hash = new Flexihash();

        foreach ($this->conf as $k => $conf) {
            $con = new \Redis;

            if ($conf['persistent'] == true) {
                $rs = $con->pconnect($conf['host'], $conf['port'], $conf['timeout'], 'persistent_id_' . $conf['select']);
            } else {
                $rs = $con->connect($conf['host'], $conf['port'], $conf['timeout']);
            }

            if ($rs == true) {
                $this->isConnected = true;
                $this->link[$k] = $con;

                $this->hash->addTarget($k);
            } else {
                $this->isConnected = false;
                /* 跳过 */
                continue;
            }

            if ('' != $conf['password']) {
                $con->auth($conf['password']);
            }

            if (0 != $conf['select']) {
                $con->select($conf['select']);
            }

            if (!empty($conf['prefix'])) {
                $this->prefix = $conf['prefix'];
            }
        }
    }

    /**
     * 获取实际的缓存标识
     * @access protected
     * @param  string $name 缓存名
     * @return string
     */
    protected function getCacheKey($name) {
        return $this->prefix . $name;
    }

    private function _getConForKey($key = '') {
        $i = $this->hash->lookup($key);
        return $this->link[$i];
    }

    /**
     * 检查是否能 ping 成功
     * @param type $key
     * @return boolean
     */
    public function ping($key = '') {
        return $this->_getConForKey($key)->ping() == '+PONG';
    }

    /**
     * 检查驱动是否可用
     * @return boolean      是否可用
     */
    public function is_available() {
        if (!$this->isConnected && $this->reConnected < $this->maxReConnected) {

            $this->connect();

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
     * 设置value,用于序列化存储
     * @param mixed $value
     * @return mixed
     */
    public function setValue($value) {
        if (!is_numeric($value)) {
            try {
                $value = json_encode($value);
            } catch (Exception $exc) {
                return false;
            }
        }
        return $value;
    }

    /**
     * 获取value,解析可能序列化的值
     * @param mixed $value
     * @return mixed
     */
    public function getValue($value, $default = false) {
        if (is_null($value) || $value === false) {
            return false;
        }
        if (!is_numeric($value)) {
            try {
                $value = json_decode($value, true);
            } catch (Exception $exc) {
                return $default;
            }
        }
        return $value;
    }

    /**
     * 设置缓存分组
     * @param type $group
     * @return $this
     */
    public function group($group = '_cache_') {
        $this->group = $group;

        $key = $this->getCacheKey('cache_ver_' . $this->group);

        try {
            /* 获取版本号 */
            $this->ver = $this->_getConForKey($key)->get($key);
            if ($this->ver) {
                return $this;
            }
            /* 设置新版本号 */
            $this->ver = $this->_getConForKey($key)->incrby($key, 1);
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
            /* 出错 */
            $this->ver = 0;
        }

        return $this;
    }

    /**
     * 按分组清空缓存
     * @param string $group
     * @return type
     * @return boolean
     */
    public function clear() {
        if ($this->group) {

            $key = $this->getCacheKey('cache_ver_' . $this->group);
            try {
                /* 获取新版本号 */
                $this->ver = $this->_getConForKey($key)->incrby($key, 1);

                /* 最大版本号修正 */
                if ($this->ver == PHP_INT_MAX) {
                    $this->ver = 1;
                    $this->_getConForKey($key)->set($key, 1);
                }

                return $this->ver;
            } catch (\Exception $ex) {
                //连接状态置为false
                $this->isConnected = false;
                $this->is_available();
            }
        }

        return true;
    }

    /**
     * 获取有分组的缓存
     * @access public
     * @param string $cache_id 缓存变量名
     * @param mixed  $default 默认值
     * @return mixed
     */
    public function get($cache_id, $default = false) {

        $key = $this->getCacheKey("{$this->group}_{$cache_id}");

        try {

            $value = $this->_getConForKey($key)->get($key);

            if (is_null($value) || false === $value) {
                return $default;
            }

            $data = $this->getValue($value, $default);

            if ($data && $data['ver'] == $this->ver) {
                return $data['data'];
            }
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
     * @param type $ttl      有效期(秒)
     * @return boolean
     */
    public function set($cache_id, $var, $ttl = 0) {
        $key = $this->getCacheKey("{$this->group}_{$cache_id}");

        /* 缓存数据 */
        $data = $this->setValue(['ver' => $this->ver, 'data' => $var]);

        try {
            if ($ttl == 0) {
                // 缓存 15 ~ 18 天
                $ttl = random_int(1296000, 1555200);
                return $this->_getConForKey($key)->setex($key, $ttl, $data);
            } else {
                // 有时间限制
                return $this->_getConForKey($key)->setex($key, $ttl, $data);
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
        $key = $this->getCacheKey("{$this->group}_{$cache_id}");

        try {
            return $this->_getConForKey($key)->delete($key);
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
        }
        return false;
    }

    /**
     * 获取字符串内指定位置的位值(BIT).
     * @param type $k           key
     * @param type $offset      位偏移
     * @return int      返回位值(0 或 1), 如果 key 不存在或者偏移超过活字符串长度范围, 返回 0.
     */
    public function getbit($k, $offset) {
        if ($this->is_available()) {
            return $this->_getConForKey($k)->getbit($k, $offset);
        }
        return 0;
    }

    /**
     * 设置字符串内指定位置的位值(BIT), 字符串的长度会自动扩展.
     * @param type $k           key
     * @param type $offset      位偏移, 取值范围 [0, 1073741824]
     * @param type $val          0 或 1
     * @return int      返回原来的位值. 如果 val 不是 0 或者 1, 返回 false.
     */
    public function setbit($k, $offset, $val) {
        if ($this->is_available()) {
            return $this->_getConForKey($k)->setbit($k, $offset, $val);
        }
        return 0;
    }

    /**
     * 计算字符串的子串所包含的位值为 1 的个数.
     * @param type $k           key
     * @return int              返回位值为 1 的个数. 出错返回 false.
     */
    public function bitcount($k) {
        if ($this->is_available()) {
            return $this->_getConForKey($k)->bitcount($k);
        }
        return 0;
    }

    /**
     * 计算字符串的长度(字节数).
     * @param type $k
     * @return int      返回字符串的长度, key 不存在则返回 0.
     */
    public function strlen($k) {
        if ($this->is_available()) {
            return $this->_getConForKey($k)->strlen($k);
        }
        return 0;
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
        $key = $this->getCacheKey($cache_id);

        if ($ttl <= 0) {
            $ttl = 1;
        }

        try {
            // 有时间限制
            return $this->_getConForKey($key)->set($key, 1, array('nx', 'ex' => $ttl));
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
        }
        return false;
    }

    /**
     * 对指定键名移除锁标记
     * @param string $cache_id      键名
     * @return boolean              是否成功
     */
    public function unlock($cache_id) {
        $key = "lock_{$cache_id}";

        return $this->simple_delete($key);
    }

    /**
     * 简单设置缓存
     * @param type $cache_id    缓存 key
     * @param type $var         缓存值
     * @param type $ttl      有效期(秒)
     * @return
     */
    public function simple_set($cache_id, $var, $ttl = 0) {
        $key = $this->getCacheKey($cache_id);
        $var = $this->setValue($var);

        try {
            if ($ttl == 0) {
                return $this->_getConForKey($key)->set($key, $var);
            } else {
                // 有时间限制
                return $this->_getConForKey($key)->setex($key, $ttl, $var);
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
     * @param type $cache_id    缓存名称
     * @param type $default     默认返回　false
     * @return boolean
     */
    public function simple_get($cache_id, $default = false) {
        $key = $this->getCacheKey($cache_id);
        try {
            $value = $this->_getConForKey($key)->get($key);

            if (is_null($value) || false === $value) {
                return $default;
            }

            return $this->getValue($value, $default);
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
        $key = $this->getCacheKey($cache_id);

        try {
            return $this->_getConForKey($key)->delete($key);
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
        }
        return false;
    }

    /**
     * 设置 key(只针对 KV 类型) 的存活时间.
     * @param type $k
     * @param type $ttl
     * @return type
     */
    public function expire($k, $ttl) {
        if ($this->is_available()) {
            return $this->_getConForKey($k)->expire($k, $ttl);
        }
        return false;
    }

    /**
     * 返回 key(只针对 KV 类型) 的存活时间.
     * @param type $k
     * @return type
     */
    public function ttl($k) {
        if ($this->is_available()) {
            return $this->_getConForKey($k)->ttl($k);
        }
        return false;
    }

    /**
     *  操作次数限制函数: 限制 uid 在 period 秒内能操作 action 最多 max_count 次.
     *  如果超过限制, 返回 false.
     * @param type $uid
     * @param type $action
     * @param type $max_count
     * @param type $period
     * @return boolean
     */
    public function act_limit($uid, $action, $max_count, $period) {
        $now = time();
        $expire = intval($now / $period) * $period + $period;
        $ttl = $expire - $now;
        $key = "act_limit_" . md5("{$uid}_{$action}");
        $count = $this->_getConForKey($key)->incr($key, 1);
        $this->expire($key, $ttl);
        if ($count === false || $count > $max_count) {
            return false;
        }
        return true;
    }

    /**
     * 自增缓存（针对数值缓存）
     * @access public
     * @param  string    $key 缓存变量名
     * @param  int       $step 步长
     * @return false|int
     */
    public function simple_inc($key, $step = 1) {
        return $this->_getConForKey($key)->incrby($key, $step);
    }

    /**
     * 自减缓存（针对数值缓存）
     * @access public
     * @param  string    $key 缓存变量名
     * @param  int       $step 步长
     * @return false|int
     */
    public function simple_dec($key, $step = 1) {
        return $this->_getConForKey($key)->decrby($key, $step);
    }

    /**
     * 列出处于区间 (key_start, key_end] 的 key-value 列表.
     * ("", ""] 表示整个区间.
     * 参数
     *      key_start - 返回的起始 key(不包含), 空字符串表示 -inf.
     *      key_end - 返回的结束 key(包含), 空字符串表示 +inf.
     *      limit - 最多返回这么多个元素.
     * 返回值
     *      如果出错则返回 false, 否则返回包含 key-value 的数关联组.
     */
    public function scan($key_start, $key_end, $limit) {
        if ($this->is_available()) {
            return $this->_getConForKey($key_start)->scan($key_start, $key_end, $limit);
        }
        return false;
    }

    /**
     * 返回哈希表 key 中给定域 field 的值。
     * @param type $cache_id    缓存名称
     * @param type $id          ID
     * @param type $default     默认返回　false
     * @return boolean/给定域的值
     */
    public function hget($cache_id, $id, $default = false) {
        $key = $this->getCacheKey($cache_id);
        try {
            $value = $this->_getConForKey($key)->hget($key, $id);

            if (is_null($value) || false === $value) {
                return $default;
            }

            return $this->getValue($value, $default);
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
        }
        return false;
    }

    /**
     * 将哈希表 key 中的域 id 的值设为 value, 如果不存在id ，会自动创建
     * @param type $cache_id    缓存 key
     * @param type $id          ID
     * @param type $var         缓存值
     * @return
     */
    public function hset($cache_id, $id, $var) {
        $key = $this->getCacheKey($cache_id);
        $var = $this->setValue($var);

        try {
            return $this->_getConForKey($key)->hset($key, $id, $var);
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
        }
        return false;
    }

    /**
     * 将哈希表 key 中的域 id 的值设为 value, 如果不存在ID的情况下，设置成功
     * @param type $cache_id    缓存 key
     * @param type $id          ID
     * @param type $var         缓存值
     * @return
     */
    public function hsetnx($cache_id, $id, $var) {
        $key = $this->getCacheKey($cache_id);
        $var = $this->setValue($var);

        try {
            return $this->_getConForKey($key)->hSetNx($key, $id, $var);
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
        }
        return false;
    }

    /**
     * 将哈希表 key 中的域 id 的值加 step
     * @param type $cache_id
     * @param type $id
     * @param int $step        整数或浮点数
     */
    public function hincr($cache_id, $id, $step = 1) {
        $key = $this->getCacheKey($cache_id);
        $var = $this->setValue($var);

        try {

            if (is_int($step) == true) {
                return $this->_getConForKey($key)->hIncrBy($key, $id, $step);
            }

            if (is_float($step) == false) {
                return $this->_getConForKey($key)->hIncrByFloat($key, $id, $step);
            }

            return false;
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
        }
        return false;
    }

    /**
     * 判断指定的 key 是否存在于 hashmap 中.
     * 参数
     *      name - hashmap 的名字.
     *      key -
     * 返回值
     *      如果存在, 返回 true, 否则返回 false.
     */
    public function hexists($name, $k) {
        if ($this->is_available()) {
            return $this->_getConForKey($name)->hExists($name, $k);
        }
        return false;
    }

    /**
     * 删除哈希表 key 中的一个指定域，不存在的域将被忽略。
     * @param type $cache_id
     * @param type $id          ID
     * @return boolean
     */
    public function hdel($cache_id, $id) {
        $key = $this->getCacheKey($cache_id);

        try {
            return $this->_getConForKey($key)->hdel($key, $id);
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
        }
        return false;
    }

    /**
     * 返回 hashmap 中的元素个数.
     * 参数
     *      name - hashmap 的名字.
     * 返回值
     *      出错则返回 false, 否则返回元素的个数, 0 表示不存在 hashmap(空).
     */
    public function hsize($name) {
        if ($this->is_available()) {
            return $this->_getConForKey($name)->hLen($name);
        }
        return false;
    }

    /**
     * 返回整个 hashmap.
     * 参数
     *      name - hashmap 的名字.
     * 返回值
     *      如果出错则返回 false, 否则返回包含 key-value 的关联数组.
     */
    public function hgetall($name) {
        if ($this->is_available()) {
            return $this->_getConForKey($name)->hGetAll($name);
        }
        return false;
    }

    /**
     * 将一个或多个值 value 插入到列表 key 的表尾(最右边)。
     * @param type $name
     * @param type $data
     * @return type
     */
    public function rPush($name = 'queue_task', $data = []) {
        $data = $this->setValue($data);
        return $this->_getConForKey($name)->rPush($name, $data);
    }

    /**
     * 将一个值 value 插入到列表头部
     * @param type $name
     * @param type $data
     * @return type
     */
    public function lPush($name = 'queue_task', $data = []) {
        $data = $this->setValue($data);
        return $this->_getConForKey($name)->lPush($name, $data);
    }

    /**
     * 命令用于移除并返回列表的第一个元素
     * @param type $name
     * @return boolean
     */
    public function lPop($name = 'queue_task') {
        $value = $this->_getConForKey($name)->lPop($name);
        if (is_null($value) || false === $value) {
            return false;
        }
        return $this->getValue($value, false);
    }

    /**
     * 移除并返回列表 key 的尾元素。
     * @param type $name
     * @return boolean
     */
    public function rPop($name = 'queue_task') {
        $value = $this->_getConForKey($name)->rPop($name);
        if (is_null($value) || false === $value) {
            return false;
        }
        return $this->getValue($value, false);
    }

    /**
     * 返回列表 key 的长度
     * @param string $name
     * @return boolean/int
     */
    public function lLen($name = 'queue_task') {
        $rs = $this->_getConForKey($name)->lLen($name);
        if ($rs) {
            return $rs;
        }
        return 0;
    }

    /**
     * 设置 zset 中指定 key 对应的权重值.
     * 参数
     *     name - zset 的名字.
     *     key - zset 中的 key.
     *     score - 整数, key 对应的权重值
     * 返回值
     *      出错则返回 false, 其它值表示正常.
     */
    public function zset($name, $k, $v) {
        if ($this->is_available()) {
            return $this->_getConForKey($name)->zAdd($name, $v, $k);
        }
        return false;
    }

    /**
     * 获取  中指定 key 的权重值.
     * 参数
     *       name - zset 的名字.
     *       key - zset 中的 key.
     * 返回值
     *       如果 key 不存在则返回 null, 如果出错则返回 false, 否则返回 key 对应的权重值.
     */
    public function zget($name, $k) {
        if ($this->is_available()) {
            return $this->_getConForKey($name)->zScore($name, $k);
        }
        return false;
    }

    /**
     * 获取 zset 中的指定 key.
     * 参数
     *        name - zset 的名字.
     *        key - zset 中的 key.
     * 返回值
     *        如果出错则返回 false, 其它值表示正常. 你无法通过返回值来判断被删除的 key 是否存在.
     */
    public function zdel($name, $k) {
        if ($this->is_available()) {
            return $this->_getConForKey($name)->zDelete($name, $k);
        }
        return false;
    }

    /**
     * 使 zset 中的 key 对应的值增加 num. 参数 num 可以为负数
     * 参数
     *      name - zset 的名字.
     *      key -
     *      num - 必须是有符号整数.
     * 返回值
     *      如果出错则返回 false, 否则返回新的值.
     */
    public function zincr($name, $k, $v) {
        if ($this->is_available()) {
            return $this->_getConForKey($name)->zIncrBy($name, $v, $k);
        }
        return false;
    }

    /**
     * 判断指定的 key 是否存在于 zset 中.
     * 参数
     *      name - zset 的名字.
     *      key -
     * 返回值
     *      如果存在, 返回 true, 否则返回 false.
     */
    public function zexists($name, $k) {
        if ($this->is_available()) {
            $rs = $this->_getConForKey($name)->zScore($name, $k);
            if ($rs == false) {
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * 返回 zset 中的元素个数.
     * 参数
     *       name - zset 的名字.
     * 返回值
     *       出错则返回 false, 否则返回元素的个数, 0 表示不存在 zset(空).
     */
    public function zsize($name) {
        if ($this->is_available()) {
            return $this->_getConForKey($name)->zSize($name);
        }
        return false;
    }

    /**
     * zrange, zrrange
     * 注意! 本方法在 offset 越来越大时, 会越慢!
     * 根据下标索引区间 [offset, offset + limit) 获取 key-score 对, 下标从 0 开始. zrrange 是反向顺序获取.
     * 参数
     *      name - zset 的名字.
     *      offset - 正整数, 从此下标处开始返回. 从 0 开始.
     *      limit - 正整数, 最多返回这么多个 key-score 对.
     * 返回值
     *      如果出错则返回 false, 否则返回包含 key-score 的关联数组.
     */
    public function zrange($name, $offset, $limit) {
        if ($this->is_available()) {
            return $this->_getConForKey($name)->zRange($name, $offset, $limit, true);
        }
        return false;
    }

    public function zrrange($name, $offset, $limit) {
        if ($this->is_available()) {
            return $this->_getConForKey($name)->zRevRange($name, $offset, $limit, true);
        }
        return false;
    }

    /**
     * 删除 zset 中的所有 key.
     * 参数
     *      name - zset 的名字.
     * 返回值
     *      如果出错则返回 false, 否则返回删除的 key 的数量.
     */
    public function zclear($name) {
        if ($this->is_available()) {
            return $this->_getConForKey($name)->delete($name);
        }
        return false;
    }

    /**
     *
     * 返回处于区间 [start,end] key 数量.
     * 参数
     *       name - zset 的名字.
     *       score_start - key 的最小权重值(包含), 空字符串表示 -inf.
     *       score_end - key 的最大权重值(包含), 空字符串表示 +inf.
     * 返回值
     *       如果出错则返回 false, 否则返回符合条件的 key 的数量.
     */
    public function zcount($name, $score_start, $score_end) {
        if ($this->is_available()) {
            return $this->_getConForKey($name)->zCount($name, $score_start, $score_end);
        }
        return false;
    }

    /**
     * 获取状态
     * @return type
     */
    public function get_stats() {
        $data = [];
        foreach ($this->link as $key => $value) {
            $data[$key] = $this->link[$key]->info();
        }
        return $data;
    }

    /**
     * 最好能保证它能最后析构!
     * 关闭连接
     */
    public function __destruct() {
        if (!empty($this->link)) {
            foreach ($this->link as $key => $value) {
                unset($this->link[$key]);
            }
        }
        unset($this->link);
        unset($this->isConnected);
    }

}
