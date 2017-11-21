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

\framework\core\Config::load(APP_PATH . 'common/data/config.php');

// 启动程序
\framework\core\App::run();
