# S3存储插件安装指南

## 一、插件安装

### 1. 文件已部署
插件文件已经创建在: `e:/2026-3-12/plugins/s3storage/`

### 2. 安装AWS SDK
在项目根目录执行以下命令:

```bash
cd e:/2026-3-12
composer require aws/aws-sdk-php
```

如果服务器上没有composer,可以先下载composer.phar:

```bash
php composer.phar require aws/aws-sdk-php
```

## 二、配置后台访问

### 方法1: 直接访问配置页面
```
http://yourdomain.com/admin.php?p=/plugin/s3storage/admin/Config/index
```

### 方法2: 集成到后台菜单(可选)

在后台菜单中添加S3配置入口:

1. 编辑后台菜单配置(在数据库 `ay_menu` 表中)
2. 添加新菜单项:
   - 名称: S3存储配置
   - 链接: `/plugin/s3storage/admin/Config/index`
   - 图标: 可选择合适图标

## 三、配置示例

### AWS S3配置示例

```
✓ 启用S3存储
Bucket名称: my-website-bucket
Access Key: AKIAIOSFODNN7EXAMPLE
Secret Key: wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
Region: us-east-1
自定义Endpoint: (留空)
使用Path Style: (不勾选)
CDN域名: (可选,如: https://cdn.example.com)
上传路径: uploads
ACL权限: 公开读取
```

### 阿里云OSS配置示例

```
✓ 启用S3存储
Bucket名称: my-oss-bucket
Access Key: LTAI4Gxxxxxxxxxxxxxxx
Secret Key: xxxxxxxxxxxxxxxxxxxxxxxx
Region: oss-cn-hangzhou.aliyuncs.com
自定义Endpoint: https://oss-cn-hangzhou.aliyuncs.com
使用Path Style: (不勾选)
CDN域名: https://cdn.aliyuncs.com
上传路径: uploads
ACL权限: 公开读取
```

### 腾讯云COS配置示例

```
✓ 启用S3存储
Bucket名称: my-cos-bucket-1250000000
Access Key: AKIDxxxxxxxxxxxxxxxxxxx
Secret Key: xxxxxxxxxxxxxxxxxxxxxxxx
Region: cos.ap-guangzhou.myqcloud.com
自定义Endpoint: https://cos.ap-guangzhou.myqcloud.com
使用Path Style: (不勾选)
CDN域名: https://file.myqcloud.com
上传路径: uploads
ACL权限: 公开读取
```

### MinIO配置示例

```
✓ 启用S3存储
Bucket名称: my-minio-bucket
Access Key: minioadmin
Secret Key: minioadmin
Region: us-east-1
自定义Endpoint: https://minio.example.com
✓ 使用Path Style
CDN域名: (留空)
上传路径: uploads
ACL权限: 公开读取
```

## 四、在项目中使用

### 场景1: 修改现有上传代码

原代码:
```php
$file = upload('image', 'jpg,png,gif');
```

修改为:
```php
require ROOT_PATH . '/plugins/s3storage/upload_enhanced.php';
$result = upload_with_s3('image', 'jpg,png,gif');

// 使用S3 URL优先
$url = $result['s3'] ?: get_http_url() . '/' . $result['local'];
```

### 场景2: 在后台编辑器中使用

在图片上传处理器中集成:

```php
// 编辑器上传处理
require ROOT_PATH . '/plugins/s3storage/upload_enhanced.php';

$result = upload_with_s3('file', 'jpg,png,gif,jpeg');

if (empty($result['error'])) {
    // 返回S3 URL给编辑器
    echo json_encode(array(
        'error' => 0,
        'url' => $result['s3'] ?: get_http_url() . '/' . $result['local']
    ));
} else {
    echo json_encode(array(
        'error' => 1,
        'message' => $result['error']
    ));
}
```

### 场景3: 会员头像上传

```php
require ROOT_PATH . '/plugins/s3storage/upload_enhanced.php';

// 上传头像
$result = upload_with_s3('avatar', 'jpg,png,gif', 200, 200);

if (empty($result['error'])) {
    // 保存到数据库
    $avatar_url = $result['s3'] ?: get_http_url() . '/' . $result['local'];
    
    // 更新用户数据
    // ...
}
```

## 五、测试步骤

1. **安装测试**
   - 检查 AWS SDK 是否正确安装
   ```bash
   php -r "require 'vendor/autoload.php'; echo 'AWS SDK loaded successfully\n';"
   ```

2. **配置测试**
   - 访问后台配置页面
   - 填写配置信息
   - 点击"测试连接"按钮

3. **上传测试**
   - 创建测试脚本 `test_upload.php`:
   ```php
   <?php
   require ROOT_PATH . '/plugins/s3storage/upload_enhanced.php';
   
   // 创建测试文件
   $test_file = ROOT_PATH . '/test.jpg';
   file_put_contents($test_file, 'test image content');
   
   // 上传测试
   $result = upload_to_s3($test_file, 'test/test.jpg');
   
   var_dump($result);
   
   // 清理
   unlink($test_file);
   ?>
   ```

4. **访问测试**
   - 检查S3控制台是否看到文件
   - 通过URL访问文件是否能正常显示

## 六、故障排查

### 问题1: composer install 失败

**解决方案:**
```bash
# 使用中国镜像
composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/
composer require aws/aws-sdk-php
```

### 问题2: 找不到S3Storage类

**解决方案:**
检查文件路径是否正确:
```php
require ROOT_PATH . '/plugins/s3storage/S3Storage.php';
```

### 问题3: 上传超时

**解决方案:**
在 `php.ini` 中增加:
```ini
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
```

### 问题4: SSL证书错误

**解决方案:**
在 `S3Storage.php` 的 `$s3Config` 中添加:
```php
'http' => [
    'verify' => false  // 仅用于测试环境
]
```

## 七、性能优化建议

1. **使用CDN加速**
   - 配置CDN域名
   - S3文件通过CDN访问

2. **启用本地缓存**
   - 插件默认同时保存到本地
   - 首次访问从本地,后续从CDN

3. **批量上传**
   - 使用 `upload_to_s3_batch()` 函数
   - 减少请求次数

4. **压缩图片**
   - 上传前使用系统图片处理功能
   - 减少传输时间

## 八、安全建议

1. **保护配置文件**
   - 确保 `plugin.json` 不可通过Web直接访问
   - 建议添加 `.htaccess`:
   ```
   <Files "plugin.json">
       Order Allow,Deny
       Deny from all
   </Files>
   ```

2. **使用IAM权限**
   - 不要使用根账号的AccessKey
   - 创建专用IAM用户,只授予必要的S3权限

3. **定期轮换密钥**
   - 定期更新AccessKey和SecretKey
   - 使用AWS KMS管理密钥

## 九、升级维护

### 检查插件更新
```bash
cd plugins/s3storage
git pull  # 如果使用git管理
```

### 备份配置
```bash
cp plugin.json plugin.json.backup
```

### 清理本地文件
如果只使用S3存储,可以定期清理本地文件:
```php
// 定时任务脚本
require ROOT_PATH . '/plugins/s3storage/upload_s3.php';

// 扫描本地upload目录
$local_dir = ROOT_PATH . '/static/upload';
// ... 清理逻辑
```

## 十、联系支持

如遇到问题:
1. 查看PHP错误日志
2. 查看插件README.md
3. 检查S3服务提供商的文档
