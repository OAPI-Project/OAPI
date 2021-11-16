<?php
/**
 * OAPI Admin API
 * 
 * @author ohmyga
 * @package Admin
 * @name Admin API
 * @version 1.0.0
 */

namespace OAPIPlugin\Admin;

use OAPI\Plugin\PluginInterface;
use OAPI\DB\DB;
use OAPI\DB\Consts;
use OAPI\Console\Console;
use OAPI\LogCat\Error;
use OAPI\HTTP\HTTP;
use OAPI\Libs\Libs;
use OAPI\Config\Config;
use OAPI\Plugin\Exception;
use function password_hash;
use function json_decode, base64_decode;

class Plugin implements PluginInterface
{
    /**
     * 数据库实例缓存
     */
    private static $_db;

    /**
     * 激活插件方法
     * 
     * @return void
     */
    public static function enable()
    {
        $db = DB::get();
        try {
            if (!self::__checkTable($db, "admin_users")) {
                if (!file_exists(__DIR__ . "/Admin.sql")) throw new Exception("无法找到 Admin 插件的数据库初始化文件");

                $sqlfile = file_get_contents(__DIR__ . "/Admin.sql");
                $sqlfile = str_replace("OAPI_", $db->getPrefix(), $sqlfile);

                $sqlfile = str_replace("%engine%", isset($db->getEngine()["engine"]) ? $db->getEngine()["engine"] : "InnoDB", $sqlfile);
                $sqlfile = str_replace("%charset%", isset($db->getCharset()["charset"]) ? $db->getCharset()["charset"] : "utf8mb4", $sqlfile);

                $sqlfile = explode(";", $sqlfile);
                foreach ($sqlfile as $script) {
                    $script = trim($script);
                    if ($script) {
                        $db->query($script, Consts::WRITE);
                    }
                }

                Console::success("数据库初始化成功，已创建 AdminAPI 所需的表", "AdminAPI");
            } else {
                Console::info("数据库初始化成功，发现 AdminAPI 所需的表，无需重复创建", "AdminAPI");
            }
        } catch (Exception $e) {
            Console::error("数据库初始化失败，无法创建 AdminAPI 所需的表，请查阅日志", "AdminAPI");
            Error::eachxception_handler($e, (__OAPI_DEBUG__ === true) ? true : false);
        }
    }

    /**
     * 禁用插件方法
     * 
     * @return void
     */
    public static function disable()
    {
    }

    /**
     * 插件每次初始化调用的方法
     * 
     * @return void
     */
    public static function run()
    {
        self::$_db = DB::get();
        \OAPI\Plugin\Plugin::actionRegisterRouter(__CLASS__);

        if (!self::__checkTable(self::$_db, "admin_users")) throw new Exception("AdminAPI 的数据库表未初始化，请重新启用插件！");

        if (self::$_db->fetchRow(self::$_db->select("count(*)")->from("table.admin_users"))["count(*)"] <= 0) {
            // 判断是否已经生成安装密钥
            // 莫得的话生成一个存入数据库
            Console::warning("当前没有任何管理员用户/未安装初始化", "AdminAPI");
            $iac = self::$_db->fetchRow(self::$_db->select()->from("table.admin_options")->where("name = ?", "installAuthCode"));
            if (!$iac) {
                $randString = Libs::randString(64, true);
                self::$_db->query(
                    self::$_db->insert("table.admin_options")->rows([
                        "name"    =>  "installAuthCode",
                        "value"   => $randString,
                    ])
                );
                Console::warning("请访问安装程序页面后，在安装验证输入框填入 {$randString}", "AdminAPI");
            } else {
                Console::warning("请访问安装程序页面后，在安装验证输入框填入 {$iac["value"]}", "AdminAPI");
            }
        }

        new User(self::$_db);
    }

