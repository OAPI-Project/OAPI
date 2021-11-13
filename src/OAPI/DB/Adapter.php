<?php
/**
 * @Author: ohmyga
 * @Date: 2021-10-22 11:36:15
 * @LastEditTime: 2021-10-22 11:36:22
 */

namespace OAPI\DB;

interface Adapter
{
    /**
     * 判断适配器是否可用
     *
     * @access public
     * @return boolean
     */
    public static function isAvailable(): bool;

    /**
     * 数据库连接函数
     * 
     * @param array $config   配置文件
     * @return mixed
     */
    public function connect(array $config);

    /**
     * 获取数据库版本
     * 
     * @param mixed $handle
     * @return string
     */
    public function getVersion($handle): string;

    /**
     * 清空数据表
     * 
     * @param string $table 数据表名
     * @param mixed $handle 连接对象
     */
    public function truncate(string $table, $handle);

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
    public function query(string $query, $handle, int $op = Consts::READ, ?string $action = null, ?string $table = null);

    /**
     * 取出数据库查询结果中的一行
     * 以数组方式输出
     * 
     * @param $resource       查询的资源数据
     * @return array|null
     */
    public function fetch($resource): ?array;

    /**
     * 取出数据库查询的所有结果
     * 以数组方式输出
     * 
     * @param $resource       查询的资源数据
     * @return array|null
     */
    public function fetchAll($resource): array;

    /**
     * 取出数据库查询结果中的一行
     * 以对象方式输出
     * 
     * @param $resource       查询的资源数据
     * @return object|null
     */
    public function fetchObject($resource): ?object;

    /**
     * 引号转义函数
     * 
     * @param mixed $string  需要转义的字符串
     * @return string
     */
    public function quoteValue($string): string;

    /**
     * 对象引号过滤
     * 
     * @param string $string  需要转义的字符串
     * @return string
     */
    public function quoteColumn(string $string): string;

    /**
     * 合成查询语句
     * 
     * @param array $sql   查询对象词法数组
     * @return string
     */
    public function parseSelect(array $sql): string;

    /**
     * 最后一次查询影响的行数
     * 
     * @param $resource            查询的资源数据
     * @param mixed $handle        连接对象
     * @return integer
     */
    public function affectedRows($resource, $handle): int;

    /**
     * 最后一次插入返回的主键值
     * 
     * @param $resource            查询的资源数据
     * @param mixed $handle        连接对象
     * @return integer
     */
    public function lastInsertId($resource, $handle): int;
}
