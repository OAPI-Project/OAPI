<?php
/**
 * @Author: ohmyga
 * @Date: 2021-10-22 12:19:05
 * @LastEditTime: 2021-10-22 12:19:11
 */

namespace OAPI\Plugin;

interface PluginInterface
{
    /**
     * 插件启用方法
     * 仅在插件启用时执行一次
     * 
     * @access public
     * @static
     * @return void
     */
    public static function enable();

    /**
     * 插件禁用方法
     * 仅在插件禁用时执行一次
     * 
     * @access public
     * @static
     * @return void
     */
    public static function disable();

    /**
     * 插件每次初始化时执行的方法
     * 
     * @access public
     * @static
     * @return void
     */
    public static function run();
}
