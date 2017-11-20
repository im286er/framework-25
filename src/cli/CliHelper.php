<?php

namespace framework\cli;

/**
 * 命令行助手类
 */
class CliHelper {

    /**
     * 打印一行
     * @param $msg
     */
    public static function print_line($msg) {
        echo ("{$msg} \n");
    }

    /**
     * 终端高亮打印绿色
     * @param $message
     */
    public static function print_ok($message) {
        printf("\033[32m\033[1m{$message}\033[0m\n");
    }

    /**
     * 终端高亮打印红色
     * @param $message
     */
    public static function print_error($message) {
        printf("\033[31m\033[1m{$message}\033[0m\n");
    }

    /**
     * 终端高亮打印黄色
     * @param $message
     */
    public static function print_warning($message) {
        printf("\033[33m\033[1m{$message}\033[0m\n");
    }

}
