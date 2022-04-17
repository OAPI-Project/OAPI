<?php
/**
 * @Author: ohmyga
 * @Date: 2021-10-22 11:03:01
 * @LastEditTime: 2022-04-17 15:52:00
 */

namespace OAPI\Console;

use OAPI\LogCat\LogCat;

class Console {
    /**
     * 默认颜色配置
     * 
     * @access private
     */
    private static $_default_use_color = [
        "error"     =>  "red",
        "warning"   =>  "yellow",
        "info"      =>  "lightblue",
        "success"   =>  "green",
        "debug"     =>  "gray"
    ];

    /**
     * 用户自定义主题配置
     * 
     * @access private
     */
    private static $_user_theme_config = [];

    /**
     * 颜色值列表
     * 
     * @access private
     */
    private static $_color_list = [];
    
    /**
     * 颜色配置
     * 
     * @access private
     */
    private static $_color = [];

    /**
     * 初始化控制台
     */
    public static function init($level, $theme = "default", array $theme_config = [])
    {
        self::$_user_theme_config = $theme_config;
        Theme::init(self::$_default_use_color, self::$_user_theme_config);
        self::$_color = Theme::get($theme);
        self::$_color_list = Theme::getColorList();
    }

    /**
     * 设置字体颜色
     * 
     * @param string $text
     * @param string $color
     * @return string
     */
    public static function color($text, $color = "")
    {
        $color = count((array)self::$_color_list) > 0 && !empty(self::$_color_list[$color]) ? self::$_color_list[$color] : TermColor::color8(37);

        return $color . $text . TermColor::RESET;
    }

    /**
     * 替换字体颜色模板字符串
     * 
     * @param string $text
     * @return mixed
     */
    public static function strRpColor($text)
    {
        if (!preg_match('/(?!{r}){.*?}/i', $text)) return $text;

        foreach (self::$_color_list as $key => $item) {
            $text = str_replace('{' . $key . '}', $item, $text);
        }

        $text = str_replace("{r}", TermColor::RESET, $text);

        return $text;
    }

    /**
     * 抛出一条错误信息
     * 
     * @param string $message   错误信息
     * @param string $module    触发模块
     * @return mixed
     */
    public static function error($message, $module = null)
    {
        echo self::__logFunc("E", ($module !== null) ? $module : null, $message, "error", true);
    }

    /**
     * 输出一条警告信息
     * 
     * @param string $message   警告信息
     * @param string $module    触发模块
     * @return mixed
     */
    public static function warning($message, $module = null)
    {
        echo self::__logFunc("W", ($module !== null) ? $module : null, $message, "warning", true);
    }

    /**
     * 输出普通信息
     * 
     * @param string $message   信息
     * @param string $module    触发模块
     * @return mixed
     */
    public static function info($message, $module = null)
    {
        echo self::__logFunc("I", ($module !== null) ? $module : null, $message, "info", true);
    }

    /**
     * 输出一条操作成功的信息
     * 
     * @param string $message   成功信息
     * @param string $module    触发模块
     * @return mixed
     */
    public static function success($message, $module = null)
    {
        echo self::__logFunc("S", ($module !== null) ? $module : null, $message, "success", true);
    }

    /**
     * 输出一条 DEBUG INFO
     * 
     * @param string $message   DEBUG
     * @param string $module    触发模块
     * @return mixed
     */
    public static function debug($message, $module = null)
    {
        if (__OAPI_DEBUG__ === false) return false;

        echo self::__logFunc("D", ($module !== null) ? $module : null, $message, "debug", true);
    }

    /**
     * 指定类型日志输出函数
     */
    private static function __logFunc($head, $module = null, $message = "", $color = "", $saveLog = true) {
        $text = self::getHead($head);
        $text .= ($module !== null) ? "[{$module}] " : " ";
        $text .= $message;

        if ($saveLog === true) LogCat::run($text . PHP_EOL);

        $text = self::color($text, Theme::get()[$color]);
        $text = self::strRpColor($text);
        $text .= PHP_EOL;

        return $text;
    }

    /**
     * 输出一条自定义消息
     * 
     * @param mixed $body               输出内容
     * @param bool $time                是否包含时间
     * @param string|null $module       是否包含模块名称
     * @param bool $eol                 输出当前自定义消息吼是否换行
     * @param bool $ret                 直接输出或是返回
     * @param bool $saveLog             是否将控制台输出保存在临时日志文件中
     * @return void
     */
    public static function diy($body, $time = false, $module = null, $eol = false, $ret = false, $saveLog = true)
    {
        $text = ($time === true) ? date("[Y-m-d H:i:s]") : "";
        $text .= ($module !== null) ? "[{$module}] " : " ";
        $text .= $body;
        $text .= ($eol === true) ? PHP_EOL : "";

        if ($saveLog === true) LogCat::run($text . ($eol === true) ? "" : PHP_EOL);

        $text = self::strRpColor($text);

        if ($ret === true) return $text;
        else echo $text;
    }

    /**
     * 获取控制台的头 (?)
     * 
     * @param string $head
     * @return mixed
     */
    private static function getHead($head)
    {
        $text = date("[Y-m-d H:i:s]") . "[{$head}]";

        return $text;
    }

    /**
     * 获取颜色值列表
     * 
     * @return array
     */
    public static function getColorList(): array
    {
        if (count((array)self::$_color_list) > 0) return self::$_color_list;
        return [];
    }
}
