<?php
/**
 * Vue 模板解析助手
 * 
 * @Author: ohmyga
 * @Date: 2021-12-03 12:10:33
 * @LastEditTime: 2021-12-03 12:19:37
 */

namespace OAPIPlugin\Admin;

class VueTemplate {

    /**
     * 读取并解析 vue 单文件模板
     * 再以数组返回 html / javascript / css 三件套
     * 
     * @param string $file        vue 单文件模板所在位置
     * @param string $page        页面名称
     * @return array
     */
    public static function load($file, $page = "") : array {
        if (!file_exists($file)) {
            return [
                "status"    => false,
                "code"      => 404,
                "message"   => (!empty($page)) ? "模板 {$page} 不存在" : "模板不存在"
            ];
        }

        $page_file = file_get_contents($file);
        $has_template = preg_match("/<template>([\s\S]*)<\/template>/is", $page_file, $template);
        $has_script = preg_match("/<script>([\s\S]*)<\/script>/is", $page_file, $script);
        $has_style = preg_match("/<style.*>([\s\S]*)<\/style>/is", $page_file, $style);

        return [
            "status"      => true,
            "data"        => [
                "template"    => $has_template ? trim($template[0]) : "<template><div>Not Found</div></template>",
                "script"      => $has_script ? trim($script[1]) : "",
                "style"       => $has_style ? trim($style[1]) : "",
                "scoped"      => preg_match("/<style.*noscoped>([\s\S]*)<\/style>/is", $page_file) ? false : true
            ],
            "isJSON"      => true
        ];
    }
}