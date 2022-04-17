<?php
/**
 * @Author: ohmyga
 * @Date: 2021-10-22 11:33:08
 * @LastEditTime: 2022-04-17 15:50:05
 */

namespace OAPI\DB;

use Swoole\Timer;
use OAPI\DB\Consts;
use OAPI\DB\Adapter;
use \Exception;

class DB
{

    /**
     * 数据库适配器
     */
    private $_adapter;

    /**
     * 数据库适配器名称
     */
    private $_adapterName;

    /**
     * 数据库表前缀
     */
    private $_prefix;

    /**
     * 数据库链接池
     */
    private static $_pool;

    /**
     * 连接池最大数量
     */
    private static $_pool_size = 64;

    /**
     * 连接超时时间
     */
    private static $_pool_wait_timeout = 600;

    private static $_timer_check_ms = 60000;

    private $_tick_num = 0;

    /**
     * 数据库配置文件
     */
    private $_config = [];

    private static $_instance;

    public function __construct($adapter, $host, int $port, $db, $username, $password, $charset = "utf8mb4", $prefix = "OAPI_", $engine = "InnoDB")
    {
        $adapterName = $adapter == 'Mysql' ? 'Mysqli' : $adapter;
        $this->_adapterName = $adapterName;

        $adapterName = '\OAPI\DB\Adapter\\' . str_replace('_', '\\', $adapterName);

        if (!method_exists($adapterName, 'isAvailable')) {
            throw new \Exception("没有找到名为 {$this->_adapterName} 的数据库适配器");
        }

        $this->_prefix = $prefix;
        $this->_config = [
            "host"       =>  $host,
            "port"       =>  (int)$port,
            "db"         =>  $db,
            "username"   =>  $username,
            "password"   =>  $password,
            "charset"    =>  $charset,
            "prefix"     =>  $this->_prefix,
            "engine"     =>  $engine
        ];

        self::$_pool = [];

        //实例化适配器对象
        $this->_adapter = new $adapterName();
    }

    public function init(int $pool_size = 32, int $timeout = 600, int $check_time = 60000)
    {
        self::$_pool_size = $pool_size;
        self::$_pool_wait_timeout = $timeout;
        self::$_timer_check_ms = ($check_time === 0) ? $timeout * 1000 : $check_time;

        $this->addPool();
    }

