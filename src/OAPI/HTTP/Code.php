<?php
/**
 * @Author: ohmyga
 * @Date: 2021-10-22 12:02:04
 * @LastEditTime: 2021-10-22 12:02:13
 */

namespace OAPI\HTTP;

use OAPI\Config\Config;

class Code
{
    /**
     * 预置 HTTP 状态码
     */
    private static $_default = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported'
    ];

    /**
     * 从配置文件读取的 HTTP 状态码列表
     */
    private static $_file_get = [];

    /**
     * 初始化
     */
    public function __construct()
    {
        $file = Config::getPath() . "/http-code.json";
        if (file_exists($file)) {
            self::$_file_get = Config::get("http-code");
        }
    }

    /**
     * 获取状态码对应内容
     */
    public static function get($code): string
    {
        $code = (int)$code;

        if (!empty(self::$_file_get[$code])) return self::$_file_get[$code];
        return self::$_default[$code];
    }
}
