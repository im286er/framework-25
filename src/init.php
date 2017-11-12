<?php

/**
 * 框架入口
 */
/* 取得当前网站所在的根目录 */
defined('ROOT_PATH') or define('ROOT_PATH', str_replace('framework', '', __DIR__));

/* 基本方法 */
require( __DIR__ . '/function.php' );
/* 核心框架 */
require( __DIR__ . '/Config.class.php' );
require( __DIR__ . '/Log.class.php' );
require (__DIR__ . '/Loader.class.php');
require( __DIR__ . '/App.class.php' );
require( __DIR__ . '/Dispatcher.class.php' );
require( __DIR__ . '/Route.class.php' );

/* 其他方法 */
require(__DIR__ . '/helpers/lib_common.php');

/* 引入站点配置文件 */
Config::load(ROOT_PATH . 'data/config.php');

/* 引入站点语言包 */