    public function ___initPool()
    {
        Timer::tick(self::$_timer_check_ms, function () {
            if (empty(self::$_pool) && count(self::$_pool) <= 0) {
                $this->addPool();
                $this->_tick_num = $this->_tick_num + 1;
                return true;
            }

            $needAddPool = false;

            foreach (self::$_pool as $key => $item) {
                if ((time() - $item["create_time"]) >= self::$_pool_wait_timeout) {
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

    /**
     * 创建数据库适配器实例并加入连接池
     * 
     * @return array
     * @access private
     */
    public function addPool(bool $locked = false): array
    {
        if (count(self::$_pool) <= self::$_pool_size) {
            return self::$_pool[] = [
                "id"            =>  (int)(mt_rand(100, mt_rand(300, 900)) . substr(time(), 0, 3) . date("His")),
                "locked"        =>  $locked,
                "create_time"   =>  time(),
                "instance"      =>  $this->_adapter->init($this->_config)
            ];
        } else {
            foreach (self::$_pool as $key => $item) {
                self::$_pool[$key]["locked"] = false;
            }
            return self::$_pool[mt_rand(0, count(self::$_pool) - 1)];
        }
    }

    private function _changelockConnect($id, $lock = false)
    {
        $_pool = !empty(self::$_pool) ? self::$_pool : [];

        foreach ($_pool as $key => $item) {
            if ($item["id"] == $id) {
                self::$_pool[$key]["locked"] = ($lock === true) ? true : false;
                break;
            }
        }
    }

    /**
     * 获取适配器名称
     * 
     * @return string
     */
    public function getAdapterName(): string
    {
        return $this->_adapterName;
    }

    /**
     * 获取数据库表前缀
     * 
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->_prefix;
    }

    /**
     * 获取数据库表编码
     * 
     * @return string
     */
    public function getCharset(): string
    {
        return $this->_config["charset"];
    }

    /**
     * 获取数据库表引索
     * 
     * @return string
     */
    public function getEngine(): string
    {
        return $this->_config["engine"];
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
    public function selectDb($op = 0, $reid = false)
    {
        if (!empty(self::$_pool)) {
            $_hasUnLock = false;
            $_selectDB = [];
            foreach (self::$_pool as $key => $item) {
                if ($item["locked"] === false) {
                    $_selectDB = $item;
                    $_hasUnLock = true;
                }
            }

            if ($_hasUnLock === false) {
                $add = $this->addPool(true);
                return (!empty($add)) ? (($reid === true) ? $add : $add["instance"]) : [];
            } else {
                $this->_changelockConnect($_selectDB["id"], true);
            }

            return ($reid === true) ? $_selectDB : $_selectDB["instance"];
        }
    }

    /**
     * 获取SQL词法构建器实例化对象
     *
     * @return Query
     */
    public function sql(): Query
    {
        return new Query($this->_adapter, $this->_prefix);
    }

    /**
     * 获取数据库版本
     * 
     * 
     */
    public function getVersion(): string
    {
        return $this->_adapter->getVersion($this->selectDb());
    }

    public static function set(DB $db)
    {
        self::$_instance = $db;
    }

    /**
     * 获取数据库实例
     */
    public static function get()
    {
        if (empty(self::$_instance)) {
            throw new Exception("没有任何数据库实例");
        }

        return self::$_instance;
    }

    /**
     * 选择查询字段
     * 
     * 
     */
    public function select(...$ags): Query
    {
        $this->selectDb(Consts::READ);

        $args = func_get_args();
        return call_user_func_array([$this->sql(), 'select'], $args ?: ['*']);
    }

    /**
     * 更新记录操作(UPDATE)
     */
    public function update(string $table): Query
    {
        $this->selectDb(Consts::WRITE);

        return $this->sql()->update($table);
    }

    /**
     * 删除记录操作(DELETE)
     */
    public function delete(string $table): Query
    {
        $this->selectDb(Consts::WRITE);

        return $this->sql()->delete($table);
    }

    /**
     * 插入记录操作(INSERT)
     */
    public function insert(string $table): Query
    {
        $this->selectDb(Consts::WRITE);

        return $this->sql()->insert($table);
    }

    /**
     * @param $table
     * @throws DbException
     */
    public function truncate($table)
    {
        $table = preg_replace("/^table\./", $this->prefix, $table);
        $this->adapter->truncate($table, $this->selectDb(Consts::WRITE));
    }

    /**
     * 执行查询语句
     */
    public function query($query, int $op = Consts::READ, string $action = Consts::SELECT)
    {
        $table = null;

        /** 在适配器中执行查询 */
        if ($query instanceof Query) {
            $action = $query->getAttribute('action');
            $table = $query->getAttribute('table');
            $op = (Consts::UPDATE == $action || Consts::DELETE == $action
                || Consts::INSERT == $action) ? Consts::WRITE : Consts::READ;
        } elseif (!is_string($query)) {
            /** 如果query不是对象也不是字符串,那么将其判断为查询资源句柄,直接返回 */
            return $query;
        }

        /** 选择连接池 */
        $handle = $this->selectDb($op, true);

        /** 提交查询 */
        $resource = $this->_adapter->query($query instanceof Query ?
            $query->prepare($query) : $query, $handle["instance"], $op, $action, $table);

        $this->_changelockConnect($handle["id"], false);

        if ($action) {
            //根据查询动作返回相应资源
            switch ($action) {
                case Consts::UPDATE:
                case Consts::DELETE:
                    return $this->_adapter->affectedRows($resource, $handle["instance"]);
                case Consts::INSERT:
                    return $this->_adapter->lastInsertId($resource, $handle["instance"]);
                case Consts::SELECT:
                default:
                    return $resource;
            }
        } else {
            //如果直接执行查询语句则返回资源
            return $resource;
        }
    }

    /**
     * 一次取出所有行
     */
    public function fetchAll($query, ?callable $filter = null): array
    {
        //执行查询
        $resource = $this->query($query);
        $result = $this->_adapter->fetchAll($resource);

        return $filter ? array_map($filter, $result) : $result;
    }

    /**
     * 一次取出一行
     */
    public function fetchRow($query, ?callable $filter = null): ?array
    {
        $resource = $this->query($query);

        return ($rows = $this->_adapter->fetch($resource)) ?
            ($filter ? call_user_func($filter, $rows) : $rows) :
            null;
    }

    /**
     * 一次取出一个对象
     */
    public function fetchObject($query, ?array $filter = null): ?object
    {
        $resource = $this->query($query);

        return ($rows = $this->_adapter->fetchObject($resource)) ?
            ($filter ? call_user_func($filter, $rows) : $rows) :
            null;
    }
}
