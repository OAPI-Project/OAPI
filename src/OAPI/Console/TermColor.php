<?php
/**
 * @Author: ohmyga
 * @Date: 2021-10-05 15:37:25
 * @LastEditTime: 2021-10-22 11:04:42
 */

namespace OAPI\Console;

class TermColor
{

    const RESET = "\e[0m";      // 样式重设
    const BOLD = "\e[1m";       // 加粗
    const ITALIC = "\e[3m";     // 斜体
    const UNDERSCORE = "\e[4m"; // 下划线
    const BLINK = "\e[5m";      // 闪烁
    const HIDE = "\e[8m";       // 隐藏

    /**
     * 输出 8 位的颜色 (包括前景色和背景色)
     * 
     * @param $code
     * @return string
     */
    static function color8($code)
    {
        return "\e[{$code}m";
    }

    /**
     * 输出 256 位的前景文字颜色 (通过8位256色颜色码)
     * 
     * @param $code
     * @return string
     */
    static function frontColor256($code)
    {
        return "\e[38;5;{$code}m";
    }

    /**
     * 输出 256 位的前景文字颜色 (通过rgb)
     * 
     * @param $r
     * @param $g
     * @param $b
     * @return string
     */
    static function frontColor256rgb($r, $g, $b)
    {
        return "\e[38;2;{$r};{$g};{$b}m";
    }

    /**
     * 输出 256 位的背景文字颜色 (通过8位256色颜色码)
     * 
     * @param $code
     * @return string
     */
    static function bgColor256($code)
    {
        return "\e[48;5;{$code}m";
    }

    /**
     * 输出 256 位的前景文字颜色 (通过rgb)
     * 
     * @param $r
     * @param $g
     * @param $b
     * @return string
     */
    static function bgColor256rgb($r, $g, $b)
    {
        return "\e[48;2;{$r};{$g};{$b}m";
    }
}
