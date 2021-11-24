<?php

/**
 * @Author: ohmyga
 * @Date: 2021-10-22 12:06:22
 * @LastEditTime: 2021-11-23 00:25:19
 */

namespace OAPI\HTTP;

use OAPI\Framework;
use function is_array;
use function strtolower;
use function json_encode, json_decode;

class HTTP
{
    public static function handleOptions()
    {
        self::setCode(204);
        self::setHeader("Access-Control-Allow-Headers", "Origin, No-Cache, X-Requested-With, If-Modified-Since, Pragma, Last-Modified, Cache-Control, Expires, Content-Type, Authorization");
        self::setHeader("Access-Control-Allow-Methods", "GET, POST, PUT, DELETE, OPTIONS");
    }

    /**
     * 返回 JSON
     * 
     * @param bool $status      当前状态
     * @param int $code         HTTP Code
     * @param string $message   返回的消息
     * @param array $data       返回的数据
     * @param array $more       更多字段
     * @return void
     */
    public static function sendJSON(bool $status, $code, $message, array $data = [], array $more = [])
    {
        $data = [
            "status"    => $status === true ? true : false,
            "code"      => (int)$code,
            "message"   => $message,
            "data"      => $data,
        ];

        if (is_array($more)) {
            $data = array_merge($data, $more);
        }

        self::setCode($code);
        self::setHeader("Content-type", "application/json; charset=utf-8");
        Framework::$server_method["response"]->end(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 返回 Raw JSON
     * 
     * @param int $code       HTTP code
     * @param array $data     返回的数据数组
     * @return void
     */
    public static function sendRawJSON($code, array $data = [])
    {
        self::setCode($code);
        self::setHeader("Content-type", "application/json; charset=utf-8");
        Framework::$server_method["response"]->end(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 返回任何内容
     * 
     * @param int $code      HTTP Code
     * @param mixed $data    返回的内容
     * @param array $header  设置 Header
     * @return void
     */
    public static function send($code, $data, array $header = [])
    {
        self::setCode($code);

        if (count($header) > 0) {
            foreach ($header as $key => $value) {
                self::setHeader($key, $value);
            }
        }

        Framework::$server_method["response"]->end($data);
    }

    /**
     * 返回重定向
     * 
     * @param int $code       HTTP Code [301/302]
     * @param string $url     重定向的链接
     * @return void
     */
    public static function sendRedirect($code, $url)
    {
        $code = ($code == 301) ? 301 : 302;
        Framework::$server_method["response"]->redirect($url, $code);
    }

    /**
     * 设置 HTTP Code
     * 
     * @param int $code      状态码
     */
    public static function setCode($code = 200)
    {
        Framework::$server_method["response"]->status($code, Code::get($code));
    }

    /**
     * 设置 Header
     * 
     * @param string $key
     * @param string $value
     */
    public static function setHeader($key, $value)
    {
        Framework::$server_method["response"]->header($key, $value);
    }

    /**
     * 锁定请求方式
     * 
     * @param string|array $method     请求方式
     */
    public static function lockMethod($method)
    {
        if (is_array($method)) {
            $_methods = [];
            foreach ($method as $val) {
                $_methods[] = strtolower($val);
            }
            if (!in_array(strtolower(Framework::$server_method["request"]->server["request_method"]), $_methods)) {
                self::_LockMethodMsg();
                return false;
            }
        } else {
            if (strtolower(Framework::$server_method["request"]->server["request_method"]) != strtolower($method)) {
                self::_LockMethodMsg();
                return false;
            }
        }

        return true;
    }

    /**
     * 锁定请求方式输出函数
     * 
     * @access private
     */
    private static function _LockMethodMsg()
    {
        self::sendJSON(false, 405, "Method not allowed");
    }

    /**
     * 获取 GET / POST 参数
     * 
     * @param string $key     参数的键
     * @param mixed $default  默认值
     * @return mixed
     */
    public static function getParams($key, $default = null)
    {
        if (strtolower(Framework::$server_method["request"]->server["request_method"]) == "get") {
            if (!empty(Framework::$server_method["request"]->get[$key]) || isset(Framework::$server_method["request"]->get[$key])) {
                return Framework::$server_method["request"]->get[$key];
            }
        }

        if (strtolower(Framework::$server_method["request"]->server["request_method"]) == "post" || strtolower(Framework::$server_method["request"]->server["request_method"]) == "delete") {
            if (is_array(json_decode(Framework::$server_method["request"]->rawContent(), true))) {
                $data = json_decode(Framework::$server_method["request"]->rawContent(), true);
                if (!empty($data[$key]) || isset($data[$key])) return $data[$key];
                if (!empty(Framework::$server_method["request"]->get[$key]) || isset(Framework::$server_method["request"]->get[$key])) return Framework::$server_method["request"]->get[$key];
                return $default;
            } else if (!empty(Framework::$server_method["request"]->post[$key])) {
                return Framework::$server_method["request"]->post[$key];
            }
        }

        if (!empty(Framework::$server_method["request"]->get)) {
            $data = Framework::$server_method["request"]->get;
            return (!empty($data[$key]) || isset($data[$key])) ? $data[$key] : $default;
        } else {
            return $default;
        }
    }

    /**
     * 获取 Cookie
     * 
     * @param string $key     参数的键
     * @param mixed $default  默认值
     * @return mixed
     */
    public static function getCookie($key, $default = null)
    {
        if (!empty(Framework::$server_method["request"]->cookie)) {
            $data = Framework::$server_method["request"]->cookie;
            return (!empty($data[$key])) ? $data[$key] : $default;
        } else {
            return $default;
        }
    }

    /**
     * 获取 Headers
     * 
     * @param string $key     参数的键
     * @param mixed $default  默认值
     * @return mixed
     */
    public static function getHeader($key, $default = null)
    {
        $key = strtolower($key);
        if (!empty(Framework::$server_method["request"]->header)) {
            $data = Framework::$server_method["request"]->header;
            return (!empty($data[$key])) ? $data[$key] : $default;
        } else {
            return $default;
        }
    }

    /**
     * 获取当前域名
     * 
     * @return string
     */
    public static function getSiteUrl()
    {
        return self::getHttpType() . self::getHeader("host", "");
    }

    /**
     * 获取当前请求协议
     * 
     * @return string
     */
    public static function getHttpType()
    {
        return (self::getHeader("x-forwarded-proto") !== null && strtolower(self::getHeader("x-forwarded-proto")) == 'https') ? 'https://' : 'http://';
    }

    /**
     * 获取真实的客户端 IP
     * 
     * @return string
     */
    public static function getUserIP()
    {
        if (!empty(self::getHeader("x-forwarded-for"))) {
            $exp = explode(", ", self::getHeader("x-forwarded-for"));
            $user_ip = (!empty($exp)) ? $exp[0] : self::getHeader("x-forwarded-for");
        } else if (!empty(self::getHeader("x-real-ip"))) {
            $user_ip = self::getHeader("x-real-ip");
        } else if (!empty(Framework::$server_method["request"]->server["remote_addr"])) {
            $user_ip = Framework::$server_method["request"]->server["remote_addr"];
        } else {
            $user_ip = "127.0.0.1";
        }

        return $user_ip;
    }

    /**
     * 获取请求类型
     * 
     * @return string
     */
    public static function getMethod()
    {
        return strtolower(Framework::$server_method["request"]->server["request_method"]);
    }

    /**
     * 判断是否为 GET
     * 
     * @return bool
     */
    public static function isGet(): bool
    {
        return (self::getMethod() == "get") ? true : false;
    }

    /**
     * 判断是否为 POST
     * 
     * @return bool
     */
    public static function isPost(): bool
    {
        return (self::getMethod() == "post") ? true : false;
    }

    /**
     * 判断是否为 PUT
     * 
     * @return bool
     */
    public static function isPut(): bool
    {
        return (self::getMethod() == "put") ? true : false;
    }

    /**
     * 判断是否为 PUT
     * 
     * @return bool
     */
    public static function isDelete(): bool
    {
        return (self::getMethod() == "put") ? true : false;
    }
}