    /**
     * 获取 AdminAPI 状态
     * (用于判断是否安装/初始化)
     * 
     * @version 1
     * @path /admin/check/init
     */
    public static function checkInit_Action($request, $response, $matches)
    {
        $config = Config::get("config");
        $data = [
            "installed"     => false,
            "hasAdminUser"  => false,
            "timezone"      => $config["timezone"]
        ];

        $alluser_count = self::$_db->fetchRow(self::$_db->select("count(*)")->from("table.admin_users"));
        if (!empty($alluser_count) && $alluser_count["count(*)"] > 0) $data["hasAdminUser"] = true;
        if (self::$_db->fetchRow(self::$_db->select()->from("table.admin_options")->where("name = ?", "installed"))) $data["installed"] = true;

        if ($data["hasAdminUser"] === true && $data["installed"] === true) $data["loginStatus"] = self::checkAuth(false);

        HTTP::sendJSON(true, 200, "Success", $data);
    }

    /**
     * 初始化安装
     * 
     * @version 1
     * @path /admin/install
     */
    public static function install_Action($request, $response, $matches)
    {
        if (HTTP::lockMethod("POST") == false) return;

        if (self::$_db->fetchRow(self::$_db->select()->from("table.admin_options")->where("name = ?", "installed"))) {
            HTTP::sendJSON(true, 403, "已安装成功，无需重复安装");
            return false;
        }

        /** 安装密钥 */
        $token = HTTP::getParams("token", "");

        /** 从数据库中获取安装密钥 */
        $ica = self::$_db->fetchRow(self::$_db->select()->from("table.admin_options")->where("name = ?", "installAuthCode"));
        if (empty($ica)) {
            HTTP::sendJSON(false, 500, "服务器内部错误 (插件未被初始化)");
            return false;
        } else if (!hash_equals($token, $ica["value"])) {
            HTTP::sendJSON(false, 403, "安装密钥验证验证失败");
            return false;
        }

        /** 用户名 */
        $username = HTTP::getParams("username", "");
        if (empty($username)) {
            HTTP::sendJSON(false, 400, "用户名不可为空", ["input" => "username"]);
            return false;
        }

        /** 密码 */
        $password = HTTP::getParams("password", "");
        if (empty($password)) {
            HTTP::sendJSON(false, 400, "密码不可为空", ["input" => "password"]);
            return false;
        } else if (!preg_match("/^\S*(?=\S{6,})\S*$/", $password)) {
            HTTP::sendJSON(false, 400, "密码最少六位", ["input" => "password"]);
            return false;
        }
        /* 越想越觉得密码限制格式很不友好，所以改为只限制密码最少位数
        } else if (!preg_match("/^\S*(?=\S{8,})(?=\S*\d)(?=\S*[A-Z])(?=\S*[a-z])(?=\S*[\.!@#$%^&*?])\S*$/", $password)) {
            HTTP::sendJSON(false, 400, "密码最少八位，需包含一个大写字母，一个小写字母，一个数字，以及一个特殊符号", ["input" => "password"]);
            return false;
        }*/

        /** 邮箱 */
        $mail = HTTP::getParams("mail", "");
        if (empty($mail)) {
            HTTP::sendJSON(false, 400, "邮箱不可为空", ["input" => "email"]);
            return false;
        } else if (!preg_match('/^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/', $mail)) {
            HTTP::sendJSON(false, 400, "邮箱格式错误", ["input" => "email"]);
            return false;
        }

        // 计算密码哈希值
        $password = password_hash($password, PASSWORD_DEFAULT, ['count' => __OAPI_BCRYPT_COUNT__]);

        self::$_db->query(
            self::$_db->insert("table.admin_users")->rows([
                "username"      => $username,
                "password"      => $password,
                "mail"          => $mail,
                "created"       => time()
            ])
        );

        self::$_db->query(
            self::$_db->insert("table.admin_options")->rows([
                "name"     => "installed",
                "value"    => "true"
            ])
        );

        HTTP::sendJSON(true, 200, "配置完成", User::userNoPassLogin($username));
    }

