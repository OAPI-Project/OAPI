<?php
/**
 * @Author: ohmyga
 * @Date: 2021-11-17 18:18:03
 * @LastEditTime: 2021-11-29 03:06:35
 */

namespace OAPIPlugin\Admin;

use OAPI\DB\DB;
use OAPI\Libs\Libs;

class Menu
{
    private static $_db;

    public function __construct()
    {
        self::$_db = DB::get();
    }

    /**
     * 菜单配置初始化
     * 
     * @return void
     */
    private static function init()
    {
        if (!self::$_db->fetchRow(self::$_db->select()->from("table.admin_options")->where("name = ?", "menuconfig"))) {
            self::$_db->query(
                self::$_db->insert("table.admin_options")->rows([
                    "name"    =>  "menuconfig",
                    "value"   =>  "[]",
                ])
            );
        }
    }

    /**
     * 获取后台配置侧栏列表
     * 
     * @return array
     */
    public static function config(): array
    {
        self::init();
        $menu = self::$_db->fetchRow(
            self::$_db
                ->select()
                ->from("table.admin_options")
                ->where("name = ?", "menuconfig")
        );

        return !empty($menu["value"]) && is_array(json_decode($menu["value"], true)) ? json_decode($menu["value"], true) : [];
    }

    /**
     * 注册菜单配置
     * 
     * @return mixed
     */
    public static function setConfig($class_name)
    {
        if (!call_user_func([$class_name, 'menuconfig'])) {
            return [
                "status"     =>  false,
                "message"    =>  "没有找到菜单注册类"
            ];
        }

        $menu = call_user_func([$class_name, 'menuconfig']);
        if (empty($menu)) {
            return [
                "status"     =>  false,
                "message"    =>  "没有任何需要注册的菜单"
            ];
        }

        $_menu = [];
        $key = str_replace("\\", "_", $class_name);

        $req = debug_backtrace();
        $file = Libs::parseInfo(file_get_contents($req[0]["file"]));

        if (!empty($menu["id"])) {
            $menu["id"] = $key . "_" . $menu["id"];
            $menu["package"] = $file["package"];
            $_menu = $menu;
        } else {
            foreach ($menu as $item) {
                $_menu_temp = $item;
                $_menu_temp["id"] = $key . "_" . $item["id"];
                $_menu_temp["package"] = $file["package"];
                $_menu[] = $_menu_temp;
            }
        }

        self::_addConfig($_menu);

        return [
            "status"   => true,
            "data"     => $_menu
        ];
    }

    /**
     * 取消注册菜单配置
     * 
     * @return array
     */
    public static function removeConfig($class_name)
    {
        if (!call_user_func([$class_name, 'menuconfig'])) {
            return [
                "status"     =>  false,
                "message"    =>  "没有找到菜单注册类"
            ];
        }

        $menu = call_user_func([$class_name, 'menuconfig']);
        if (empty($menu)) {
            return [
                "status"     =>  false,
                "message"    =>  "没有任何需要取消注册的菜单"
            ];
        }

        $key = str_replace("\\", "_", $class_name);
        if (!empty($menu["id"])) {
            $menu["id"] = $key . "_" . $menu["id"];
            self::_rmConfig($menu["id"]);
        } else {
            foreach ($menu as $item) {
                $_key = $key . "_" . $item["id"];
                self::_rmConfig($_key);
            }
        }

        return [
            "status"     => true,
            "data"       => []
        ];
    }

    /**
     * 增加 Menu 配置
     * 
     * @return array
     */
    private static function _addConfig(array $config)
    {
        $allconfig = self::config();

        if (!empty($allconfig)) {
            if (empty($config["id"]) && count($config) > 0) {
                foreach ($config as $item) {
                    $_has = false;
                    foreach ($allconfig as $allitem) {
                        if ($item["id"] == $allitem["id"]) {
                            $_has = true;
                            break;
                        }
                    }

                    if ($_has === false) {
                        $allconfig[] = $item;
                    }
                }
            } else {
                $_has = false;
                foreach ($allconfig as $allitem) {
                    if ($allitem["id"] == $config["id"]) {
                        $_has = true;
                        break;
                    }
                }

                if ($_has === false) {
                    $allconfig[] = $config;
                }
            }
        } else {
            $allconfig[] = $config;
        }

        self::$_db->query(
            self::$_db->update("table.admin_options")->rows([
                "value"  => json_encode($allconfig)
            ])->where("name = ?", "menuconfig")
        );

        return $allconfig;
    }

    /**
     * 删除 Menu 配置
     * 
     * @return array
     */
    private static function _rmConfig($id)
    {
        $allconfig = self::config();
        if (empty($allconfig)) return [];

        foreach ($allconfig as $key => $item) {
            if ($item["id"] == $id) {
                unset($allconfig[$key]);
                break;
            }
        }

        $allconfig = array_values($allconfig);

        self::$_db->query(
            self::$_db->update("table.admin_options")->rows([
                "value"  => json_encode($allconfig)
            ])->where("name = ?", "menuconfig")
        );

        return $allconfig;
    }
}
