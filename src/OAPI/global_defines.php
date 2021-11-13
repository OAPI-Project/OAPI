<?php
/**
 * 全局常量
 * 
 * @Author: ohmyga
 * @Date: 2021-10-21 22:30:57
 * @LastEditTime: 2021-10-21 22:46:41
 */

define('__OAPI_DEBUG__', true);

define('__OAPI_PLUGIN_NAMESPACE__', "OAPIPlugin");
define('__OAPI_DATA_DIR__', __OAPI_ROOT_DIR__ . "/data");
define('__OAPI_CONFIG_DIR__', __OAPI_DATA_DIR__ . "/config");
define('__OAPI_LOG_DIR__', __OAPI_DATA_DIR__ . '/logs');

define('__OAPI_BCRYPT_COUNT__', 20);

define('__OAPI_VERSION__', json_decode(file_get_contents(__OAPI_ROOT_DIR__ . '/composer.json'), true)['version'] ?? 'unknown');
