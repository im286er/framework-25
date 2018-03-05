<?php

namespace framework\nosql;

/**
 * 延时队列
 */
class delayQueue {

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
     * 加入队列
     * @param   string      $queue_name     队列名称
     * @param   array       $data           数据
     * @param   int         $ttl            延时时间(300秒)
     * @return  int         $id             队列编号
     */
    public function qpush($queue_name = 'task_queue', $data = [], $ttl = 300) {

        $zname = "delay_queue_{$queue_name}";
        $hname = "delay_queue_{$queue_name}";

        /* 记录队列顺序 */
        $id = $this->ssdb->zincr('tickets_id', 'delay_queue_id', 1);
        if ($id == PHP_INT_MAX) {
            $id = 1;
            $this->ssdb->zset('tickets_id', 'delay_queue_id', 1);
        }

        /* 记录数据 */
        /* 修正延时 */
        $ttl = ($ttl <= 0) ? 10 : $ttl;
        $time = time() + $ttl;
        $this->ssdb->zset($zname, $id, $time);
        $this->ssdb->hset($hname, $id, serialize($data));

        /* 积压队列数量 */
        $this->ssdb->zincr('delay_queue', $queue_name, 1);

        return $id;
    }

    /**
     * 弹出队列数据
     * @param   string      $queue_name     队列名称
     * @param   int         $size           数量
     * @return boolean/array
     */
    public function qpop($queue_name = 'task_queue', $size = 1) {

        /* 检查积压数量 */
        $zname = "delay_queue_{$queue_name}";
        $hname = "delay_queue_{$queue_name}";
        $total = $this->size($queue_name);
        if ($total == 0) {
            return false;
        }

        /* 加锁 */
        $rs = Redis::getInstance()->lock($zname, 60);
        if ($rs == false) {
            /* 加锁失败 */
            return false;
        }

        $return_data = [];
        /* 获取数据 */
        $score_end = time();
        $size = ($size > 100 && $size <= 0) ? 100 : $size;
        $items = $this->ssdb->zscan($zname, '', 1, $score_end, $size);
        if ($items) {
            foreach ($items as $id => $time) {
                /* 组合数据 */
                $value = $this->ssdb->hget($hname, $id);
                /* 删除队列数据 */
                $this->ssdb->zdel($zname, $id);
                $this->ssdb->hdel($hname, $id);
                if (empty($value)) {
                    continue;
                }
                $data = unserialize($value);
                if ($data) {
                    $return_data[] = $data;
                }
            }
        }

        /* 修正统计 */
        $total = $this->size($queue_name);
        $this->ssdb->zset('delay_queue', $queue_name, $total);

        /* 解锁 */
        Redis::getInstance()->unlock($zname);

        /* 返回 */
        if (empty($return_data)) {
            return false;
        }
        return $return_data;
    }

    /**
     * 弹出队列数据自动转正式队列
     * @param   string      $queue_name     队列名称
     * @param   int         $size           数量
     * @return boolean/array
     */
    public function move_to_queue($queue_name = 'task_queue', $size = 1) {

        /* 检查积压数量 */
        $zname = "delay_queue_{$queue_name}";
        $hname = "delay_queue_{$queue_name}";
        $total = $this->size($queue_name);
        if ($total == 0) {
            return false;
        }

        /* 加锁 */
        $rs = Redis::getInstance()->lock($zname, 60);
        if ($rs == false) {
            /* 加锁失败 */
            return false;
        }

        /* 获取数据 */
        $score_end = time();
        $size = ($size > 100 && $size <= 0) ? 100 : $size;
        $items = $this->ssdb->zscan($zname, '', 1, $score_end, $size);
        if ($items) {
            foreach ($items as $id => $time) {
                /* 组合数据 */
                $value = $this->ssdb->hget($hname, $id);
                /* 删除队列数据 */
                $this->ssdb->zdel($zname, $id);
                $this->ssdb->hdel($hname, $id);
                if (empty($value)) {
                    continue;
                }
                /* 存入正式队列 */
                $data = unserialize($value);
                Redis::getInstance()->queue_push($queue_name, $data);
            }
        }

        /* 修正统计 */
        $total = $this->size($queue_name);
        $this->ssdb->zset('delay_queue', $queue_name, $total);

        /* 解锁 */
        Redis::getInstance()->unlock($zname);

        /* 返回 */
        return true;
    }

    /**
     * 获取所有延时队列名称列表
     * @param type $page
     * @param type $size
     * @return type
     */
    public function queue_list($page = 1, $size = 20) {
        $zname = 'delay_queue';

        $total = $this->ssdb->zsize($zname);
        $total = intval($total);
        $max_page = ceil($total / $size);

        /* 返回数据结果 */
        $data = [
            'total' => $total,
            'max_page' => $max_page,
            'size' => $size,
            'page' => $page,
            'list' => [],
        ];
        if ($page > $max_page) {
            return $data;
        }

        $start = (($page - 1) * $size);
        $sort_order_method = 0;
        // 优化大数据量翻页
        if ($start > 1000 && $total > 2000 && $start > $total / 2) {
            $order = $sort_order_method == 0 ? 0 : 1;
            $newstart = $total - $start - $size;
            if ($newstart < 0) {
                $size += $newstart;
                $newstart = 0;
            }
            if ($order == 0) {
                $items = $this->ssdb->zrange($zname, $newstart, $size);
            } else {
                $items = $this->ssdb->zrrange($zname, $newstart, $size);
            }
            $items = array_reverse($items, TRUE);
        } else {
            $order = $sort_order_method == 0 ? 1 : 0;
            if ($order == 0) {
                $items = $this->ssdb->zrange($zname, $start, $size);
            } else {
                $items = $this->ssdb->zrrange($zname, $start, $size);
            }
        }

        $list = [];

        if ($items) {
            foreach ($items as $name => $size) {
                $list[] = [
                    'name' => $name,
                    'total' => $size,
                ];
            }
            $data['list'] = $list;
        }
        return $data;
    }

    /**
     * 查看队列数量
     * @param type $queue_name
     * @return int
     */
    public function size($queue_name = 'task_queue') {
        $zname = "delay_queue_{$queue_name}";
        $total = $this->ssdb->zsize($zname);
        if (empty($total)) {
            return 0;
        }
        return $total;
    }

}
