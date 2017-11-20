<?php

namespace framework\db\Driver;

/**
 * MSSQL PDO驱动  sql2005
 */
class MSSQL extends DbDriver {
    /* 数据库表达式 */

    protected $exp = ['eq' => '=', 'neq' => '<>', 'gt' => '>', 'egt' => '>=', 'lt' => '<', 'elt' => '<=', 'notlike' => 'NOT LIKE', 'like' => 'LIKE', 'in' => 'IN', 'notin' => 'NOT IN', 'not in' => 'NOT IN', 'between' => 'BETWEEN', 'not between' => 'NOT BETWEEN', 'notbetween' => 'NOT BETWEEN'];
    /* 查询表达式 */
    protected $selectSql = 'SELECT T1.* FROM (SELECT thinkphp.*, ROW_NUMBER() OVER (%ORDER%) AS ROW_NUMBER FROM (SELECT %DISTINCT% %FIELD% FROM %TABLE%%JOIN%%WHERE%%GROUP%%HAVING%) AS thinkphp) AS T1 %LIMIT%%COMMENT%';
    /* */
    protected $_like_escape_chr = '!';

    /**
     * 创建数据库链接
     * 只有在需要创建时才创建，最小化调用
     *
     * @return resource 返回link_source
     */
    public function __connect() {
        try {
            $this->link = new PDO($this->db_config['dsn'], $this->db_config['username'], $this->db_config['password'], [PDO::ATTR_CASE => PDO::CASE_NATURAL]);
            $this->link->exec('SET QUOTED_IDENTIFIER ON');
            $this->link->exec('SET NAMES UTF8');
            $this->link->exec("SET ANSI_WARNINGS ON");
            $this->link->exec("SET ANSI_PADDING ON");
            $this->link->exec("SET ANSI_NULLS ON");
            $this->link->exec("SET CONCAT_NULL_YIELDS_NULL ON");
        } catch (PDOException $e) {
            $json = ['ret' => 500, 'data' => null, 'msg' => '连接数据库服务器失败:' . $e->getMessage()];
            ajax_return($json);
        }
    }

    public static function getInstance($db = 'mssql') {
        static $obj = [];
        if (!isset($obj[$db])) {
            $obj[$db] = new self($db);
        }
        return $obj[$db];
    }

