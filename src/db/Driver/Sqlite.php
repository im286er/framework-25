<?php

namespace framework\db\Driver;

use framework\core\Exception;

/**
 * Sqlite数据库驱动
 */
class Sqlite extends DbDriver {

    public static function getInstance($db = 'sqlite') {
        static $obj = [];
        if (!isset($obj[$db])) {
            $obj[$db] = new self($db);
        }
        return $obj[$db];
    }

    /**
     * 创建数据库链接
     */
    public function __connect() {

        //         'sqlite' => ['dsn' => 'sqlite:' . ROOT_PATH . 'cache/cache.db'],

        try {
            $this->link = new \PDO($this->db_config['dsn'], '', '', $this->options);
        } catch (\PDOException $e) {
            throw new Exception('连接数据库服务器失败:' . $e->getMessage(), 500);
        }
    }

    /**
     * 取得数据表的字段信息
     * @access public
     * @return array
     */
    public function getFields($tableName) {
        list($tableName) = explode(' ', $tableName);
        $result = $this->query('PRAGMA table_info( ' . $tableName . ' )');
        $info = array();
        if ($result) {
            foreach ($result as $key => $val) {
                $info[$val['field']] = array(
                    'name' => $val['field'],
                    'type' => $val['type'],
                    'notnull' => (bool) ($val['null'] === ''), // not null is empty, null is yes
                    'default' => $val['default'],
                    'primary' => (strtolower($val['dey']) == 'pri'),
                    'autoinc' => (strtolower($val['extra']) == 'auto_increment'),
                );
            }
        }
        return $info;
    }

    /**
     * 取得数据库的表信息
     * @access public
     * @return array
     */
    public function getTables($dbName = '') {
        $result = $this->query("SELECT name FROM sqlite_master WHERE type='table' "
                . "UNION ALL SELECT name FROM sqlite_temp_master "
                . "WHERE type='table' ORDER BY name");
        $info = array();
        foreach ($result as $key => $val) {
            $info[$key] = current($val);
        }
        return $info;
    }

    /**
     * SQL指令安全过滤
     * @access public
     * @param string $str  SQL指令
     * @return string
     */
    public function escapeString($str) {
        return str_ireplace("'", "''", $str);
    }

    /**
     * limit
     * @access public
     * @return string
     */
    public function parseLimit($limit) {
        $limitStr = '';
        if (!empty($limit)) {
            $limit = explode(',', $limit);
            if (count($limit) > 1) {
                $limitStr .= ' LIMIT ' . $limit[1] . ' OFFSET ' . $limit[0] . ' ';
            } else {
                $limitStr .= ' LIMIT ' . $limit[0] . ' ';
            }
        }
        return $limitStr;
    }

}
