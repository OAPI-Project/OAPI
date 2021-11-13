<?php
/**
 * @Author: ohmyga
 * @Date: 2021-10-22 11:38:18
 * @LastEditTime: 2021-10-22 11:38:31
 */

namespace OAPI\DB\Adapter;

use PDOStatement, PDOException;
use OAPI\DB\Adapter;
use OAPI\DB\Consts;

abstract class Pdo implements Adapter
{

    /**
     * 数据库对象
     */
    protected $object;

    /**
     * 判断 PDO 是否可用
     * 
     * @return bool
     */
    public static function isAvailable(): bool
    {
        return class_exists('PDO');
    }

    /**
     * 数据库连接函数
     */
    public function connect(array $config)
    {
        try {
            $this->object = $this->init($config);
            $this->object->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            return $this->object;
        } catch (PDOException $e) {
            print_r($e);
            exit(0);
        }
    }

    /**
     * 初始化数据库
     * 
     * @abstract
     * @param array $config  数据库配置文件
     */
    abstract public function init(array $config): \PDO;

    /**
     * 获取数据库版本
     * 
     * @param mixed $handle
     * @return string
     */
    public function getVersion($handle): string
    {
        return 'pdo:' . $handle->getAttribute(\PDO::ATTR_DRIVER_NAME)
            . ' ' . $handle->getAttribute(\PDO::ATTR_SERVER_VERSION);
    }

    /**
     * 执行数据库查询
     *
     * @param string $query        SQL 语句
     * @param mixed $handle        连接对象
     * @param integer $op          数据库读写状态
     * @param string|null $action  数据库动作
     * @param string|null $table   数据表
     * @return resource
     */
    public function query(string $query, $handle, int $op = Consts::READ, ?string $action = null, ?string $table = null): PDOStatement
    {
        try {
            $this->lastTable = $table;
            $resource = $handle->prepare($query);
            $resource->execute();
        } catch (PDOException $e) {
            print_r($e);
            exit(0);
        }

        return $resource;
    }

    /**
     * 取出数据库查询结果中的一行
     * 以数组方式输出
     * 
     * @param $resource       查询的资源数据
     * @return array|null
     */
    public function fetch($resource): ?array
    {
        return $resource->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * 取出数据库查询的所有结果
     * 以数组方式输出
     * 
     * @param $resource       查询的资源数据
     * @return array|null
     */
    public function fetchAll($resource): array
    {
        return $resource->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * 取出数据库查询结果中的一行
     * 以对象方式输出
     * 
     * @param $resource       查询的资源数据
     * @return object|null
     */
    public function fetchObject($resource): ?object
    {
        return $resource->fetchObject() ?: null;
    }

    public function quoteValue($string): string
    {
        return $this->object->quote($string);
    }

    /**
     * 最后一次查询影响的行数
     * 
     * @param $resource            查询的资源数据
     * @param mixed $handle        连接对象
     * @return integer
     */
    public function affectedRows($resource, $handle): int
    {
        return $resource->rowCount();
    }

    /**
     * 最后一次插入返回的主键值
     * 
     * @param $resource            查询的资源数据
     * @param mixed $handle        连接对象
     * @return integer
     */
    public function lastInsertId($resource, $handle): int
    {
        return $handle->lastInsertId();
    }
}