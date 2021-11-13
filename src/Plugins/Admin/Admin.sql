--
-- 表的结构 `OAPI_admin_user`
--
CREATE TABLE `OAPI_admin_users` (
    `uid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT "UID",
    `username` varchar(32) DEFAULT NULL COMMENT "用户名",
    `password` varchar(64) default NULL COMMENT "密码",
    `mail` varchar(150) default NULL COMMENT "邮箱",
    `nickname` varchar(32) default NULL COMMENT "昵称",
    `created` int(10) unsigned default '0' COMMENT "注册时间",
    `logged` int(10) unsigned default '0' COMMENT "上次登录时间",
    `authCode` varchar(64) default NULL COMMENT "登录校验凭据",
    PRIMARY KEY  (`uid`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `mail` (`mail`)
) ENGINE = %engine% DEFAULT CHARSET = %charset%;

--
-- 表的结构 `OAPI_admin_options`
--
CREATE TABLE `OAPI_admin_options` (
  `name` varchar(32) NOT NULL COMMENT "配置名称",
  `value` text COMMENT "配置内容",
  PRIMARY KEY (`name`)
) ENGINE = %engine% DEFAULT CHARSET = %charset%;