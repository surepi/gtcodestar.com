-- PbootCMS OSS云存储插件 - 卸载脚本
-- 执行前请先备份数据库

-- 删除OSS配置项
DELETE FROM `ay_config` WHERE `name` IN (
    'oss_enabled',
    'oss_provider',
    'oss_access_key_id',
    'oss_access_key_secret',
    'oss_endpoint',
    'oss_bucket',
    'oss_custom_domain',
    'oss_upload_path',
    'oss_use_path_style',
    'oss_acl'
);

-- 删除OSS上传日志表
DROP TABLE IF EXISTS `ay_oss_log`;
