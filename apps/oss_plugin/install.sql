-- PbootCMS OSS云存储插件 - 安装脚本
-- 执行前请先备份数据库

-- 添加OSS配置项到 ay_config 表
INSERT INTO `ay_config` (`name`, `value`, `type`, `sorting`, `description`) VALUES
('oss_enabled', '0', '1', 255, '启用OSS上传'),
('oss_provider', 'aliyun', '2', 255, 'OSS服务商'),
('oss_access_key_id', '', '2', 255, 'OSS AccessKey ID'),
('oss_access_key_secret', '', '2', 255, 'OSS AccessKey Secret'),
('oss_endpoint', 'https://oss-cn-hangzhou.aliyuncs.com', '2', 255, 'OSS Endpoint'),
('oss_bucket', '', '2', 255, 'OSS Bucket名称'),
('oss_custom_domain', '', '2', 255, 'OSS自定义域名'),
('oss_upload_path', 'upload', '2', 255, 'OSS上传路径前缀'),
('oss_use_path_style', '0', '2', 255, '使用路径样式'),
('oss_acl', 'public-read', '2', 255, 'OSS文件权限');

-- 创建OSS上传日志表（可选）
CREATE TABLE IF NOT EXISTS `ay_oss_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL COMMENT '文件名',
  `object_path` varchar(500) DEFAULT NULL COMMENT 'OSS对象路径',
  `file_size` int(11) DEFAULT NULL COMMENT '文件大小（字节）',
  `upload_time` int(11) NOT NULL COMMENT '上传时间',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态：1成功0失败',
  `error_msg` varchar(500) DEFAULT NULL COMMENT '错误信息',
  PRIMARY KEY (`id`),
  KEY `upload_time` (`upload_time`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COMMENT='OSS上传日志表';
