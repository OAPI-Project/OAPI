<?php
/**
 * @Author: ohmyga
 * @Date: 2021-10-22 11:37:05
 * @LastEditTime: 2021-11-14 01:12:55
 */

namespace OAPI\DB;

use OAPI\DB\Adapter;
use OAPI\DB\Consts;

class Query {
    private const KEYWORDS = '*PRIMARY|AND|OR|LIKE|BINARY|BY|DISTINCT|AS|IN|IS|NULL';

    /**
     * 默认字段
     */
    private static $_default = [
        'action' => null,
        'table'  => null,
        'fields' => '*',
        'join'   => [],
        'where'  => null,
        'limit'  => null,
        'offset' => null,
        'order'  => null,
        'group'  => null,
        'having' => null,
        'rows'   => [],
    ];

    private $_adapter;

    /**
     * 查询语句预结构
     * 由数组构成
     * 方便组合为 SQL 查询字符串
     * 
     * @var array
     */
    private $_sqlPreBuild;

    private $_prefix;

    private $_params = [];

    /**
     * 构造函数
     * 引用数据库适配器作为内部数据
     */
    public function __construct(Adapter $adapter, string $prefix)
    {
        $this->_adapter = &$adapter;
        $this->_prefix = $prefix;

        $this->_sqlPreBuild = self::$_default;
    }

    /**
     * 设置默认参数
     * 
     */
    public static function setDefault(array $default)
    {
        self::$_default = array_merge(self::$_default, $default);
    }

    /**
     * 获取参数
     * 
     * @return array
     */
    public function getParams(): array
    {
        return $this->_params;
    }

    /**
     * 获取查询字串属性值
     * 
     */
    public function getAttribute(string $attributeName): ?string
    {
        return $this->_sqlPreBuild[$attributeName] ?? null;
    }

    /**
     * 清除查询字串属性值
     * 
     */
    public function cleanAttribute(string $attributeName): Query
    {
        if (isset($this->_sqlPreBuild[$attributeName])) {
            $this->_sqlPreBuild[$attributeName] = self::$_default[$attributeName];
        }
        return $this;
    }

    /**
     * 连接表
     * 
     */
    public function join(string $table, string $condition, string $op = Consts::INNER_JOIN): Query
    {
        $this->_sqlPreBuild['join'][] = [$this->filterPrefix($table), $this->filterColumn($condition), $op];
        return $this;
    }

    /**
     * 过滤表前缀
     * 表前缀由table.构成
     * 
     */
    private function filterPrefix(string $string): string
    {
        return (0 === strpos($string, 'table.')) ? substr_replace($string, $this->_prefix, 0, 6) : $string;
    }

    /**
     * 过滤数组键值
     * 
     */
    private function filterColumn(string $str): string
    {
        $str = $str . ' 0';
        $length = strlen($str);
        $lastIsAlnum = false;
        $result = '';
        $word = '';
        $split = '';
        $quotes = 0;

        for ($i = 0; $i < $length; $i++) {
            $cha = $str[$i];

            if (ctype_alnum($cha) || false !== strpos('_*', $cha)) {
                if (!$lastIsAlnum) {
                    if (
                        $quotes > 0 && !ctype_digit($word) && '.' != $split
                        && false === strpos(self::KEYWORDS, strtoupper($word))
                    ) {
                        $word = $this->_adapter->quoteColumn($word);
                    } elseif ('.' == $split && 'table' == $word) {
                        $word = $this->_prefix;
                        $split = '';
                    }

                    $result .= $word . $split;
                    $word = '';
                    $quotes = 0;
                }

                $word .= $cha;
                $lastIsAlnum = true;
            } else {
                if ($lastIsAlnum) {
                    if (0 == $quotes) {
                        if (false !== strpos(' ,)=<>.+-*/', $cha)) {
                            $quotes = 1;
                        } elseif ('(' == $cha) {
                            $quotes = - 1;
                        }
                    }

                    $split = '';
                }

                $split .= $cha;
                $lastIsAlnum = false;
            }

        }

        return $result;
    }

