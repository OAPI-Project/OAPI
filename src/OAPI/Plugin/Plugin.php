<?php
/**
 * @Author: ohmyga
 * @Date: 2021-10-22 12:10:47
 * @LastEditTime: 2021-12-05 18:24:30
 */

namespace OAPI\Plugin;

use OAPI\DB\DB;
use OAPI\Libs\Libs;
use OAPI\Console\Console;
use OAPI\LogCat\LogCat;
use OAPI\HTTP\Router;
use ReflectionClass, ReflectionMethod;
use function file_get_contents;

class Plugin
{
    /**
     * 数据库实例
     */
    private static $_db;

    /**
     * 已启用插件
     */
    private static $_enabled = [];

    /**
     * 异常插件
     */
    private static $_error_plugins = [];

    /**
     * 所有插件
     */
    private static $_plugins = [];

    /**
     * 未经处理的插件文件列表
     */
    private static $_raw_plugins_list = [];

    /**
     * 未经处理的插件列表
     */
    private static $_raw_plugins = [];

    /**
     * 已实例化的插件
     */
    private static $_instance_plugins = [];

    /**
     * 初始化
     */
    public function __construct()
    {
        self::$_raw_plugins_list = $this->__getPluginList();

        if (count(self::$_raw_plugins_list) > 0) {
            foreach (self::$_raw_plugins_list as $list) {
                if (file_exists($list . "/Plugin.php")) {
                    $info = Libs::parseInfo(file_get_contents($list . "/Plugin.php"), true);
                    self::$_raw_plugins[$info['package']] = [
                        "name"          => $info['name'],
                        "package"       => $info['package'],
                        "author"        => $info['author'],
                        "version"       => $info['version'],
                        "file"          => $list . "/Plugin.php",
                        "description"   => $info['description'],
                    ];
                }
            }
        }

        /** 从数据库获取插件启用信息 */
        $db = DB::get();
        self::$_db = $db;
        $result = $db->fetchAll($db->select()->from("table.plugins"));

        if (!empty($result)) {
            foreach ($result as $plugin) {
                if (!empty(self::$_raw_plugins[$plugin["package"]])) {
                    $_pl_raw = self::$_raw_plugins[$plugin["package"]];
                    $_pl = [
                        "name"         => $plugin["name"],
                        "package"      => $plugin["package"],
                        "author"       => $plugin["author"],
                        "status"       => $plugin["status"],
                        "version"      => $_pl_raw["version"],
                        "file"         => $_pl_raw["file"],
                        "description"  => $_pl_raw["description"],
                    ];

                    if ($plugin["status"] == "enable") {
                        self::$_enabled[$plugin["package"]] = $_pl;
                    }

                    self::$_plugins[$plugin["package"]] = $_pl;

                    /** 检查是否需要更新插件信息 */
                    if ($_pl_raw["name"] != $plugin["name"]) {
                        $db->query($db->update("table.plugins")->rows(["name" => $_pl_raw["name"]])->where("package = ?", $plugin["package"]));
                    }
                    if ($_pl_raw["version"] != $plugin["version"]) {
                        $db->query($db->update("table.plugins")->rows(["version" => $_pl_raw["version"]])->where("package = ?", $plugin["package"]));
                    }
                } else {
                    // 异常插件
                    self::$_error_plugins[$plugin["package"]] = [
                        "name"    => $plugin["name"],
                        "package" => $plugin["package"],
                        "author"  => $plugin["author"],
                        "status"  => $plugin["status"],
                        "error"   => "插件不存在"
                    ];
                }
            }
        }

        foreach (self::$_raw_plugins as $rawp) {
            if (empty(self::$_plugins[$rawp["package"]])) {
                self::$_plugins[$rawp["package"]] = [
                    "name"         => $rawp["name"],
                    "package"      => $rawp["package"],
                    "author"       => $rawp["author"],
                    "status"       => "disable",
                    "version"      => $rawp["version"],
                    "file"         => $rawp["file"],
                    "description"  => $rawp["description"],
                ];

                /** 排除异常插件 */
                unset(self::$_error_plugins[$rawp["package"]]);
                self::$_error_plugins = array_merge(self::$_error_plugins);
            }
        }
    }

