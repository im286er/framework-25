<?php

namespace framework\nosql;

use framework\core\Exception;

/**
 * Sqlite缓存，主要使用在小型系统中
 * 
  'sqlite' => [
  'dsn' => 'sqlite::memory:',
  'username' => '',
  'password' => '',
  ],
 * 
 */
class SqliteCache {

    private $prefix = 'guipin_';
    private $group = '_cache_';
    private $ver = 0;
    private $cache_path = ROOT_PATH . 'cache/';
    private $link;
    //文件句柄
    private $lock_fp = [];

    /**
     * 架构函数
     * @param array $options
     */
    public function __construct() {

        $this->link = \framework\db\Driver\Sqlite::getInstance('sqlite');

        /* 初始化表 */
        $sql = "CREATE TABLE IF NOT EXISTS sharedmemory(
            cachekey VARCHAR(256) UNIQUE NOT NULL,
            data blob,            
            expire INTEGER 
            )";
        $this->link->execute($sql);
    }

    public static function getInstance() {
        static $obj;
        if (!$obj) {
            $obj = new self();
        }
        return $obj;
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

        /* 获取版本号 */
        $this->ver = $this->simple_get($key);
        if ($this->ver) {
            return $this;
        }
        /* 设置新版本号 */
        $this->ver = $this->inc($key, 1);

        return $this;
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

        $value = $this->simple_get($key);

        if (is_null($value) || false === $value) {
            return $default;
        }

        $data = $this->getValue($value, $default);

        if ($data && $data['ver'] == $this->ver) {
            return $data['data'];
        }

        return $default;
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

        if ($ttl == 0) {
            // 缓存 15 ~ 18 天
            $ttl = random_int(1296000, 1555200);
        }
        return $this->simple_set($key, $data, $ttl);
    }

    /**
     * 删除有分组的缓存
     * @param type $cache_id
     * @return type
     */
    public function delete($cache_id) {
        $key = $this->getCacheKey("{$this->group}_{$cache_id}");

        return $this->simple_delete($key);
    }

    /**
     * 取得变量名
     * @access protected
     * @param  string $name 缓存变量名
     * @return string
     */
    protected function getCacheKey($name) {
        return $this->prefix . $this->link->escapeString($name);
    }

    /**
     * 判断缓存是否存在
     * @access public
     * @param  string $name 缓存变量名
     * @return bool
     */
    public function has($name) {
        return $this->simple_get($name) ? true : false;
    }

    /**
     * 读取缓存
     * @access public
     * @param  string $name 缓存变量名
     * @param  mixed  $default 默认值
     * @return mixed
     */
    public function simple_get($name, $default = false) {

        $name = $this->getCacheKey($name);

        $sql = "SELECT data FROM sharedmemory WHERE cachekey = '{$name}'  AND (expire=0 OR expire >" . time() . ") LIMIT 1 ";

        $row = $this->link->query($sql);

        if (empty($row)) {
            return $default;
        }

        $content = $row[0]['data'];

        return $this->getValue($content, $default);
    }

    /**
     * 写入缓存
     * @access public
     * @param  string        $name 缓存变量名
     * @param  mixed         $value  存储数据
     * @param  int|\DateTime $expire  有效时间 0为永久
     * @return boolean
     */
    public function simple_set($name, $value, $expire = 0) {
        $name = $this->getCacheKey($name);
        $value = $this->setValue($value);
        $expire = ($expire == 0) ? 0 : (time() + $expire); //缓存有效期为0表示永久缓存

        $sql = "REPLACE INTO sharedmemory (cachekey, data, expire) VALUES ('{$name}', '{$value}', '{$expire}')";

        $rs = $this->link->execute($sql);
        if ($rs) {
            return true;
        }
        return false;
    }

    /**
     * 自增缓存（针对数值缓存）
     * @access public
     * @param  string    $name 缓存变量名
     * @param  int       $step 步长
     * @return false|int
     */
    public function inc($name, $step = 1) {
        if ($this->has($name)) {
            $value = $this->simple_get($name) + $step;
        } else {
            $value = $step;
        }

        return $this->simple_set($name, $value, 0) ? $value : false;
    }

    /**
     * 自减缓存（针对数值缓存）
     * @access public
     * @param  string    $name 缓存变量名
     * @param  int       $step 步长
     * @return false|int
     */
    public function dec($name, $step = 1) {
        if ($this->has($name)) {
            $value = $this->simple_get($name) - $step;
        } else {
            $value = -$step;
        }

        return $this->simple_set($name, $value, 0) ? $value : false;
    }

    /**
     * 简单删除缓存
     * @access public
     * @param  string $name 缓存变量名
     * @return boolean
     */
    public function simple_delete($name) {
        $name = $this->getCacheKey($name);

        $sql = " DELETE FROM sharedmemory WHERE cachekey = '{$name}' ";
        $this->link->execute($sql);

        return true;
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

            /* 获取新版本号 */
            $this->ver = $this->inc($key, 1);

            /* 最大版本号修正 */
            if ($this->ver == PHP_INT_MAX) {
                $this->ver = 1;
                $this->simple_set($key, 1);
            }

            return $this->ver;
        }

        return true;
    }

    /**
     * 对指定键名设置锁标记（此锁并不对键值做修改限制,仅为键名的锁标记）;
     * 此方法可用于防止惊群现象发生,在get方法获取键值无效时,先判断键名是否有锁标记,
     * 如果已加锁,则不获取新值;
     * 如果未加锁,则先设置锁，若设置失败说明锁已存在，若设置成功则获取新值,设置新的缓存
     * @param string $cache_id   键名
     * @return boolean      是否成功
     */
    public function lock($cache_id) {
        $key = "lock_{$cache_id}";

        $this->lock_fp[$key] = fopen($this->cache_path . $key, 'w+');
        if ($this->lock_fp[$key] === false) {
            return false;
        }
        return flock($this->lock_fp[$key], LOCK_EX);
    }

    /**
     * 对指定键名移除锁标记
     * @param string $cache_id      键名
     * @return boolean              是否成功
     */
    public function unlock($cache_id) {
        $key = "lock_{$cache_id}";

        if (isset($this->lock_fp[$key]) && $this->lock_fp[$key] !== false) {
            flock($this->lock_fp[$key], LOCK_UN);
            clearstatcache();
        }
        //进行关闭
        fclose($this->lock_fp[$key]);
        return $this->unlink($this->cache_path . $key);
    }

    /**
     * 清除全部缓存
     * @access public
     * @return boolean
     */
    public function flushall() {

        $sql = " TRUNCATE TABLE sharedmemory ";

        $this->link->execute($sql);

        return true;
    }

    /**
     * 判断文件是否存在后，删除
     * @access private
     * @param  string $path
     * @return boolean
     */
    private function unlink($path) {
        return is_file($path) && unlink($path);
    }

}