    /**
     * AND 条件查询语句
     * 
     */
    public function where(...$args): Query
    {
        [$condition] = $args;
        $condition = str_replace('?', "%s", $this->filterColumn($condition));
        $operator = empty($this->_sqlPreBuild['where']) ? ' WHERE ' : ' AND';

        if (count($args) <= 1) {
            $this->_sqlPreBuild['where'] .= $operator . ' (' . $condition . ')';
        } else {
            array_shift($args);
            $this->_sqlPreBuild['where'] .= $operator . ' (' . vsprintf($condition, $this->quoteValues($args)) . ')';
        }

        return $this;
    }

    /**
     * 转义参数
     * 
     */
    protected function quoteValues(array $values): array
    {
        foreach ($values as &$value) {
            if (is_array($value)) {
                $value = '(' . implode(',', array_map([$this, 'quoteValue'], $value)) . ')';
            } else {
                $value = $this->quoteValue($value);
            }
        }

        return $values;
    }

    /**
     * 延迟转义
     * 
     */
    public function quoteValue($value): string
    {
        $this->_params[] = $value;
        return '#param:' . (count($this->_params) - 1) . '#';
    }

    /**
     * OR 条件查询语句
     * 
     */
    public function orWhere(...$args): Query
    {
        [$condition] = $args;
        $condition = str_replace('?', "%s", $this->filterColumn($condition));
        $operator = empty($this->_sqlPreBuild['where']) ? ' WHERE ' : ' OR';

        if (func_num_args() <= 1) {
            $this->_sqlPreBuild['where'] .= $operator . ' (' . $condition . ')';
        } else {
            array_shift($args);
            $this->_sqlPreBuild['where'] .= $operator . ' (' . vsprintf($condition, $this->quoteValues($args)) . ')';
        }

        return $this;
    }

    /**
     * 查询行数限制
     * 
     */
    public function limit($limit): Query
    {
        $this->_sqlPreBuild['limit'] = intval($limit);
        return $this;
    }

    /**
     * 查询行数偏移量
     * 
     */
    public function offset($offset): Query
    {
        $this->_sqlPreBuild['offset'] = intval($offset);
        return $this;
    }

    /**
     * 分页查询
     * 
     */
    public function page($page, $pageSize): Query
    {
        $pageSize = intval($pageSize);
        $this->_sqlPreBuild['limit'] = $pageSize;
        $this->_sqlPreBuild['offset'] = (max(intval($page), 1) - 1) * $pageSize;
        return $this;
    }

    /**
     * 指定需要写入的栏目及其值
     * 
     */
    public function rows(array $rows): Query
    {
        foreach ($rows as $key => $row) {
            $this->_sqlPreBuild['rows'][$this->filterColumn($key)]
                = is_null($row) ? 'NULL' : $this->_adapter->quoteValue($row);
        }
        return $this;
    }

    /**
     * 指定需要写入栏目及其值
     * 单行且不会转义引号
     * 
     */
    public function expression(string $key, $value, bool $escape = true): Query
    {
        $this->_sqlPreBuild['rows'][$this->filterColumn($key)] = $escape ? $this->filterColumn($value) : $value;
        return $this;
    }

    /**
     * 排序顺序(ORDER BY)
     * 
     */
    public function order(string $orderBy, string $sort = Consts::SORT_ASC): Query
    {
        if (empty($this->_sqlPreBuild['order'])) {
            $this->_sqlPreBuild['order'] = ' ORDER BY ';
        } else {
            $this->_sqlPreBuild['order'] .= ', ';
        }

        $this->_sqlPreBuild['order'] .= $this->filterColumn($orderBy) . (empty($sort) ? null : ' ' . $sort);
        return $this;
    }

    /**
     * 集合聚集(GROUP BY)
     * 
     */
    public function group(string $key): Query
    {
        $this->_sqlPreBuild['group'] = ' GROUP BY ' . $this->filterColumn($key);
        return $this;
    }

