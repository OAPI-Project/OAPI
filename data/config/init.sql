--
-- 表的结构 `OAPI_options`
--
CREATE TABLE `OAPI_options` (
  `name` varchar(32) NOT NULL COMMENT "配置名称",
  `value` text COMMENT "配置内容",
  PRIMARY KEY (`name`)
) ENGINE = %engine% DEFAULT CHARSET = %charset%;

--
-- 表的结构 `OAPI_plugins`
--
CREATE TABLE `OAPI_plugins` (
  `package` varchar(64) NOT NULL COMMENT "插件包名",
  `name` varchar(64) NOT NULL COMMENT "插件名称",
  `author` varchar(64) NOT NULL COMMENT "插件作者",
  `version` varchar(64) NOT NULL COMMENT "插件版本",
  `status` varchar(20) NOT NULL COMMENT "插件状态",
  PRIMARY KEY (`package`)
) ENGINE = %engine% DEFAULT CHARSET = %charset%;