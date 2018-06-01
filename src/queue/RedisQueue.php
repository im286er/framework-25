<?php

namespace framework\queue;

use framework\nosql\Redis;

/**
 * RedisQueue
 */
class RedisQueue implements IQueue {

    public static function getInstance() {
        static $obj;
        if (!$obj) {
            $obj = new self();
        }
        return $obj;
    }

    /**
     * @param $queueName
     * @return string
     */
    protected function getQueueKey($queueName) {
        return $queueName . '_QUEUE_KEY';
    }

    /**
     * 弹出队列数据
     * @param $queueName
     * @return mixed|null
     */
    protected function qpop_single($queueName) {
        $queueKey = $this->getQueueKey($queueName);
        $delayData = Redis::getInstance()->rPop("{$queueKey}.delayed");
        if ($delayData) {
            if ($delayData['time'] > time()) {
                Redis::getInstance()->lPush("{$queueKey}.delayed", $delayData);
            } else {
                Redis::getInstance()->lPush("{$queueKey}.waiting", $delayData['id']);
            }
        }
        if ($id = Redis::getInstance()->rPop("{$queueKey}.waiting")) {
            $message = Redis::getInstance()->hget("{$queueKey}.messages", $id);
            if ($message) {
                Redis::getInstance()->hdel("{$queueKey}.messages", $id);
                return $message;
            }
        }
        return false;
    }

    /**
     * 从队列首部弹出多个
     * @param       string      $name
     * @param       int         $size    默认 1
     * @return      boolean/array
     */
    public function qpop($name = 'queue_task', $size = 1) {
        if ($size == 1) {
            return $this->qpop_single($name);
        }

        $total = $this->size($name);
        if ($total == 0) {
            return false;
        }

        $max = min($size, $total);

        $data = [];

        for ($i = 0; $i < $max; $i++) {
            $value = $this->qpop_single($name);
            if ($value) {
                $data[$i] = $value;
            }
        }

        if (empty($data)) {
            return false;
        }

        return $data;
    }

    /**
     * 加入队列
     * @param $queueName
     * @param $data
     * @param int $delay
     * @return int
     */
    public function qpush($queueName, $data, $delay = 0) {
        $queueKey = $this->getQueueKey($queueName);
        $id = Redis::getInstance()->simple_inc("{$queueKey}.message_id");
        Redis::getInstance()->hSet("{$queueKey}.messages", $id, $data);
        if ($delay) {
            $delayData = [
                'id' => $id,
                'time' => time() + $delay,
            ];
            Redis::getInstance()->lPush("{$queueKey}.delayed", $delayData);
        } else {
            Redis::getInstance()->lPush("{$queueKey}.waiting", $id);
        }
        return $id;
    }

    /**
     * @param $queueName
     * @return int
     */
    public function size($queueName) {
        $queueKey = $this->getQueueKey($queueName);
        return Redis::getInstance()->lLen("{$queueKey}.waiting");
    }

}
