<?php

namespace framework\nosql;

/**
 * 队列
 */
class Queue {

    public static function getInstance() {
        static $obj;
        if (!$obj) {
            $obj = new self();
        }
        return $obj;
    }

    /**
     * 加入队列
     * @param   string      $queue_name     队列名称
     * @param   array       $data           数据
     * @return boolean
     */
    public function qpush($queue_name = 'queue_task', $data = []) {
        return ssdbService::getInstance()->qpush($queue_name, serialize($data));
    }

    /**
     * 弹出队列数据
     * @param   string      $queue_name     队列名称
     * @param   int         $size           数量
     * @return boolean/array
     */
    public function qpop($queue_name = 'queue_task', $size = 1) {
        if ($size == 1) {
            $vo = ssdbService::getInstance()->qpop_front($queue_name, $size);
            if ($vo) {
                $vo = unserialize($vo);
                if ($vo) {
                    return $vo;
                }
            }
            return false;
        }
        $list = ssdbService::getInstance()->qpop_front($queue_name, $size);
        if ($list) {
            $data = [];
            foreach ($list as $key => $value) {
                $data[$key] = unserialize($value);
            }
            return $data;
        }
        return false;
    }

    /**
     * 获取所有队列名称列表
     * @return type
     */
    public function queue_list() {
        
    }

    /**
     * 查看队列数量
     * @param type $queue_name
     * @return int
     */
    public function size($queue_name = 'queue_task') {
        $rs = ssdbService::getInstance()->qsize($queue_name);
        if ($rs) {
            return $rs;
        }
        return 0;
    }

}
