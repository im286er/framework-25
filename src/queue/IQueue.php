<?php

namespace framework\queue;

interface IQueue {

    public function qpush($queueName, $job, $delay);

    public function qpop($queueName, $size);

    public function size($queueName);
}
