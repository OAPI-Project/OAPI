<?php
/**
 * 日志管理模块
 * 
 * @Author: ohmyga
 * @Date: 2021-10-22 10:54:26
 * @LastEditTime: 2021-11-14 00:58:37
 */

namespace OAPI\LogCat;

use function file_put_contents;

class LogCat
{
    /** 错误日志目录 */
    public static $error_log_dir = __OAPI_LOG_DIR__ . "/error";

    /** 普通日志目录 */
    public static $normal_log_dir = __OAPI_LOG_DIR__ . "/normal";

    /** 运行日志目录 */
    public static $run_log_file = __OAPI_LOG_DIR__ . "/run.log";

    /**
     * 初始化
     */
    public static function init()
    {
        // 创建目录
        if (!is_dir(__OAPI_LOG_DIR__)) mkdir(__OAPI_LOG_DIR__, 0777, true);
        if (!is_dir(self::$error_log_dir)) mkdir(self::$error_log_dir, 0777, true);
        if (!is_dir(self::$normal_log_dir)) mkdir(self::$normal_log_dir, 0777, true);

        if (file_exists(self::$run_log_file)) file_put_contents(self::$run_log_file, "");
    }

    /**
     * 写入错误日志
     * 
     * @param string $level        错误等级
     * @param string $type         错误类型
     * @param string $message      错误内容
     * @param string $file         错误所在文件
     * @param int $line            错误所在行数
     */
    public static function error($level, $type, $message, $file, $line)
    {
        $enum = 0;
        $eline = "";
        while ($enum < 65) {
            $enum++;
            $eline .= "=";
        }
        $log = "+" . $eline . PHP_EOL;

        $log .= "| Level: " . $level . PHP_EOL;
        $log .= "| Type: " . $type . PHP_EOL;
        $log .= "| Line: " . $line . PHP_EOL;
        $log .= "| File: " . $file . PHP_EOL;
        $log .= "| Message: " . $message . PHP_EOL;
        $log .= "| Time: " . date("Y-m-d H:i:s") . PHP_EOL;

        $log .= "+" . $eline . PHP_EOL . PHP_EOL;

        $file = self::$error_log_dir . "/" . date("Y-m-d") . ".log";

        self::saveLog($file, $log);
    }

    /**
     * 写入普通日志
     * 
     * @param mixed $content    日志内容
     */
    public static function normal($content)
    {
        $file = self::$normal_log_dir . "/" . date("Y-m-d") . ".log";
        self::saveLog($file, $content);
    }

    /**
     * 写入运行日志 (每次启动将会覆盖)
     * 
     * @param mixed $content    日志内容
     */
    public static function run($content)
    {
        self::saveLog(self::$run_log_file, $content);
    }

    /**
     * 日志写入
     * 
     * @param string $file     日志文件路径
     * @param string $body     日志内容
     */
    public static function saveLog($file, $body)
    {
        if (file_exists($file)) {
            file_put_contents($file, $body, FILE_APPEND);
        } else {
            file_put_contents($file, $body);
        }
    }
}
