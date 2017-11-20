<?php

/**
 * 框架启动
 */
require( VENDOR_PATH . '/autoload.php' );

/* 应用类库命名空间 */
\framework\core\Loader::addNamespace(
        [
            'api' => APP_PATH . 'api/',
            'common' => APP_PATH . 'common/',
            'admin' => APP_PATH . 'admin/',
            'www' => APP_PATH . 'www/',
        ]
);

/* 设置 session 配置 */
if (\framework\core\Config::get('memcached_cache')) {
    ini_set("session.save_handler", "memcache");
    ini_set("session.gc_maxlifetime", "28800"); // 8 小时
    foreach (Config::get('memcached_cache') as $key => $conf) {
        ini_set("session.save_path", "tcp://{$conf['host']}:{$conf['port']}");
    }
    unset($conf);
}

// 启动程序
\framework\core\App::run();
