<?php

namespace framework\queue;

use framework\nosql\ssdbService;

/**
 * ssdbQueue
 */
class ssdbQueue implements IQueue {

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
    protected function setValue($value) {
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
    protected function getValue($value, $default = false) {
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
        $delayData = ssdbService::getInstance()->qpop_front("{$queueKey}.delayed");

        $delayData = $this->getValue($delayData);
        if ($delayData) {
            if ($delayData['time'] > time()) {
                ssdbService::getInstance()->qpush_back("{$queueKey}.delayed", $this->setValue($delayData));
            } else {
                ssdbService::getInstance()->qpush_back("{$queueKey}.waiting", $delayData['id']);
            }
        }

        /* 弹出 */
        $id = ssdbService::getInstance()->qpop_front("{$queueKey}.waiting");
        if ($id) {
            $message = ssdbService::getInstance()->hget("{$queueKey}.messages", $id);

            $message = $this->getValue($message);
            if ($message) {
                ssdbService::getInstance()->hdel("{$queueKey}.messages", $id);
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
        $id = ssdbService::getInstance()->incr("{$queueKey}.message_id");

        ssdbService::getInstance()->hset("{$queueKey}.messages", $id, $this->setValue($data));
        if ($delay) {
            $delayData = [
                'id' => $id,
                'time' => time() + $delay,
            ];
            ssdbService::getInstance()->qpush_back("{$queueKey}.delayed", $delayData);
        } else {
            ssdbService::getInstance()->qpush_back("{$queueKey}.waiting", $id);
        }
        return $id;
    }

    /**
     * @param $queueName
     * @return int
     */
    public function size($queueName) {
        $queueKey = $this->getQueueKey($queueName);
        return ssdbService::getInstance()->qsize("{$queueKey}.waiting");
    }

}
