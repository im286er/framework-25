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
     * 设置value,用于序列化存储
     * @param mixed $value
     * @return mixed
     */
    public function setValue($value) {
        if (!is_numeric($value)) {
            try {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
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
     * 加入队列
     * @param   string      $queue_name     队列名称
     * @param   array       $data           数据
     * @return boolean
     */
    public function qpush($queue_name = 'queue_task', $data = []) {
        return ssdbService::getInstance()->qpush($queue_name, $this->setValue($data));
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
                return $this->getValue($vo);
            }
            return false;
        }
        $list = ssdbService::getInstance()->qpop_front($queue_name, $size);
        if ($list) {
            $data = [];
            foreach ($list as $key => $value) {
                $data[$key] = $this->getValue($value);
            }
            return $data;
        }
        return false;
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
