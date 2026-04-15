# PbootCMS OSS云存储插件 - 打包说明

## 插件目录结构

```
oss_plugin/
├── README.md                # 插件说明文档
├── config.php              # 插件配置文件
├── install.sql            # 安装SQL脚本
├── uninstall.sql          # 卸载SQL脚本
├── OssUpload.php          # OSS上传核心类
├── admin_config.html      # 后台配置HTML片段
├── oss_integration.md     # 集成说明文档
└── PACKAGE_INSTRUCTIONS.md # 本文件（打包说明）
```

## 打包为zip文件

### Windows系统

使用WinRAR或7-Zip：
1. 右键点击 `oss_plugin` 文件夹
2. 选择"添加到压缩文件"
3. 格式选择 ZIP
4. 命名为：`pbootcms-oss-plugin-v1.0.0.zip`

### Linux/Mac系统

```bash
cd apps/
zip -r pbootcms-oss-plugin-v1.0.0.zip oss_plugin/
```

## 发布到插件市场

### 1. 准备发布材料

**必填信息**：
- 插件名称：PbootCMS OSS云存储插件
- 插件版本：v1.0.0
- 插件分类：系统扩展
- 功能简介：支持阿里云OSS、腾讯云COS、AWS S3等云存储
- 兼容版本：PbootCMS 3.0.0+
- PHP版本要求：7.0+
- 开源协议：MIT

**可选信息**：
- 插件截图：后台配置界面截图
- 演示地址：使用插件后的演示网站
- 演视频：安装和配置视频教程

### 2. 插件市场发布平台

推荐发布平台：
- PbootCMS官方论坛
- CSDN插件市场
- GitHub开源仓库
- Gitee开源仓库

### 3. 发布内容模板

```
## PbootCMS OSS云存储插件

### 插件简介
这是一个为PbootCMS开发的云存储插件，支持将网站上传的文件自动存储到阿里云OSS、腾讯云COS、AWS S3等云存储平台。

### 功能特点
✅ 支持多种云存储：阿里云OSS、腾讯云COS、AWS S3、MinIO
✅ 后台可视化配置，无需编辑代码
✅ 自动检测，智能切换（OSS失败自动降级到本地）
✅ 支持CDN加速域名
✅ 保留原图处理功能（缩放、水印）

### 系统要求
- PbootCMS 3.0 或更高版本
- PHP 7.0 或更高版本
- PHP CURL扩展

### 安装方法
1. 将 oss_plugin 文件夹上传到 /apps/ 目录
2. 在数据库中执行 install.sql
3. 按照 oss_integration.md 的说明集成到系统

### 下载地址
[下载链接]

### 技术支持
- 插件文档：README.md
- 集成文档：oss_integration.md
- 问题反馈：[您的联系方式]
```

## 版本管理

### 版本号规则

采用语义化版本号：`主版本号.次版本号.修订号`

- `主版本号`：不兼容的API修改
- `次版本号`：向下兼容的功能性新增
- `修订号`：向下兼容的问题修正

### 更新日志格式

```markdown
## v1.0.0 (2025-03-12)
### 新增
- 支持阿里云OSS、腾讯云COS、AWS S3、MinIO
- 后台可视化配置
- 自动降级机制

### 修复
- 修复文件上传失败时的错误处理

### 优化
- 优化上传性能
```

## 用户反馈收集

### 反馈渠道
- GitHub Issues
- 插件市场评论区
- 邮件：您的邮箱
- QQ群：群号

### 问题分类
1. 安装问题
2. 配置问题
3. 使用问题
4. Bug反馈
5. 功能建议

### 反馈模板

```
**PbootCMS版本**：
**PHP版本**：
**服务器环境**：Apache/Nginx
**问题描述**：
**错误信息**：
**复现步骤**：
```

## 版权声明

### MIT License

Copyright (c) 2025 [您的名字]

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

### 致谢

- 感谢PbootCMS官方提供的优秀框架
- 感谢所有反馈建议的用户

## 后续计划

### v1.1.0 计划功能
- [ ] 支持图片压缩
- [ ] 支持视频上传
- [ ] 添加上传日志查看
- [ ] 支持批量上传

### v1.2.0 计划功能
- [ ] 支持七牛云
- [ ] 支持又拍云
- [ ] 添加文件管理界面

### v2.0.0 计划功能
- [ ] 开发自动安装脚本
- [ ] 开发插件管理界面
- [ ] 支持插件热更新

## 联系方式

- 作者：[您的名字]
- 邮箱：your@email.com
- 个人网站：https://yourwebsite.com
- GitHub：https://github.com/yourusername