    /**
     * 
     */
    public function having(string $condition, ...$args): Query
    {
        $condition = str_replace('?', "%s", $this->filterColumn($condition));
        $operator = empty($this->sqlPreBuild['having']) ? ' HAVING ' : ' AND';

        if (count($args) == 0) {
            $this->sqlPreBuild['having'] .= $operator . ' (' . $condition . ')';
        } else {
            $this->sqlPreBuild['having'] .= $operator . ' (' . vsprintf($condition, $this->quoteValues($args)) . ')';
        }

        return $this;
    }

    /**
     * 选择查询字段
     * 
     */
    public function select(...$args): Query
    {
        $this->_sqlPreBuild['action'] = Consts::SELECT;

        $this->_sqlPreBuild['fields'] = $this->getColumnFromParameters($args);
        return $this;
    }

    /**
     * 从参数中合成查询字段
     * 
     */
    private function getColumnFromParameters(array $parameters): string
    {
        $fields = [];

        foreach ($parameters as $value) {
            if (is_array($value)) {
                foreach ($value as $key => $val) {
                    $fields[] = $key . ' AS ' . $val;
                }
            } else {
                $fields[] = $value;
            }
        }

        return $this->filterColumn(implode(' , ', $fields));
    }

    /**
     * 查询记录操作(SELECT)
     * 
     * @param string $table   查询的表
     * @return Query
     */
    public function from(string $table): Query
    {
        $this->_sqlPreBuild['table'] = $this->filterPrefix($table);
        return $this;
    }

    /**
     * 更新记录操作(UPDATE)
     * 
     * @param string $table   需要更新记录的表
     * @return Query
     */
    public function update(string $table): Query
    {
        $this->_sqlPreBuild['action'] = Consts::UPDATE;
        $this->_sqlPreBuild['table'] = $this->filterPrefix($table);
        return $this;
    }

    /**
     * 删除记录操作(DELETE)
     * 
     * @param string $table   需要删除记录的表
     * @return Query
     */
    public function delete(string $table): Query
    {
        $this->_sqlPreBuild['action'] = Consts::DELETE;
        $this->_sqlPreBuild['table'] = $this->filterPrefix($table);
        return $this;
    }

    /**
     * 插入记录操作(INSERT)
     * 
     * @param string $table   需要插入记录的表
     * @return Query
     */
    public function insert(string $table): Query
    {
        $this->_sqlPreBuild['action'] = Consts::INSERT;
        $this->_sqlPreBuild['table'] = $this->filterPrefix($table);
        return $this;
    }

    /**
     * @param string $query
     * @return string
     */
    public function prepare(string $query): string
    {
        $params = $this->_params;
        $adapter = $this->_adapter;

        return preg_replace_callback("/#param:([0-9]+)#/", function ($matches) use ($params, $adapter) {
            if (array_key_exists($matches[1], $params)) {
                return $adapter->quoteValue($params[$matches[1]]);
            } else {
                return $matches[0];
            }
        }, $query);
    }

    /**
     * 构造最终查询语句
     *
     * @return string
     */
    public function __toString()
    {
        switch ($this->_sqlPreBuild['action']) {
            case Consts::SELECT:
                return $this->_adapter->parseSelect($this->_sqlPreBuild);
            case Consts::INSERT:
                return 'INSERT INTO '
                    . $this->_sqlPreBuild['table']
                    . '(' . implode(' , ', array_keys($this->_sqlPreBuild['rows'])) . ')'
                    . ' VALUES '
                    . '(' . implode(' , ', array_values($this->_sqlPreBuild['rows'])) . ')'
                    . $this->_sqlPreBuild['limit'];
            case Consts::DELETE:
                return 'DELETE FROM '
                    . $this->_sqlPreBuild['table']
                    . $this->_sqlPreBuild['where'];
            case Consts::UPDATE:
                $columns = [];
                if (isset($this->_sqlPreBuild['rows'])) {
                    foreach ($this->_sqlPreBuild['rows'] as $key => $val) {
                        $columns[] = "$key = $val";
                    }
                }

                return 'UPDATE '
                    . $this->_sqlPreBuild['table']
                    . ' SET ' . implode(' , ', $columns)
                    . $this->_sqlPreBuild['where'];
            default:
                return null;
        }
    }
}
