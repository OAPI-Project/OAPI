<?php
/**
 * @Author: ohmyga
 * @Date: 2021-10-22 11:41:18
 * @LastEditTime: 2021-10-22 11:42:05
 */

namespace OAPI\DB\Adapter;

trait MysqlTrait
{
    use QueryTrait;

    /**
     * 清空数据表
     * 
     * @param string $table 数据表名
     * @param mixed $handle 连接对象
     */
    public function truncate(string $table, $handle)
    {
        $this->query('TRUNCATE TABLE ' . $this->quoteColumn($table), $handle);
    }

    /**
     * 合成查询语句
     * 
     * @param array $sql   查询对象词法数组
     * @return string
     */
    public function parseSelect(array $sql): string
    {
        return $this->buildQuery($sql);
    }
}
