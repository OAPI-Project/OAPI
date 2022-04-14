<?php
/**
 * @Author: ohmyga
 * @Date: 2021-10-22 12:07:58
 * @LastEditTime: 2021-10-23 01:14:51
 */

namespace OAPI\HTTP\Router;

class Parser
{
    /**
     * 默认匹配表
     */
    private static $_defaultRegx = [];

    /**
     * 自定义匹配表
     */
    private static $_userRegx = [];

    /**
     * 路由表
     */
    private $_routes;

    /**
     * 参数表
     */
    private $_params;

    /**
     * 初始化解析器
     */
    public function __construct(array $routes)
    {
        $this->_routes = $routes;

        self::$_defaultRegx = [
            'string' => '(.%s)',
            'char' => '([^/]%s)',
            'digital' => '([0-9]%s)',
            'alpha' => '([_0-9a-zA-Z-]%s)',
            'alphaslash' => '([_0-9a-zA-Z-/]%s)',
            'split' => '((?:[^/]+/)%s[^/]+)',
        ];
    }

    /**
     * 添加自定义匹配规则
     * 
     * @param string $key         规则
     * @param string $regx        匹配条件
     * @return array
     */
    public static function addUserRegx($key, $regx): array
    {
        if (empty(self::$_userRegx[$key]) && empty(self::$_defaultRegx[$key])) {
            self::$_userRegx[$key] = $regx;
            self::$_defaultRegx[$key] = $regx;
        }

        return [$key => $regx];
    }

    /**
     * 解析路由表
     * 
     * @return array
     */
    public function parse(): array
    {
        $result = [];

        foreach ($this->_routes as $key => $route) {
            $this->_params = [];

            if ($route['url'] != "/" && $route["disableVersion"] != "true") {
                $route['url'] = "/v" . (int)$route['version'] . $route['url'];
            }

            $route['regx'] = preg_replace_callback(
                "/%([^%]+)%/",
                [$this, 'match'],
                preg_quote(str_replace(['[', ']', ':'], ['%', '%', ' '], $route['url']))
            );

            /** 处理斜线 */
            $route['regx'] = rtrim($route['regx'], '/');
            $route['regx'] = '|^' . $route['regx'] . '[/]?$|';

            $route['format'] = preg_replace("/\[([^\]]+)\]/", "%s", $route['url']);
            $route['params'] = $this->_params;

            $result[$key] = $route;
        }

        return $result;
    }

    /**
     * 局部匹配并替换正则字符串
     *
     * @param array $matches      匹配部分
     * @return string
     */
    public function match(array $matches): string
    {
        $params = explode(' ', $matches[1]);
        $paramsNum = count($params);
        $this->_params[] = $params[0];

        if (1 == $paramsNum) {
            return sprintf(!empty(self::$_userRegx[$params[0]]) ? self::$_userRegx[$params[0]] : self::$_defaultRegx['char'], '+');
        } elseif (2 == $paramsNum) {
            return sprintf(!empty(self::$_userRegx[$params[1]]) ? self::$_userRegx[$params[1]] : self::$_defaultRegx[$params[1]], '+');
        } elseif (3 == $paramsNum) {
            return sprintf(
                !empty(self::$_userRegx[$params[1]]) ? self::$_userRegx[$params[1]] : self::$_defaultRegx[$params[1]],
                $params[2] > 0 ? '{' . $params[2] . '}' : '*');
        } elseif (4 == $paramsNum) {
            return sprintf(
                !empty(self::$_userRegx[$params[1]]) ? self::$_userRegx[$params[1]] : self::$_defaultRegx[$params[1]],
                '{' . $params[2] . ',' . $params[3] . '}');
        }

        return $matches[0];
    }
}
