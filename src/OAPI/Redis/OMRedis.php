<?php
/**
 * @Author: ohmyga
 * @Date: 2021-10-31 19:22:51
 * @LastEditTime: 2021-11-14 01:10:00
 */

namespace OAPI\Redis;

use Swoole\Timer;
use OAPI\LogCat\Error;

class OMRedis
{
    /**
     * Redis 配置文件
     */
    private $_config = [];

    /**
     * Redis 连接池
     */
    private static $_pool = [];

    private static $_pool_size = 64;

    private static $_timer_check_ms = 10;

    private $_tick_num = 0;

    private static $_instance;

    public $redis;

    public function __construct($host, $port, $auth = "", $timeout = 1, $db_index = 1)
    {
        $this->_config = [
            "host"      => $host,
            "port"      => (int)$port,
            "auth"      => $auth,
            "timeout"   => $timeout,
            "db_index"  => $db_index,
        ];
    }

    public function init()
    {
        $this->addPool();
    }

    public function ___initPool()
    {
        $this->addPool();
        Timer::tick(self::$_timer_check_ms, function () {
            if (empty(self::$_pool) && count(self::$_pool) <= 0) {
                $this->addPool();
                $this->_tick_num = $this->_tick_num + 1;
                return true;
            }

            $needAddPool = false;

            foreach (self::$_pool as $key => $item) {
                if ((time() - $item["create_time"]) >= $this->_config["timeout"]) {
                    self::$_pool[$key]["instance"] = null;
                    unset(self::$_pool[$key]);
                    self::$_pool = array_values(self::$_pool);
                    $needAddPool = true;
                }
            }

            if ($needAddPool === true) $this->addPool();

            $this->_tick_num = $this->_tick_num + 1;
        });
    }

    public function addPool()
    {
        if (count(self::$_pool) <= self::$_pool_size) {
            return self::$_pool[] = [
                "create_time"   =>  time(),
                "instance"      =>  $this->_connect()
            ];
        }
    }

    private function _connect()
    {
        try {
            $redis = new \Redis();
            $redis->connect($this->_config["host"], $this->_config["port"], $this->_config["timeout"]);
            if (!empty($this->_config["auth"])) $redis->auth($this->_config["auth"]);
            if ($redis->ping('kokodayo') != "kokodayo") throw new OMRedisException("无法连接至 Redis");

            return $redis;
        } catch (OMRedisException $e) {
            Error::eachxception_handler($e, (__OAPI_DEBUG__ === true) ? true : false);
        }
    }

    /**
     * Set Redis
     * 
     * @return mixed
     */
    public static function set(OMRedis $redis)
    {
        self::$_instance = $redis;
    }

    /**
     * 获取 Redis 实例
     * 
     * @return OMRedis
     */
    public static function get()
    {
        if (empty(self::$_instance)) {
            throw new OMRedisException("没有任何 Redis 实例");
        }

        self::$_instance->redis = self::$_instance->select();

        return self::$_instance;
    }

    /**
     * 清空连接池
     * 
     * @return void
     */
    public function clearPool()
    {
        self::$_pool = [];
    }

    /**
     * 选择数据库
     * 
     * @return mixed
     */
    public function select()
    {
        if (!empty(self::$_pool)) {
            return (self::$_pool[round(0, (count(self::$_pool) - 1))])["instance"];
        }
    }
}
