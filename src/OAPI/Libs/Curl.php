<?php
/**
 * @Author: ohmyga
 * @Date: 2021-10-22 12:12:50
 * @LastEditTime: 2021-10-22 12:13:09
 */

namespace OAPI\Libs;

class Curl
{
    /**
     * 发送 GET 请求
     * 
     * @param string $url    目标链接
     * @param array $data    提交参数
     * @param array $header  Header
     * @param array $cookie  给对方送饼干（不是）
     */
    public static function get(string $url, array $data = [], array $header = [], array $cookie = [])
    {
        if (!empty($data)) {
            $url = $url . '?' . http_build_query($data); // 组合参数附加到链接之后（例如 http://xx.com/xxx?xx=xx）
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);

        if (!empty($header)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        }

        if (!empty($cookie)) {
            curl_setopt($curl, CURLOPT_COOKIE, $cookie);
        }

        // 请求方式
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");

        // 关闭 SSL 证书验证
        // 不然破事太多了
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $exec = curl_exec($curl);

        curl_close($curl);

        return $exec;
    }

    /**
     * 发送 POST 请求
     * 
     * @param string $url    目标链接
     * @param array $data    提交参数
     * @param array $header  Header
     * @param array $cookie  提交 Cookie
     */
    public static function post(string $url, array $data = [], array $header = [], array $cookie = [])
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);

        if (!empty($header)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        }

        if (!empty($cookie)) {
            curl_setopt($curl, CURLOPT_COOKIE, $cookie);
        }

        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));

        // SSL 验证 快爬！
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        // POST~
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        $exec = curl_exec($curl);

        $output = (!empty(curl_error($curl))) ? ['error' => true, 'data' => curl_error($curl)] : $exec;

        curl_close($curl);

        return $output;
    }
}
