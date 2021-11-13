<?php
/**
 * @Author: ohmyga
 * @Date: 2021-10-22 12:33:10
 * @LastEditTime: 2021-10-22 12:33:32
 */

namespace OAPIPlugin\Admin;

use OAPI\Libs\Libs;
use OAPI\DB\DB;
use function password_hash, password_verify, password_needs_rehash;

class User
{

    private static $_db;

    public function __construct($db = null)
    {
        self::$_db = ($db == null) ? DB::get() : $db;
    }

    /**
     * 免密码登录
     * 
     * @param string $username     用户名
     * @return array
     */
    public static function userNoPassLogin($username): array
    {
        $user = self::$_db->fetchRow(
            self::$_db
                ->select()
                ->from("table.admin_users")
                ->where("username = ?", $username)
        );

        $auth = Libs::randString(32);

        self::$_db->query(
            self::$_db->update("table.admin_users")->rows([
                "authCode"   => $auth,
                "logged"     => time()
            ])->where("uid = ?", $user["uid"])
        );

        return ["uid" => $user["uid"], "authCode" => $auth];
    }

    /**
     * 用户登录
     * 
     * @param string $username    用户名
     * @param string $password    密码
     * @return array
     */
    public static function login($username, $password): array
    {
        $user = self::$_db->fetchRow(
            self::$_db
                ->select()
                ->from("table.admin_users")
                ->where("username = ?", $username)
        );

        if (empty($user)) {
            return ["status" => false, "error" => "用户名或密码错误"];
        }

        if (password_verify($password, $user['password']) === false) {
            return ["status" => false, "error" => "用户名或密码错误"];
        }

        /** 检查密码哈希值是否需要重新计算 */
        $check_password_rehash = password_needs_rehash($user['password'], PASSWORD_DEFAULT, ['count' => __OAPI_BCRYPT_COUNT__]);
        $new_password = ($check_password_rehash) ? password_hash($user['password'], PASSWORD_DEFAULT, ['count' => __OAPI_BCRYPT_COUNT__]) : $user['password'];

        $auth = Libs::randString(32);

        self::$_db->query(
            self::$_db->update("table.admin_users")->rows([
                "authCode"   => $auth,
                "logged"     => time(),
                "password"   => $new_password
            ])->where("uid = ?", $user["uid"])
        );

        return ["status" => true, "uid" => $user["uid"], "authCode" => $auth];
    }

    /**
     * 权限检查
     * 
     * @param int|string $uid          用户 UID
     * @param string $authCode         AuthCode
     * @return boolean
     */
    public static function checkAuth($uid, $authCode): bool
    {
        $user = self::$_db->fetchRow(
            self::$_db
                ->select()
                ->from("table.admin_users")
                ->where("uid = ?", $uid)
        );

        if (empty($user)) return false;
        if (!hash_equals($authCode, $user["authCode"])) return false;

        return true;
    }
}
