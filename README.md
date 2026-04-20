# GTCodestar 企业官网

基于 PbootCMS 二次开发的企业官网系统。

## 技术栈

- PHP 7.0+（PbootCMS 内核，MVC 架构）
- MySQL 数据库
- LiteSpeed / Apache / Nginx
- Cloudflare R2 对象存储（静态文件）

## 目录结构

```
├── index.php              # 前台入口
├── admin.php              # 后台管理入口
├── api.php                # API 入口
├── r2.php                 # R2 存储迁移工具
├── config/
│   ├── config.php         # 系统配置
│   ├── database.php       # 数据库配置
│   └── route.php          # 路由配置
├── core/
│   ├── extend/
│   │   └── R2Storage.php  # Cloudflare R2 上传类（纯PHP，AWS V4签名）
│   ├── function/          # 系统函数库
│   └── ...                # 内核文件
├── apps/
│   ├── admin/             # 后台模块（内容、用户、系统管理）
│   ├── home/              # 前台模块（首页、文章、搜索等）
│   ├── api/               # API 模块
│   └── common/            # 公共模块
├── template/default/      # 前台模板
├── static/                # 静态资源
└── sql/                   # 数据库初始化文件
```

## 运行时与备份

- `runtime/` 为系统运行时缓存目录，已在 `.gitignore` 中忽略，不应提交到仓库。
- `backup/` 仅用于本地备份，已从仓库移除，并且也已列入 `.gitignore`。

## 部署

1. 新建数据库，导入 `sql/gtcodestar_en.sql`
2. 修改 `config/database.php` 填写数据库连接信息
3. 访问 `/admin.php` 登录后台，完成授权
4. 后台 → 全局配置 → 定制标签 → 设置 `app host` 为你的域名

## R2 对象存储

静态文件（图片、文档等）支持上传到 Cloudflare R2，无出口流量费。

### 配置

后台 → 全局配置 → 参数配置 → **R2存储** 标签页，填写：

| 配置项 | 说明 |
|--------|------|
| Account ID | Cloudflare 仪表盘 → R2 页面顶部 |
| Access Key ID | R2 API 令牌 |
| Secret Access Key | R2 API 令牌 |
| Bucket 名称 | R2 存储桶名称 |
| 自定义域名 | 如 `https://r2.example.com`（需带 https://） |
| 上传路径前缀 | 默认 `uploads` |

### 自动同步

配置启用后，后台上传的文件会自动同步到 R2。

### 一键迁移

访问 `/r2.php`（需先登录后台），可以：

1. **测试连接** — 验证 R2 配置
2. **上传文件** — 批量上传 `static/upload/` 到 R2（支持暂停/继续）
3. **替换链接** — 数据库中文件路径替换为 R2 CDN 域名
4. **对比文件** — 检查本地与 R2 文件一致性（大小对比）
5. **删除本地** — 仅删除已确认同步的文件，不一致的跳过保留

## 页面模块

首页 / 产品 / 新闻 / 案例 / 招聘 / 关于 / 联系 / 留言 / 搜索
