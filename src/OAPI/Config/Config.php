<?php
/**
 * 配置加载/获取模块
 * 
 * @Author: ohmyga
 * @Date: 2021-10-22 10:44:50
 * @LastEditTime: 2021-11-14 01:13:21
 */

namespace OAPI\Config;

use Symfony\Component\Yaml\Yaml;
use function is_array, in_array;
use function file_exists;
use function file_get_contents;

class Config
{
    /**
     * 配置文件目录
     * 
     * @access private
     */
    private static $_path = ".";

    /**
     * 支持的配置后缀名
     * 
     * @access private
     */
    private static $_config_ext_name = [
        'php',
        'yml',
        'yaml',
        'json'
    ];

    /**
     * 暂存的配置文件
     * 
     * @access private
     */
    private static $_config = [];

    /**
     * 前一次发生的错误日志
     * 
     * @access public
     */
    public static $_last_error = "";

    /**
     * 设置配置文件目录
     * 
     * @param string $path    目录
     * @return string         配置文件所在目录
     */
    public static function setPath($path)
    {
        return self::$_path = $path;
    }

    /**
     * 获取配置文件目录
     * 
     * @return string   配置文件目录
     */
    public static function getPath()
    {
        return self::$_path;
    }

    /**
     * 清空配置文件缓存
     * 
     * @return void
     */
    public static function clear()
    {
        self::$_config = [];
    }

    /**
     * 修改暂存配置文件中的值
     * 
     * @param string $name    配置文件名称
     * @param string $key     配置的键
     * @param string $value   修改之后的值
     * @return bool           是否修改成功
     */
    public static function modify($name, $key, $value): bool
    {
        if (!isset(self::$_config[$name])) return false;

        self::$_config[$name][$key] = $value;
        return true;
    }

    /**
     * 获取配置文件
     * 
     * @param string $name    配置文件的名称
     * @param string $key     配置文件的键
     */
    public static function get($name, $key = null)
    {
        $data = (isset(self::$_config[$name])) ? self::$_config[$name] : self::__loadConfig($name);

        if ($data === false) return false;

        return ($key !== null) ? $data[$key] ?? null : $data;
    }

    /**
     * 加载配置文件
     * 
     * @param string $name   配置文件的名称（文件名）
     * @return void
     */
    private static function __loadConfig($name)
    {
        $extName = self::$_config_ext_name;

        foreach ($extName as $key => $ext) {
            $file = self::$_path . "/" . $name . "." . $ext;

            if (file_exists($file)) {
                return self::__getConfig($name, $file, $ext, true);
            } elseif ($key === (count($extName) - 1)) {
                $extStringList = "";
                foreach ($extName as $k => $e) {
                    $extStringList .= ($k === (count($extName) - 1)) ? $e : $e . ", ";
                }
                self::$_last_error = "未找到名为 " . $name . " 的配置文件，请检查配置格式(后缀)是否为[{$extStringList}]";
            }
        }

        return false;
    }

    /**
     * 读取并载入配置文件
     * 
     * @param string $name    配置文件名称
     * @param string $file    配置文件所在位置
     * @param string $ext     配置文件格式
     */
    public static function __getConfig($name, $file, $ext, $selfuse = false)
    {
        if (in_array($ext, self::$_config_ext_name) && ($ext == "yaml" || $ext == "yml")) {
            $data = Yaml::parseFile($file);
            if (is_array($data)) {
                if ($selfuse === true) return self::$_config[$name] = $data;
                if ($selfuse === false) return $data;
            } else {
                if ($selfuse === true) self::$_last_error = "Yaml 配置文件反序列化出错，请查看控制台的错误提示或查阅错误日志";
                return false;
            }
        }

        if (in_array($ext, self::$_config_ext_name) && $ext == "php") {
            $data = include_once $file;

            if (is_array($data)) {
                if ($selfuse === true) return self::$_config[$name] = $data;
                if ($selfuse === false) return $data;
            } else {
                if ($selfuse === true) self::$_last_error = "PHP 配置文件 include 失败，请查看控制台错误信息或查阅错误日志";
                return false;
            }
        }

        if (in_array($ext, self::$_config_ext_name) && $ext == "json") {
            $data = json_decode(file_get_contents($file), true);

            if (is_array($data)) {
                if ($selfuse === true) return self::$_config[$name] = $data;
                if ($selfuse === false) return $data;
            } else {
                if ($selfuse === true) self::$_last_error = "JSON 配置文件反序列化失败，请查看控制台错误信息或查阅错误日志";
                return false;
            }
        }

        return false;
    }
}