    /**
     * 插件初始化
     * 
     * @return void
     */
    public function init()
    {
        foreach (self::$_enabled as $enable) {
            $epl = self::__loadPlugin($enable);
            if ($epl["status"] === false) throw new \Exception($epl["message"]);
            self::$_instance_plugins[$enable["package"]] = $epl["instance"];
        }

        Console::info("插件初始化完成", "Plugin");
        Console::info("已启用插件: " . count(self::$_enabled) . " | 已禁用插件: " . (count(self::$_plugins) - count(self::$_enabled)), "Plugin");
        if (count(self::$_error_plugins) > 0) {
            Console::warning("发现异常插件 " . count(self::$_error_plugins) . " 个", "Plugin");
            if (__OAPI_DEBUG__ === true) {
                $errp = "";
                $errl = "";
                for ($i = 0; $i < count(self::$_error_plugins); $i++) {
                    $epl = self::$_error_plugins;
                    $errp .= ($i == (count($epl) - 1)) ? $epl[key($epl)]["package"] : $epl[key($epl)]["package"] . ", ";
                    $errl .= "[{$i}] 名称: {$epl[key($epl)]["name"]} | 包名: {$epl[key($epl)]["package"]} | 原始数据(JSONEncode): " . json_encode($epl[key($epl)]);
                    $errl .= ($i == (count($epl) - 1)) ? "" : PHP_EOL;
                    next(self::$_error_plugins);
                }
                Console::debug("异常插件包名列表: [{$errp}]", "Plugin");
                Console::debug("查阅日志以获取更为详细的列表", "Plugin");
                LogCat::error("WARNING", "Normal", $errl, __FILE__, __LINE__);
            }
        }

        foreach (self::$_instance_plugins as $epl) $epl::run();
    }

    /**
     * 启用插件
     * 
     * @param string $package     插件包名
     * @param bool $rph           是否返回插件实例
     * @param bool $console       是否在控制台输出日志
     * @return array
     */
    public static function enable($package, $rph = false, $console = true): array
    {
        if (empty(self::$_plugins[$package])) {
            if ($console === true) Console::warning("插件 [" . $package . "] 不存在，启用失败", "Plugin");
            return ["status" => "error", "message" => "插件不存在，启用失败"];
        }

        if (!empty(self::$_enabled[$package])) {
            if ($console === true) Console::warning("插件 [" . $package . "] 已经启用，无需重复启用", "Plugin");
            return ["status" => "enabled", "instance" => $rph === true ? self::$_instance_plugins[$package] : null, "message" => "插件已经启用，无需重复启用"];
        }

        $plugin = self::$_plugins[$package];
        $has = (self::$_db->fetchRow(self::$_db->select()->from('table.plugins')->where('package = ?', $package))) ? true : false;

        if ($has === true) {
            self::$_db->query(
                self::$_db->update("table.plugins")->rows([
                    "status" => "enable"
                ])->where("package = ?", $plugin["package"])
            );
        } else {
            self::$_db->query(
                self::$_db->insert("table.plugins")->rows([
                    "package"  => $package,
                    "name"     => $plugin["name"],
                    "author"   => $plugin["author"],
                    "version"  => $plugin["version"],
                    "status"   => "enable",
                ])
            );
        }

        $plugin["status"] = "enable";
        self::$_plugins[$package] = $plugin;
        self::$_enabled[$package] = self::$_plugins[$package];

        $instance = self::__loadPlugin($plugin);
        if ($instance["status"] === false) throw new \Exception($instance["message"]);
        self::$_instance_plugins[$package] = $instance["instance"];
        if ($console === true) Console::success("插件 [" . $package . "] 启用成功", "Plugin");

        $instance["instance"]::enable();
        $instance["instance"]::run();

        return ["status" => "enable", "instance" => $rph === true ? self::$_instance_plugins[$package] : null, "message" => "插件启用成功"];
    }

    /**
     * 禁用插件
     * 
     * @param string $package     插件包名
     * @param bool $console       是否在控制台输出日志
     * @return array
     */
    public static function disable($package, $console = true): array
    {
        if (empty(self::$_plugins[$package])) {
            if ($console === true) Console::warning("插件 [" . $package . "] 不存在，禁用失败", "Plugin");
            return ["status" => "error", "message" => "插件不存在，禁用失败"];
        }

        if (empty(self::$_enabled[$package])) {
            if ($console === true) Console::warning("插件 [" . $package . "] 已经禁用，无需重复禁用", "Plugin");
            return ["status" => "disabled", "message" => "插件已经禁用，无需重复禁用"];
        }

        $plugin = self::$_enabled[$package];

        self::$_db->query(
            self::$_db->update("table.plugins")->rows([
                "status" => "disable"
            ])->where("package = ?", $plugin["package"])
        );

        self::removePluginRouter($plugin["package"]);
        self::$_instance_plugins[$plugin["package"]]::disable();

        unset(self::$_instance_plugins[$plugin["package"]]);
        self::$_instance_plugins = array_merge(self::$_instance_plugins);

        unset(self::$_enabled[$package]);
        self::$_enabled = array_merge(self::$_enabled);

        $pl = self::$_plugins[$package];
        $pl["status"] = "disable";
        self::$_plugins[$package] = $pl;

        if ($console === true) Console::success("插件 [" . $package . "] 禁用成功", "Plugin");
        return ["status" => "disable", "message" => "插件禁用成功"];
    }

