<?php
/**
 * OAPI 启动入口
 * 
 * @Author: ohmyga
 * @Date: 2021-10-21 22:34:10
 * @LastEditTime: 2021-10-21 22:42:31
 */

if (!defined("__OAPI_ROOT_DIR__")) exit;

foreach ([
    __OAPI_ROOT_DIR__ . "/vendor/autoload.php",
    __OAPI_ROOT_DIR__ . "/autoload.php"
] as $file) {
    if (file_exists($file)) {
        define("__OAPI_COMPOSER_INSTALL__", $file);
        break;
    }
}

if (!defined("__OAPI_COMPOSER_INSTALL__")) {
    echo 'You need to set up the project dependencies using Composer:' . PHP_EOL . PHP_EOL .
        '        composer install' . PHP_EOL . PHP_EOL .
        'You can learn all about Composer on https://getcomposer.org/.' . PHP_EOL;

    exit;
} else {
    // 引入自动加载
    require_once __OAPI_COMPOSER_INSTALL__;
}

// 芜湖！启动 (๑•̀ㅂ•́)و✧
(new \OAPI\Framework())->start();
