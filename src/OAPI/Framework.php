<?php
/**
 * OAPI Framework
 * 
 * @Author: ohmyga
 * @Date: 2021-10-21 22:43:24
 * @LastEditTime: 2021-11-23 00:52:33
 */

namespace OAPI;

use OAPI\Config\Config;
use OAPI\LogCat\LogCat;
use OAPI\LogCat\Error;
use OAPI\Console\Console;
use OAPI\DB\DB;
use OAPI\DB\Consts;
use OAPI\Redis\OMRedis;
use OAPI\HTTP\HTTP;
use OAPI\HTTP\Code;
use OAPI\HTTP\Router;
use OAPI\Plugin\Plugin;
use Swoole\Http\Server;

use function time;
use function explode;
use function file_exists, file_get_contents;
use function date_default_timezone_set;

class Framework
{
    /**
     * Swoole HTTP Server
     * 
     * @access public
     */
    public static $server;

    /**
     * Swoole HTTP 服务端收到请求时的暂存变量
     * 
     * @access public
     */
    public static $server_method = [];

    /**
     * 数据库实例化暂存变量
     * 
     * @access public
     */
    private static $_dbserver;

    /**
     * Redis 实例化暂存变量
     * 
     * @access public
     */
    private static $_redisserver;

    /**
     * OAPI 启动时间
     */
    public static $start_run_time = 0;

    /**
     * 框架初始化
     */
    public function __construct()
    {
        // 设置框架启动的时间
        self::$start_run_time = time();

        /** 引入常量 */
        require_once __DIR__ . "/global_defines.php";

        // 设置配置文件目录
        Config::setPath(__OAPI_CONFIG_DIR__);

        // 初始化日志模块 & 错误处理模块
        try {
            LogCat::init();
            Error::init();
        } catch (\OAPI\LogCat\Exception $e) {
            echo "日志 & 错误处理模块初始化失败，请查阅错误日志" . PHP_EOL;
            Error::eachxception_handler($e, false);
        }

        // 初始化控制台
        try {
            $config = Config::get("config");

            if ($config === false) {
                echo "提示：请复制文件 " . __OAPI_CONFIG_DIR__ . "/config-example.yaml 并重命名为 config.yaml 放于 " . __OAPI_CONFIG_DIR__ . " 下，修改配置文件后再重新运行" . PHP_EOL;
                exit;
            }

            $config["log"]["debug"] = __OAPI_DEBUG__;
            Config::modify("config", "log", $config["log"]);

            Console::init(
                $config["log"]["level"]
            );

            // 设置时区
            $timezone = $config["timezone"] ?? "Asia/Shanghai";
            date_default_timezone_set($timezone);

            $motd = $this->getLineMotd();

            $enum = 0;
            $eline = "+";
            while ($enum < 65) {
                $enum++;
                $eline .= "=";
            }

            if (is_array($motd) && $motd !== false) {
                foreach ($this->getLineMotd() as $line) {
                    Console::diy(Console::color($line, "blue"), false, null, true, false, false);
                }

                Console::diy(Console::color($eline, "blue"), false, null, true, false);
            }

            Console::diy(Console::color("| OAPI Version: " . __OAPI_VERSION__, "blue"), false, null, true, false);
            Console::diy(Console::color("| Author: OAPI Project (https://github.com/OAPI-Project)", "blue"), false, null, true, false);
            Console::diy(Console::color("| Github: https://github.com/OAPI-Project/OAPI", "blue"), false, null, true, false);
            Console::diy(Console::color("| LICENSE: AGPL v3.0 (https://www.gnu.org/licenses/agpl-3.0.html)", "blue"), false, null, true, false);
            Console::diy(Console::color($eline, "blue"), false, null, true, false);
            Console::info("控制台初始化完成", "Console");
        } catch (\Exception $e) {
            echo "控制台初始化失败，无法正常使用，请查阅错误日志" . PHP_EOL;
            Error::eachxception_handler($e, false);
        }

        // 初始化数据库
        try {
            $database = Config::get("config")["database"];
            $db = (new DB(
                $database["adapter"],
                $database["host"],
                $database["port"],
                $database["dbname"],
                $database["username"],
                $database["password"],
                $database["charset"],
                $database["prefix"],
                $database["engine"]
            ));
            $db->init($database["pool"]["size"], $database["pool"]["timeout"], $database["pool"]["check_timeout"]);
            self::$_dbserver = $db;
            $db->set($db);
            if (!$this->__checkTable($db, "options") || !$this->__checkTable($db, "plugins")) {
                Console::warning("正在初始化数据库...", "Database");

                if (!file_exists(__OAPI_CONFIG_DIR__ . "/init.sql")) throw new Exception("数据库初始化失败，未找到 init.sql");

                $sqlfile = file_get_contents(__OAPI_CONFIG_DIR__ . "/init.sql");
                $sqlfile = str_replace("OAPI_", $db->getPrefix(), $sqlfile);

                $sqlfile = str_replace("%engine%", isset($database["engine"]) ? $database["engine"] : "InnoDB", $sqlfile);
                $sqlfile = str_replace("%charset%", isset($database["charset"]) ? $database["charset"] : "utf8mb4", $sqlfile);

                $sqlfile = explode(";", $sqlfile);
                foreach ($sqlfile as $script) {
                    $script = trim($script);
                    if ($script) {
                        $db->query($script, Consts::WRITE);
                    }
                }

                Console::success("数据库初始化成功，已创建所需的表", "Database");
            }
            Console::info("数据库初始化完成", "Database");
        } catch (Exception $e) {
            Console::error("数据库初始化失败，请查阅日志", "Database");
            Error::eachxception_handler($e, (__OAPI_DEBUG__ === true) ? true : false);
        }

        // 初始化 Redis
        try {
            $redis_config = Config::get("config")["redis"];
            $redis = new OMRedis(
                $redis_config["host"],
                $redis_config["port"],
                $redis_config["auth"],
                $redis_config["timeout"],
                $redis_config["db_index"]
            );
            $redis->init();
            self::$_redisserver = $redis;
            $redis->set($redis);
            Console::info("Redis 初始化完成", "Redis");
        } catch (Exception $e) {
            Console::error("Redis 初始化失败，请查阅日志", "Redis");
            Error::eachxception_handler($e, (__OAPI_DEBUG__ === true) ? true : false);
        }

        // 初始化 HTTP 模块
        try {
            new Code();
        } catch (Exception $e) {
            Console::error("HTTP 模块始化失败，请查阅日志", "HTTP");
            Error::eachxception_handler($e, (__OAPI_DEBUG__ === true) ? true : false);
        }

        // 初始化路由
        try {
            $router = new Router();
        } catch (Exception $e) {
            Console::error("路由初始化失败，请查阅日志", "Router");
            Error::eachxception_handler($e, (__OAPI_DEBUG__ === true) ? true : false);
        }

        // 初始化插件
        try {
            if (!is_dir(__OAPI_ROOT_DIR__ . "/src/Plugins")) throw new Exception("插件目录不存在，初始化失败");
            $plugin = (new Plugin());
            $plugin->init();

            /** 检查 Admin API 插件是否存在并启用 (必选组件 不安装则无法正常使用) */
            if (!$plugin->has("Admin")) throw new Exception("(必须) 未找到 [Admin] 插件，请先安装后再运行 OAPI");

            if (!empty($plugin->getAllPlugins()["Admin"])) {
                if ($plugin->getAllPlugins()["Admin"]["status"] == "disable") $plugin->enable("Admin");
            } else {
                throw new Exception("(致命错误) 已在数据库中发现 [Admin] 的配置信息，但并未找到 [Admin] 的源文件，请重新安装插件后再试");
            }
        } catch (Exception $e) {
            Console::error("插件模块初始化失败，请查阅日志", "Plugin");
            Error::eachxception_handler($e, (__OAPI_DEBUG__ === true) ? true : false);
        }

        // 初始化 Swoole HTTP Server
        try {
            $config = Config::get("config");
            $this->server($config["server"]["host"], $config["server"]["port"]);
            Console::info("HTTP Server 初始化完成", "Swoole/HTTP");
        } catch (Exception $e) {
            Console::error("HTTP Server 初始化失败，请查阅日志", "Swoole");
            Error::eachxception_handler($e, (__OAPI_DEBUG__ === true) ? true : false);
        }

        self::$server->on("WorkerStart", function ($server) {
            self::$_dbserver->___initPool();
            self::$_redisserver->___initPool();
        });
    }