    /**
     * 获取插件是否存在
     * (无论是否启用)
     * 
     * @param string $package     插件包名
     * @param bool $console       是否在控制台输出日志
     * @return bool
     */
    public static function has($package, $console = false): bool
    {
        $_has = false;
        foreach (self::$_plugins as $plugin) {
            if ($plugin["package"] == $package) {
                $_has = true;
                continue;
            }
        }

        if ($console === true) Console::warning("插件 [" . $package . "] 不存在", "Plugin");
        return $_has;
    }

    /**
     * 获取插件信息
     * (无论是否启用)
     * 
     * @param string $package     插件包名
     * @return array
     */
    public static function getInfo($package): array {
        $_plugin = [];
        foreach (self::$_plugins as $plugin) {
            if ($plugin["package"] == $package) {
                $_plugin = $plugin;
                break;
            }
        }

        return !empty($_plugin) ? $_plugin : [];
    }

    /**
     * 获取所有插件的列表
     * 
     * @return array
     */
    public static function getAllPlugins(): array
    {
        return is_array(self::$_plugins) && count(self::$_plugins) > 0 ? self::$_plugins : [];
    }

    /**
     * 读取插件目录下的所有插件
     * 
     * @return array
     */
    private function __getPluginList(): ?array
    {
        return glob(__OAPI_ROOT_DIR__ . "/src/Plugins/*");
    }

    /**
     * 加载插件
     */
    private static function __loadPlugin(array $plugin)
    {
        if (!file_exists($plugin["file"])) return ["status" => false, "message" => "插件源文件不存在，无法正确加载"];

        require_once $plugin["file"];

        $pl = "\\OAPIPlugin\\" . $plugin["package"] . "\\Plugin";
        return ["status" => true, "instance" => (new $pl())];
    }

    /**
     * 为后缀为 _Action 的函数启用根据注解注册路由
     * 
     * @param string $class
     * @param string $name
     */
    public static function actionRegisterRouter($class, $name = "path")
    {
        $routes = [];

        $ReflectionClass = new ReflectionClass(!empty($class) ? $class : __CLASS__);

        foreach ($ReflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            preg_match('/(.*)_Action$/i', $method->getName(), $matches);
            preg_match('/(.*)_Admin_Action$/i', $method->getName(), $admin_matches);

            if (!empty($matches[1]) && empty($admin_matches[1])) {
                $parseInfo = Libs::parseInfo($method->getDocComment());
                $routes[] = [
                    'action'         => $matches[0],
                    'name'           => 'OAPI_' . $matches[1],
                    'url'            => (!empty($parseInfo[$name])) ?  $parseInfo[$name] : $matches[1],
                    'version'        => (!empty($parseInfo["version"])) ? $parseInfo["version"] : 1,
                    'disableVersion' => (isset($parseInfo["disableVersion"])) ? $parseInfo["disableVersion"] : false,
                    'description'    => $parseInfo['description']
                ];
            }
        }

        foreach ($routes as $key => $route) {
            Router::add([
                "name"            => $route["name"],
                "url"             => $route["url"],
                "version"         => $route["version"],
                "disableVersion"  => $route["disableVersion"],
                "widget"          => $class . "::" . $route["action"],
            ]);
        }
    }

    /**
     * 删除插件注册路由
     * 
     * @param string      插件包名
     */
    public static function removePluginRouter($package)
    {
        $routes = Router::getRoutes();

        if (empty($routes)) return false;

        foreach ($routes as $key => $route) {
            $widget = str_replace("\\", "/", $route["widget"]);
            if (preg_match("/\/$package\/Plugin::.*/is", $widget)) {
                Router::remove($key);
            }
        }

        Router::values();
    }

    /**
     * 获取插件配置储存目录
     * 
     * @return mixed
     */
    public static function getDataDir()
    {
        $req = debug_backtrace();
        if (empty($req[0])) return false;

        $file = Libs::parseInfo(file_get_contents($req[0]["file"]));
        $dataRootDir = defined(__OAPI_DATA_DIR__) ? __OAPI_DATA_DIR__ : __OAPI_ROOT_DIR__ . "/data";
        $dataAppDir = $dataRootDir . "/apps";
        $pluginAppDir = $dataAppDir . "/" . $file["package"];
        
        if (!is_dir($pluginAppDir)) mkdir($pluginAppDir, 0777, true);

        return $pluginAppDir;
    }
}
