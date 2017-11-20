<?php
namespace framework\ssdb;
/**
 * 队列
 */
class Queue {

    private $ssdb;

    function __construct() {
        $this->ssdb = ssdbService::getInstance();
    }

    public static function getInstance() {
        static $obj;
        if (!$obj) {
            $obj = new self();
        }
        return $obj;
    }

    /**
     * 从队列头部加入
     * @param type $name
     * @param type $data
     * @return type
     */
    public function qpush_front($name = 'task_queue', $data = []) {
        return $this->ssdb->qpush_front($name, serialize($data));
    }

    /**
     * 从队列尾部加入
     * @param type $name
     * @param type $data
     * @return type
     */
    public function qpush($name = 'task_queue', $data = []) {
        return $this->ssdb->qpush_back($name, serialize($data));
    }

    /**
     * 从队列首部弹出
     * @param type $name
     * @param type $size
     * @return boolean
     */
    public function qpop($name = 'task_queue', $size = 1) {
        if ($size == 1) {
            $vo = $this->ssdb->qpop_front($name, $size);
            if ($vo) {
                $vo = unserialize($vo);
                if ($vo) {
                    return $vo;
                }
            }
        } else {
            $list = $this->ssdb->qpop_front($name, $size);
            if ($list) {
                $data = [];
                foreach ($list as $key => $value) {
                    $data[$key] = unserialize($value);
                }
                return $data;
            }
        }
        return false;
    }

    /**
     * 查看队列数量
     * @param type $name
     * @return int
     */
    public function size($name = 'task_queue') {
        $rs = $this->ssdb->qsize($name);
        if ($rs) {
            return $rs;
        }
        return 0;
    }

}
