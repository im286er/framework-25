<?php

namespace framework\queue;

use framework\nosql\Redis;

/**
 * RedisQueue
 */
class RedisQueue {

    public static function getInstance() {
        static $obj;
        if (!$obj) {
            $obj = new self();
        }
        return $obj;
    }

    protected function getQueueKey($queue_name) {
        if (empty($queue_name)) {
            throw new \LengthException('队列名称不能为空', 410);
        }
        return 'queue_' . $queue_name;
    }

    /**
     * 弹出队列数据
     * @param   string      $queue_name     队列名称
     * @param   int         $size           数量
     * @return boolean/array
     */
    public function qpop($queue_name = 'queue_task', $size = 1) {
        $queue_name = $this->getQueueKey($queue_name);

        if ($size == 1) {
            $vo = Redis::getInstance()->lPop($queue_name);
            if ($vo) {
                return $vo;
            }
            return false;
        }

        $data = [];
        for ($i = 0; $i < $size; $i++) {
            $vo = Redis::getInstance()->lPop($queue_name);
            if ($vo) {
                $data[] = $vo;
            }
        }
        return $data;
    }

    /**
     * 加入队列
     * @param   string      $queue_name     队列名称
     * @param   array       $data           数据
     * @return boolean
     */
    public function qpush($queue_name = 'queue_task', $data = []) {
        $queue_name = $this->getQueueKey($queue_name);

        return Redis::getInstance()->rPush($queue_name, $data);
    }

    /**
     * 查看队列数量
     * @param type $queue_name
     * @return int
     */
    public function size($queue_name) {
        $queue_name = $this->getQueueKey($queue_name);

        return Redis::getInstance()->lLen($queue_name);
    }

}
