<?php
/**
 * PbootCMS OSS云存储插件配置文件
 */

return array(
    // 插件名称
    'name' => 'OSS云存储插件',

    // 插件版本
    'version' => '1.0.0',

    // 插件作者
    'author' => 'Your Name',

    // 插件描述
    'description' => '支持阿里云OSS、腾讯云COS、AWS S3、MinIO等云存储',

    // PbootCMS最低版本要求
    'min_pbootcms_version' => '3.0.0',

    // PHP最低版本要求
    'min_php_version' => '7.0',

    // 必需的PHP扩展
    'required_extensions' => [
        'curl',
    ],

    // 插件类型：admin, api, home
    'type' => 'admin',

    // 是否启用
    'enabled' => true,

    // 插件安装时间
    'install_time' => '',
);