    /**
     * 登录接口
     * 
     * @version 1
     * @path /admin/login
     */
    public static function login_Action($request, $response, $matches)
    {
        if (HTTP::lockMethod("POST") == false) return;

        /** 用户名 */
        $username = HTTP::getParams("username", "");
        if (empty($username)) {
            HTTP::sendJSON(false, 400, "用户名不可为空", ["input" => "username"]);
            return false;
        }

        /** 密码 */
        $password = HTTP::getParams("password", "");
        if (empty($password)) {
            HTTP::sendJSON(false, 400, "密码不可为空", ["input" => "password"]);
            return false;
        }

        $check_password = User::login($username, $password);

        if ($check_password["status"] === false) {
            HTTP::sendJSON(false, 403, $check_password["error"]);
            return false;
        }

        HTTP::sendJSON(true, 200, "Success", [
            "uid"      => $check_password["uid"],
            "authCode" => $check_password["authCode"]
        ]);
    }

    /**
     * 获取插件列表
     * 
     * @version 1
     * @path /admin/plugin/list
     */
    public static function pluginList_Action($request, $response, $matches)
    {
        if (HTTP::lockMethod(["GET"]) == false) return;
        if (!self::checkAuth()) return;

        $plist = \OAPI\Plugin\Plugin::getAllPlugins();

        HTTP::sendJSON(true, 200, "Success", $plist);
    }

    /**
     * 启用 / 禁用插件
     * 
     * @version 1
     * @path /admin/plugin/modify
     */
    public static function modifyPlugin_Action($request, $response, $matches)
    {
        if (HTTP::lockMethod(["GET"]) == false) return;
        if (!self::checkAuth()) return;

        $method = HTTP::getParams("method", "enable") == "enable" ? "enable" : "disable";

        $package = HTTP::getParams("package", "");
        if (empty($package)) {
            HTTP::sendJSON(false, 400, "嘿！插件包名呢");
            return false;
        } else if ($package == "Admin" || $package == "AdminAPI") {
            $mname = $method == "enable" ? "明明已经启用却还再次启用我自己" : "我禁用了我自己 (并没有成功)";
            HTTP::sendJSON(false, 500, "恭喜获得成就：{$mname}");
            return false;
        }

        $res = \OAPI\Plugin\Plugin::$method($package, true);

        if ($res["status"] == "error") {
            HTTP::sendJSON(false, 500, $res["message"]);
            return false;
        }

        HTTP::sendJSON(true, 200, $res["message"]);
    }

    /**
     * 获取单个插件的信息
     * 
     * @version 1
     * @path /admin/plugin/get
     */
    public static function getOnePluginInfo_Action($request, $response, $matches)
    {
        if (HTTP::lockMethod(["GET"]) == false) return;
        if (!self::checkAuth()) return;

        $package = HTTP::getParams("package", "");
        if (empty($package)) {
            HTTP::sendJSON(false, 400, "嘿！插件包名呢");
            return false;
        }

        if (!\OAPI\Plugin\Plugin::has($package)) {
            HTTP::sendJSON(false, 404, "插件 {$package} 不存在");
            return false;
        }

        $pl = \OAPI\Plugin\Plugin::getAllPlugins()[$package];
        HTTP::sendJSON(true, 200, "Success", $pl);
    }

    /**
     * 检查权限
     * 
     * @return boolean
     */
    public static function checkAuth($send = true): bool
    {
        $authorization = HTTP::getHeader("Authorization", "");

        if (empty($authorization)) {
            if ($send === true) HTTP::sendJSON(false, 403, "Forbidden");
            return false;
        }

        $authorization = json_decode(base64_decode($authorization), true);

        if (empty($authorization)) {
            if ($send === true) HTTP::sendJSON(false, 403, "Forbidden");
            return false;
        }

        $check_auth = User::checkAuth($authorization["admin_uid"], $authorization["admin_auth"]);

        if (!$check_auth) {
            if ($send === true) HTTP::sendJSON(false, 403, "Forbidden");
            return false;
        }

        return true;
    }

    /**
     * 判断数据库表是否存在
     * 
     * @param DB $db
     * @param string $table
     * @return bool
     */
    private static function __checkTable(DB $db, $table): bool
    {
        return empty($db->fetchAll($db->select("table_name")->from("information_schema.TABLES")->where("table_name = ?", $db->getPrefix() . $table))) ? false : true;
    }
}