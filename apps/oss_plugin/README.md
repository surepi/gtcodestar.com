# PbootCMS OSS云存储插件

## 插件简介

这是一个为PbootCMS开发的OSS云存储插件，支持将网站上传的文件自动存储到阿里云OSS、腾讯云COS、AWS S3等云存储平台。

## 功能特点

- ✅ 支持多种云存储：阿里云OSS、腾讯云COS、AWS S3、MinIO
- ✅ 后台可视化配置，无需编辑代码
- ✅ 自动检测，智能切换（OSS失败自动降级到本地）
- ✅ 支持CDN加速域名
- ✅ 保留原图处理功能（缩放、水印）
- ✅ 标准插件格式，易于安装和卸载

## 系统要求

- PbootCMS 3.0 或更高版本
- PHP 7.0 或更高版本
- PHP CURL扩展

## 安装方法

### 方法一：自动安装（推荐）

1. 将 `oss_plugin` 文件夹上传到 `/apps/` 目录
2. 在浏览器访问安装脚本：
   ```
   http://你的域名/index.php/?s=/oss/install
   ```
3. 根据提示完成安装

### 方法二：手动安装

1. 将 `apps/oss_plugin` 文件夹复制到你的PbootCMS的 `/apps/` 目录
2. 在数据库中执行 `install.sql` 文件
3. 修改 `/core/function/file.php`，在文件开头添加：
   ```php
   // 引入OSS上传类
   require_once APP_PATH . '/oss_plugin/OssUpload.php';
   ```
4. 在 `handle_upload` 函数中添加OSS上传逻辑（参考 `oss_integration.md`）
5. 在后台配置页面添加OSS配置项（参考 `admin_config.html`）

## 配置说明

### 1. 访问后台配置

登录后台后进入：**系统管理** → **系统配置** → **OSS云存储配置**

### 2. 填写配置信息

#### 阿里云OSS配置示例：
```
启用OSS上传: ✓ 启用
OSS服务商: 阿里云OSS
AccessKey ID: LTAI5txxxxxxxx
AccessKey Secret: xxxxxxxxxxxxx
Endpoint: https://oss-cn-hangzhou.aliyuncs.com
Bucket名称: my-bucket
自定义域名: （可选）
上传路径前缀: upload
文件权限: public-read
```

#### 腾讯云COS配置示例：
```
启用OSS上传: ✓ 启用
OSS服务商: 腾讯云COS
AccessKey ID: AKIDxxxxxxx
AccessKey Secret: xxxxxxxxxxxxx
Endpoint: https://cos.ap-guangzhou.myqcloud.com
Bucket名称: my-bucket-1234567890
自定义域名: （可选）
上传路径前缀: upload
文件权限: public-read
```

### 3. CORS配置

在OSS控制台配置CORS规则：

**阿里云OSS**：
- 来源：`*`
- 方法：`GET, POST, PUT, DELETE, HEAD`
- 头部：`*`
- 缓存时间：`600`

**腾讯云COS**：
- 来源：`*`
- 方法：`GET, POST, PUT, DELETE, HEAD`
- 头部：`*`
- 超时时间：`600`

## 使用说明

### 启用OSS上传

1. 登录后台
2. 进入系统配置 → OSS云存储配置
3. 勾选"启用OSS上传"
4. 填写相关配置信息
5. 点击"立即提交"

### 测试上传

在后台上传一张图片或文件：
- ✅ 成功：文件URL显示为OSS域名
- ❌ 失败：查看 `runtime/log/` 错误日志

### 禁用OSS上传

如需切换回本地存储，只需在后台取消"启用OSS上传"即可。

## 卸载方法

### 自动卸载

1. 访问卸载脚本：
   ```
   http://你的域名/index.php/?s=/oss/uninstall
   ```
2. 确认卸载

### 手动卸载

1. 在数据库中执行 `uninstall.sql` 文件
2. 删除 `/apps/oss_plugin` 目录
3. 恢复 `/core/function/file.php` 的修改
4. 删除后台配置页面的OSS配置项

## 插件文件说明

```
oss_plugin/
├── OssUpload.php          # OSS上传核心类
├── config.php             # 插件配置文件
├── install.sql            # 安装脚本（数据库）
├── uninstall.sql          # 卸载脚本（数据库）
├── admin_config.html      # 后台配置页面（HTML片段）
├── oss_integration.md     # 集成说明文档
└── README.md             # 插件说明文档（本文件）
```

## 技术特点

### 1. 纯PHP实现
- 无需安装任何第三方SDK
- 使用原生CURL实现S3协议
- 轻量高效

### 2. S3兼容
- 支持所有S3兼容的存储服务
- 标准化接口
- 易于扩展

### 3. 智能降级
- OSS上传失败自动保留本地文件
- 不影响网站正常运行
- 详细的错误日志

### 4. 安全可靠
- AWS Signature V2签名算法
- 支持HTTPS加密传输
- 配置信息存储在数据库

## 常见问题

### Q1: 上传失败，提示签名错误
**A**: 检查AccessKey ID和Secret是否正确，确认Bucket名称和Endpoint匹配。

### Q2: 上传成功但无法访问文件
**A**: 确认文件权限设置为`public-read`，检查Bucket的读写权限。

### Q3: 插件安装后找不到配置页面
**A**: 确认已成功执行`install.sql`，清除浏览器缓存。

### Q4: 能否同时使用OSS和本地存储？
**A**: 可以。插件会根据配置自动选择，OSS失败时会自动使用本地存储。

### Q5: 支持哪些文件类型？
**A**: 支持PbootCMS允许的所有文件类型（图片、文档、视频等）。

## 更新日志

### v1.0.0 (2025-03-12)
- 初始版本发布
- 支持阿里云OSS、腾讯云COS、AWS S3、MinIO
- 后台可视化配置
- 自动降级机制

## 免责声明

本插件免费开源，仅供学习和个人使用。使用本插件产生的一切后果由使用者自行承担，开发者不承担任何责任。

## 技术支持

- 阿里云OSS文档：https://help.aliyun.com/product/31815.html
- 腾讯云COS文档：https://cloud.tencent.com/document/product/436
- AWS S3文档：https://docs.aws.amazon.com/s3/

## 开源协议

MIT License

## 致谢

感谢PbootCMS官方提供的优秀框架。
