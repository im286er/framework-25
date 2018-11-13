<?php

namespace framework\session;

use framework\core\Config;
use framework\core\Exception;
use framework\db\Model\MYSQLModel;

/**
 * 数据库方式Session驱动
  CREATE TABLE `session` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` varchar(255) NOT NULL,
  `session_expire` int(11) NOT NULL,
  `session_data` blob DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_id` (`session_id`)
  );
 */
class dbDriver extends \SessionHandler {

    private $ttl = 7200;       /* 2小时 */
    private $db;

    public function open($savePath, $sessionName) {

        $db_config = Config::getInstance()->get('database');
        if (empty($db_config)) {
            throw new Exception('请配置数据库');
        }
        foreach ($db_config as $key => $value) {
            $session_db = $key;
            break;
        }

        $this->db = MYSQLModel::getInstance('session', $session_db);
        return true;
    }

    public function close() {
        $this->gc($this->ttl);
        return true;
    }

    public function read($session_id) {
        if (empty($session_id)) {
            return '';
        }

        $vo = $this->db->where(['session_id' => $session_id, 'session_expire' => ['gt', time()]])->find();
        if ($vo) {
            return (string) $vo['session_data'];
        }

        return '';
    }

    public function write($session_id, $session_data) {
        if (empty($session_id)) {
            return false;
        }
        $data = [
            'session_id' => $session_id,
            'session_data' => $session_data,
            'session_expire' => time() + $this->ttl,
        ];
        $this->db->add($data, [], true);
        return true;
    }

    public function destroy($session_id) {
        if (empty($session_id)) {
            return false;
        }
        $this->db->where(['session_id' => $session_id])->delete();
        return true;
    }

    public function gc($maxlifetime) {
        $this->db->where(['session_expire' => ['lt', time()]])->delete();
        return true;
    }

}
