# S3存储上传插件

一个用于PbootCMS的S3存储桶上传插件,支持将静态文件上传到AWS S3或兼容S3 API的存储服务。

## 功能特性

- ✅ 支持AWS S3官方存储
- ✅ 支持所有兼容S3 API的存储服务(MinIO、阿里云OSS、腾讯云COS、七牛云等)
- ✅ 后台可视化配置界面
- ✅ 连接测试功能
- ✅ 支持CDN域名配置
- ✅ 支持自定义上传路径
- ✅ 支持文件访问权限(ACL)设置
- ✅ 提供增强版上传函数,支持本地+S3双存储
- ✅ 详细的错误提示和日志记录

## 安装步骤

### 1. 安装AWS SDK for PHP

在项目根目录执行:

```bash
composer require aws/aws-sdk-php
```

### 2. 部署插件

将 `plugins/s3storage` 目录复制到你的PbootCMS项目的 `plugins` 目录下。

### 3. 访问配置页面

访问后台配置页面进行设置:
```
http://yourdomain.com/admin.php?p=/plugin/s3storage/admin/Config/index
```

## 配置说明

### 基础配置

| 配置项 | 说明 | 必填 | 示例 |
|--------|------|------|------|
| 启用S3存储 | 是否启用插件 | 是 | 勾选 |
| Bucket名称 | S3存储桶名称 | 是 | my-bucket |
| Access Key | 访问密钥ID | 是 | AKIAIOSFODNN7EXAMPLE |
| Secret Key | 访问密钥 | 是 | wJalrXUtnFEMI... |
| Region | 区域代码 | 是 | us-east-1 |
| 自定义Endpoint | 兼容S3服务端点 | 否 | https://s3.example.com |

### 高级配置

| 配置项 | 说明 |
|--------|------|
| 使用Path Style | 某些兼容服务需要启用(MinIO等) |
| CDN域名 | 配置CDN加速域名 |
| 上传路径 | S3中的存储路径前缀 |
| ACL权限 | 文件访问权限 |

## 使用方法

### 方法1: 使用增强版上传函数(推荐)

在需要上传的地方引入增强版上传函数:

```php
// 引入插件上传函数
require ROOT_PATH . '/plugins/s3storage/upload_enhanced.php';

// 上传单文件,自动上传到本地+S3
$result = upload_with_s3('file', 'jpg,png,gif');

// 检查结果
if (empty($result['error'])) {
    echo "本地路径: " . $result['local'] . "\n";
    echo "S3 URL: " . $result['s3'] . "\n";
} else {
    echo "上传失败: " . $result['error'];
}
```

### 方法2: 使用简化版函数

```php
require ROOT_PATH . '/plugins/s3storage/upload_enhanced.php';

// 上传并直接返回URL(优先S3,否则本地)
$url = upload_to_s3_simple('file', 'jpg,png');

if ($url) {
    echo "文件URL: " . $url;
}
```

### 方法3: 批量上传

```php
require ROOT_PATH . '/plugins/s3storage/upload_enhanced.php';

// 批量上传
$urls = upload_to_s3_batch('files', 'jpg,png,gif');

foreach ($urls as $url) {
    echo $url . "\n";
}
```

### 方法4: 手动上传到S3

```php
require ROOT_PATH . '/plugins/s3storage/upload_s3.php';

// 已有本地文件,手动上传到S3
$result = upload_to_s3('/path/to/local/file.jpg', 'uploads/image.jpg');

if ($result['success']) {
    echo "S3 URL: " . $result['url'];
} else {
    echo "上传失败: " . $result['error'];
}
```

## 支持的存储服务

### AWS S3官方
- Endpoint: 留空
- Region: 选择对应区域(如 us-east-1)

### 阿里云OSS
- Endpoint: `https://oss-cn-hangzhou.aliyuncs.com` (根据地区调整)
- Access Key/Secret Key: 阿里云AccessKey
- Bucket: OSS存储桶名称

### 腾讯云COS
- Endpoint: `https://cos.ap-guangzhou.myqcloud.com` (根据地区调整)
- Access Key/Secret Key: 腾讯云SecretId/SecretKey

### 七牛云Kodo
- Endpoint: `https://s3-cn-east-1.qiniucs.com` (根据地区调整)
- Access Key/Secret Key: 七牛云AK/SK

### MinIO
- Endpoint: `https://minio.example.com` (你的MinIO服务器地址)
- ✅ 勾选"使用Path Style Endpoint"

### 其他兼容S3服务
- Endpoint: 对应服务的S3 API端点
- 根据服务要求选择是否启用Path Style

## 常见问题

### 1. 上传失败提示"AWS SDK未安装"

需要先安装AWS SDK:
```bash
composer require aws/aws-sdk-php
```

### 2. 连接测试失败

请检查:
- Access Key和Secret Key是否正确
- Bucket名称是否正确
- Region是否正确
- 网络是否可以访问S3服务
- 是否有足够的权限

### 3. 文件上传成功但无法访问

请检查:
- ACL权限是否设置为"公开读取"
- CDN域名是否配置正确
- 存储桶策略是否允许公开访问

### 4. 想要同时保留本地和S3

使用 `upload_with_s3()` 函数,它会同时保存到本地和S3:
- `$result['local']` - 本地路径
- `$result['s3']` - S3 URL

## 文件结构

```
plugins/s3storage/
├── plugin.json              # 插件配置文件
├── README.md                # 说明文档
├── S3Storage.php            # S3核心类
├── upload_s3.php            # S3扩展函数
├── upload_enhanced.php      # 增强版上传函数
└── admin/
    ├── ConfigController.php # 后台配置控制器
    └── view/
        └── config.html      # 配置页面
```

## 系统要求

- PHP >= 5.4
- PbootCMS 1.8.1+
- AWS SDK for PHP 3.x+

## 技术支持

如有问题,请检查:
1. PHP错误日志
2. PbootCMS后台日志
3. AWS/云服务控制台的请求日志

## License

MIT License

## 更新日志

### v1.0.0 (2025-03-13)
- 初始版本发布
- 支持AWS S3及兼容服务
- 后台配置界面
- 增强版上传函数
