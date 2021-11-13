<?php
/**
 * @Author: ohmyga
 * @Date: 2021-10-05 15:36:51
 * @LastEditTime: 2021-10-22 11:04:37
 */

namespace OAPI\Console;

class Theme
{
    private static $_default_color = [];

    private static $_user_color_config = [];

    private static $_color_list = [];

    /**
     * 初始化
     * 
     * @param array $default_color       默认颜色
     * @param array $user_color_config   用户自定义颜色
     */
    public static function init($default_color, $user_color_config)
    {
        self::$_default_color = $default_color;
        self::$_user_color_config = $user_color_config;
        self::initColor();
    }

    /**
     * 初始化颜色值列表
     * 
     */
    private static function initColor()
    {
        self::$_color_list = [
            "red"              =>  TermColor::color8(31),
            "green"            =>  TermColor::color8(32),
            "blue"             =>  TermColor::color8(34),
            "pink"             =>  TermColor::frontColor256(207),
            "yellow"           =>  TermColor::color8(33),
            "lightpurple"      =>  TermColor::color8(35),
            "white"            =>  TermColor::color8(37),
            "black"            =>  TermColor::color8(30),
            "gold"             =>  TermColor::frontColor256(214),
            "gray"             =>  TermColor::frontColor256(59),
            "lightblue"        =>  TermColor::color8(36),
            "lightlightblue"   =>  TermColor::frontColor256(63)
        ];
    }

    /**
     * 获取颜色配置
     * 
     * @param string $theme   颜色主题
     * @return array
     */
    public static function get($theme = "default"): array
    {
        if ($theme == "default") return self::$_default_color;

        if (count((array)self::$_user_color_config) > 0 && !empty(self::$_user_color_config[$theme])) return self::$_user_color_config[$theme];

        return self::$_default_color;
    }

    /**
     * 获取颜色值列表
     * 
     * @return array
     */
    public static function getColorList()
    {
        return self::$_color_list;
    }
}