    /**
     * 启动服务
     */
    public function start()
    {
        self::$server->start();
    }

    /**
     * 初始化 HTTP Server
     * 
     * @param string $host   监听的主机
     * @param string $prot   监听的端口
     */
    private function server($host, $prot)
    {
        self::$server = new Server($host, $prot);
        $this->onStart();
        $this->onRequest();
    }

    /**
     * Swoole HTTP 服务器收到 HTTP 请求时所执行的函数
     * 
     * @return mixed
     * @access private
     */
    private function onRequest()
    {
        self::$server->on('request', function ($request, $response) {
            if ($request->server["request_uri"] == "/favicon.ico") {
                $response->end();
                return false;
            }

            self::$server_method["request"] = $request;
            self::$server_method["response"] = $response;

            foreach (Config::get("config", "server")["header"] as $head) {
                $response->header(key($head), current($head));
            }

            Console::info("收到一个 HTTP 请求: {$request->server["request_uri"]} | Method: {$request->server["request_method"]} | IP: " . HTTP::getUserIP(), "Swoole/HTTP");
            Router::dispatch();
        });
    }

    /**
     * Swoole HTTP 服务器启动后执行的函数
     * 
     * @return mixed
     * @access private
     */
    private function onStart()
    {
        self::$server->on("start", function ($server) {
            $config = Config::get("config");
            Console::info("HTTP Server 正常运行在 {lightlightblue}{$config["server"]["host"]}:{$config["server"]["port"]}{r} {lightblue}上{r}", "Swoole/HTTP");
        });
    }

    /**
     * 逐行获取 Motd
     * 
     * @return array|bool
     */
    private function getLineMotd(): ?array
    {
        if (file_exists(__OAPI_CONFIG_DIR__ . "/motd.txt")) {
            $motd = file_get_contents(__OAPI_CONFIG_DIR__ . "/motd.txt");
        } else {
            return false;
        }

        $motd = explode(PHP_EOL, $motd);
        return $motd;
    }

    /**
     * 判断数据库表是否存在
     * 
     * @param DB $db
     * @param string $table
     * @return bool
     */
    private function __checkTable(DB $db, $table): bool
    {
        return empty($db->fetchAll($db->select("table_name")->from("information_schema.TABLES")->where("table_name = ?", $db->getPrefix() . $table))) ? false : true;
    }
}
