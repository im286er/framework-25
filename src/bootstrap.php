<?php

/**
 * 框架启动
 * bootstrap.php 样例
 */
/* 基本方法 */
require( FRAMEWORK_PATH . '/function.php' );
/* 核心框架 */
require( FRAMEWORK_PATH . '/Config.class.php' );
require( FRAMEWORK_PATH . '/Log.class.php' );
require (FRAMEWORK_PATH . '/Loader.class.php');
require( FRAMEWORK_PATH . '/App.class.php' );
require( FRAMEWORK_PATH . '/Dispatcher.class.php' );
require( FRAMEWORK_PATH . '/Route.class.php' );

/* 加载公共方法 */
require_cache(APP_PATH . 'common/function.php');

/* 引入站点配置文件 */
Config::load(ROOT_PATH . 'data/config.php');

/* 应用类库命名空间 */
Loader::addNamespace(
        [
            'api' => APP_PATH . 'api/',
            'common' => APP_PATH . 'common/',
            'admin' => APP_PATH . 'admin/',
        ]
);

/* 设置 session 配置 */
if (Config::get('memcached_cache')) {
    ini_set("session.save_handler", "memcache");
    ini_set("session.gc_maxlifetime", "28800"); // 8 小时
    foreach (Config::get('memcached_cache') as $key => $conf) {
        ini_set("session.save_path", "tcp://{$conf['host']}:{$conf['port']}");
    }
    unset($conf);
}