    /**
     * 取得数据表的字段信息
     * @access public
     * @return array
     */
    public function getFields($tableName) {
        list($tableName) = explode(' ', $tableName);
        $result = $this->query("SELECT   column_name,   data_type,   column_default,   is_nullable
        FROM    information_schema.tables AS t
        JOIN    information_schema.columns AS c
        ON  t.table_catalog = c.table_catalog
        AND t.table_schema  = c.table_schema
        AND t.table_name    = c.table_name
        WHERE   t.table_name = '$tableName'");
        $info = array();
        if ($result) {
            foreach ($result as $key => $val) {
                $info[$val['column_name']] = array(
                    'name' => $val['column_name'],
                    'type' => $val['data_type'],
                    'notnull' => (bool) ($val['is_nullable'] === ''), // not null is empty, null is yes
                    'default' => $val['column_default'],
                    'primary' => false,
                    'autoinc' => false,
                );
            }
        }
        return $info;
    }

    /**
     * 取得数据表的字段信息
     * @access public
     * @return array
     */
    public function getTables() {
        $result = $this->query("SELECT TABLE_NAME
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_TYPE = 'BASE TABLE'
            ");
        $info = array();
        foreach ($result as $key => $val) {
            $info[$key] = current($val);
        }
        return $info;
    }

    /**
     * order分析
     * @access protected
     * @param mixed $order
     * @return string
     */
    protected function parseOrder($order) {
        return !empty($order) ? ' ORDER BY ' . $order : ' ORDER BY rand()';
    }

    /**
     * 随机排序
     * @access protected
     * @return string
     */
    protected function parseRand() {
        return 'rand()';
    }

    /**
     * 字段名分析
     * @access protected
     * @param string $key
     * @return string
     */
    protected function parseKey(&$key) {
        $key = trim($key);
        if (!is_numeric($key) && !preg_match('/[,\'\"\*\(\)\[.\s]/', $key)) {
            $key = '[' . $key . ']';
        }
        return $key;
    }

    function remove_invisible_characters($str, $url_encoded = TRUE) {
        $non_displayables = [];

        /* every control character except newline (dec 10) */
        /* carriage return (dec 13), and horizontal tab (dec 09) */

        if ($url_encoded) {
            $non_displayables[] = '/%0[0-8bcef]/'; // url encoded 00-08, 11, 12, 14, 15
            $non_displayables[] = '/%1[0-9a-f]/'; // url encoded 16-31
        }

        $non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S'; // 00-08, 11, 12, 14-31, 127

        do {
            $str = preg_replace($non_displayables, '', $str, -1, $count);
        } while ($count);

        return $str;
    }

    /**
     * SQL指令安全过滤
     * @access public
     * @param string $str  SQL字符串
     * @return string
     */
    public function escapeString($str) {
        $str = stripcslashes($str);
        $str = preg_replace(["/\('(.*)'\)/", "/\(\((.*)\)\)/", "/\((.*)\)/"], '$1', $str);

// Escape single quotes
        $str = str_replace("'", "''", $this->remove_invisible_characters($str));

        if (strtoupper($str) === 'NULL') {
            $str = null;
        }

        return $str;
    }

    /**
     * limit分析
     * @param type $limit
     * @return string
     */
    protected function parseLimit($limit) {
        if (empty($limit)) {
            return '';
        }
        $limit = explode(',', $limit);
        if (count($limit) > 1) {
            $limitStr = '(T1.ROW_NUMBER BETWEEN ' . $limit[0] . ' + 1 AND ' . $limit[0] . ' + ' . $limit[1] . ')';
        } else {
            $limitStr = '(T1.ROW_NUMBER BETWEEN 1 AND ' . $limit[0] . ")";
        }
        return 'WHERE ' . $limitStr;
    }

    /**
     * 更新记录
     * @access public
     * @param mixed $data 数据
     * @param array $options 表达式
     * @return false | integer
     */
    public function update($data, $options) {
        $this->model = $options['model'];
        $this->parseBind(!empty($options['bind']) ? $options['bind'] : []);
        $sql = 'UPDATE '
                . $this->parseTable($options['table'])
                . $this->parseSet($data)
                . $this->parseWhere(!empty($options['where']) ? $options['where'] : '')
                . $this->parseLock(isset($options['lock']) ? $options['lock'] : false)
                . $this->parseComment(!empty($options['comment']) ? $options['comment'] : '');

        /* 如果 where 为空,不允许执行 */
        if (empty($options['where'])) {
            $this->error = "[UPDATE 语句无 Where 条件] [ SQL语句 ] : {$sql} ";
            Log::write($this->error, Log::SQL);
            return false;
        }

        return $this->execute($sql, !empty($options['fetch_sql']) ? true : false);
    }

    /**
     * 删除记录
     * @access public
     * @param array $options 表达式
     * @return false | integer
     */
    public function delete($options = array()) {
        $this->model = $options['model'];
        $this->parseBind(!empty($options['bind']) ? $options['bind'] : array());
        $sql = 'DELETE FROM '
                . $this->parseTable($options['table'])
                . $this->parseWhere(!empty($options['where']) ? $options['where'] : '')
                . $this->parseLock(isset($options['lock']) ? $options['lock'] : false)
                . $this->parseComment(!empty($options['comment']) ? $options['comment'] : '');

        /* 如果 where 为空,不允许执行 */
        if (empty($options['where'])) {
            $this->error = "[DELETE 语句无 Where 条件] [ SQL语句 ] : {$sql} ";
            Log::write($this->error, Log::SQL);
            return false;
        }

        return $this->execute($sql, !empty($options['fetch_sql']) ? true : false);
    }

    /**
     * 执行语句  修正在 mssql 下插入会乱码
     * @access public
     * @param string $str  sql指令
     * @param boolean $fetchSql  不执行只是获取SQL
     * @return mixed
     */
    public function execute($str, $fetchSql = false) {
        if (!$this->link) {
            $this->__connect();
        }
        $this->queryString = $str;
        if (!empty($this->bind)) {
            $that = $this;
            $this->queryString = strtr($this->queryString, array_map(function($val) use($that) {
                        return '\'' . $that->escapeString($val) . '\'';
                    }, $this->bind));
        }
        if ($fetchSql) {
            return $this->queryString;
        }

        for ($i = 0; $i < 3; $i++) {
//释放前次的查询结果
            if (!empty($this->PDOStatement)) {
                $this->PDOStatement = null;
            }
            $this->PDOStatement = $this->link->prepare($this->queryString);
            if (false === $this->PDOStatement) {
                $this->error();
                return false;
            }

            try {
                $result = $this->PDOStatement->execute();
                if (false === $result) {
                    $this->error();
                    /* 出错重新连接数据库 */
                    if (($this->errno() == 'HY000') or ( $this->errno() == 2006) or ( $this->errno() == 2013)) {
                        $this->close();
                        $this->__connect();
                        if ($this->link) {
                            continue;
                        }
                    }
                    return false;
                } else {
                    $this->numRows = $this->PDOStatement->rowCount();
                    if (preg_match("/^\s*(INSERT\s+INTO|REPLACE\s+INTO)\s+/i", $this->queryString)) {
                        $this->lastInsID = $this->link->lastInsertId();
                    }
                    return $this->numRows;
                }
            } catch (PDOException $e) {
                /* 出错重新连接数据库 */
                if ($i < 2) {
                    $this->close();
                    $this->__connect();
                    if ($this->link) {
                        continue;
                    }
                } else {
                    $this->error();
                    return false;
                }
            }
        }
    }

}

//  end