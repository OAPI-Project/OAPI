<?php
/**
 * @Author: ohmyga
 * @Date: 2021-10-22 12:04:31
 * @LastEditTime: 2021-10-30 17:30:27
 */

namespace OAPI\HTTP;

use OAPI\Framework;
use OAPI\HTTP\Router\Parser;
use OAPI\Console\Console;
use function count;
use function is_array;

class Router
{
    /**
     * 路由表
     */
    private static $_routes = [];

    /**
     * 初始化
     */
    public function __construct()
    {
    }

    /**
     * 路由分发函数
     */
    public static function dispatch()
    {
        $request = Framework::$server_method["request"];
        $response = Framework::$server_method["response"];
        if (strtolower($request->server["request_method"]) == "options") {
            HTTP::handleOptions();
            $response->end();
            return true;
        }

        $_has = false;
        $request->server["request_uri"] = str_replace("//", "/", $request->server["request_uri"]);
        foreach (self::$_routes as $route) {
            if (preg_match($route['regx'], $request->server["request_uri"], $matches)) {
                call_user_func($route["widget"], $request, $response, $matches);
                $_has = true;
                Console::success("[{$request->server["request_uri"]}]在路由表中匹配成功", "Router");
            }
        }

        if ($_has === false) {
            HTTP::sendJSON(false, 404, "404 Not Found");
            Console::warning("[{$request->server["request_uri"]}]在路由表中匹配失败 (404)", "Router");
        }
    }

    /**
     * 添加路由
     * 
     * @param array $route     单个路由
     * @return array           路由解析结果
     */
    public static function add(array $route): array
    {
        $route["disableVersion"] = (isset($route["disableVersion"])) ? $route["disableVersion"] : false;
        if ($route["url"] != "/" && $route["disableVersion"] != "true") {
            $route["version"] = (!empty($route["version"])) ? $route["version"] : "1";
        }
        $parser = new Parser([$route]);
        self::$_routes[] = $parser->parse()[0];

        return $parser->parse()[0];
    }

    /**
     * 删除路由
     * 
     * @param int $id      路由表中的 ID
     * @return array       路由表内容
     */
    public static function remove($id): array
    {
        if (!empty(self::$_routes[$id])) {
            unset(self::$_routes[$id]);
        }

        return self::$_routes;
    }

    /**
     * 路由重新排序
     * 
     * @return array
     */
    public static function values(): array
    {
        self::$_routes = array_values(self::$_routes);
        return self::$_routes;
    }

    /**
     * 获取已解析的路由表
     * 
     * @return array
     */
    public static function getRoutes(): array
    {
        return (is_array(self::$_routes) && count(self::$_routes) > 0) ? self::$_routes : [];
    }
}
