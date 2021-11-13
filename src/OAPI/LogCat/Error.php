<?php
/**
 * 错误处理模块
 * 
 * @Author: ohmyga
 * @Date: 2021-10-22 11:00:50
 * @LastEditTime: 2021-11-06 16:33:01
 */

namespace OAPI\LogCat;

use OAPI\LogCat\LogCat;
use OAPI\Console\Console;
use OAPI\Framework;

class Error
{
    public static function init()
    {
        set_error_handler("OAPI\LogCat\Error::handler", E_ALL | E_STRICT);
        set_exception_handler("OAPI\LogCat\Error::eachxception_handler");
    }

    /**
     * 错误解析器
     * 
     * @param int $error_level            错误等级
     * @param string $error_message       错误消息
     * @param string $error_file          错误所在文件
     * @param int $error_line             错误所在行号
     * @param void $error_context         错误上下文内容
     */
    public static function handler($error_level, $error_message, $error_file, $error_line, $error_context)
    {
        switch ($error_level) {
            case 2:
                $level = 'WARNING';
                break;
            case 8:
                $level = 'INFO';
                break;
            case 256:
                $level = 'ERROR';
                break;
            case 512:
                $level = 'WARNING';
                break;
            case 1024:
                $level = 'NOTICE';
                break;
            case 4096:
                $level = 'ERROR';
                break;
            case 8191:
                $level = 'ERROR';
                break;

            default:
                $level = 'UNKNOWN';
                break;
        }

        LogCat::error($level, "Error", $error_message, $error_file, $error_line);
        Console::error('PHP ' . $level . ': ' . $error_message . ' in ' . $error_file . ' on line ' . $error_line . '.');
        if ($error_level != 1024 && $error_level != 8) {
            if (!empty(Framework::$server->master_pid)) {
                posix_kill(Framework::$server->master_pid, SIGTERM);
            } else {
                exit();
            }
        }
    }

    /**
     * 致命错误处理器
     * 
     * @param $exception
     * @param bool $console     是否在控制台输出
     **/
    public static function eachxception_handler($exception, $console = true)
    {
        LogCat::error("Exception", "Exception", $exception->getMessage(), $exception->getFile(), $exception->getLine());
        if ($console === true) Console::error('PHP Exception: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ' on line ' . $exception->getLine() . '.');
        if (!empty(Framework::$server->master_pid)) {
            posix_kill(Framework::$server->master_pid, SIGTERM);
        } else {
            exit();
        }
    }
}
