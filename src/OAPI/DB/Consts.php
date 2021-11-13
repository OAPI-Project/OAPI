<?php
/**
 * @Author: ohmyga
 * @Date: 2021-10-22 11:34:46
 * @LastEditTime: 2021-10-22 11:34:53
 */

namespace OAPI\DB;

class Consts
{
    /** 读取数据库 */
    public const READ = 1;

    /** 写入数据库 */
    public const WRITE = 2;

    /** 升序方式 */
    public const SORT_ASC = "ASC";

    /** 降序方式 */
    public const SORT_DESC = "DESC";

    /** 表内连接方式 */
    public const INNER_JOIN = "INNER";

    /** 表外连接方式 */
    public const OUTER_JOIN = "OUTER";

    /** 表左连接方式 */
    public const LEFT_JOIN = "LEFT";

    /** 表右连接方式 */
    public const RIGHT_JOIN = "RIGHT";

    /** 数据库查询操作 */
    public const SELECT = "SELECT";

    /** 数据库更新操作 */
    public const UPDATE = "UPDATE";

    /** 数据库插入操作 */
    public const INSERT = "INSERT";

    /** 数据库删除操作 */
    public const DELETE = "DELETE";
}
