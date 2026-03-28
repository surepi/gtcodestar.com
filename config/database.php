<?php
/**
 * 主数据库连接参数，未配置的参数使用框架惯性配置
 * 如果修改为mysql数据库，请同时修改type和dbname两个参数
 */
return array(

    'database' => array(

        'type' => 'pdo_mysql', // 数据库连接驱动类型: mysqli,sqlite,pdo_mysql,pdo_sqlite

        'host' => 'localhost', // 数据库服务器

        'user' => 'gtcodest75_db01', // 数据库连接用户名

        'passwd' => 'wBYT3GBc7S2hXdnve9CH', // 数据库连接密码

        'port' => '3306', // 数据库端口

        'dbname' => 'gtcodest75_db01' // 去掉注释，启用mysql数据库，注意修改前面的连接信息及type为mysqli
    ),

);