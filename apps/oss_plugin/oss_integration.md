# OSS插件集成到PbootCMS的详细步骤

本文档说明如何将OSS插件集成到PbootCMS系统中。

## 步骤1：修改 core/function/file.php

### 1.1 在文件开头引入OSS上传类

在 `core/function/file.php` 文件开头添加：

```php
// 引入OSS上传类
require_once APP_PATH . '/oss_plugin/OssUpload.php';
```

### 1.2 修改 handle_upload 函数

找到 `handle_upload` 函数，将其替换为以下代码：

```php
// 处理并移动上传文件
function handle_upload($file, $temp, $array_ext_allow, $max_width, $max_height, $watermark)
{
    // 从数据库读取OSS配置
    $ossEnabled = Config::get('oss_enabled', 'db');
    $enableOss = $ossEnabled == '1' && class_exists('OssUpload');

    // 定义主存储路径（本地或临时目录）
    if ($enableOss) {
        $save_path = ROOT_PATH . '/runtime/temp_oss';
    } else {
        $save_path = DOC_PATH . STATIC_DIR . '/upload';
    }

    $file = explode('.', $file); // 分离文件名及扩展
    $file_ext = strtolower(end($file)); // 获取扩展

    if (! in_array($file_ext, $array_ext_allow)) {
        return $file_ext . '格式的文件不允许上传！';
    }

    // 文件扩展黑名单
    $black = array(
        'php',
        'jsp',
        'asp',
        'vb',
        'exe',
        'sh',
        'cmd',
        'bat',
        'vbs',
        'phtml',
        'class',
        'php2',
        'php3',
        'php4',
        'php5'
    );

    if (in_array($file_ext, $black)) {
        return $file_ext . '格式的文件不允许上传！';
    }

    $image = array(
        'png',
        'jpg',
        'gif',
        'bmp'
    );
    $file = array(
        'ppt',
        'pptx',
        'xls',
        'xlsx',
        'doc',
        'docx',
        'pdf',
        'txt'
    );
    if (in_array($file_ext, $image)) {
        $file_type = 'image';
    } elseif (in_array($file_ext, $file)) {
        $file_type = 'file';
    } else {
        $file_type = 'other';
    }

    // 检查文件存储路径
    if (! check_dir($save_path . '/' . $file_type . '/' . date('Ymd'), true)) {
        return '存储目录创建失败！';
    }

    // 生成文件名
    $filename = time() . mt_rand(100000, 999999) . '.' . $file_ext;
    $file_path = $save_path . '/' . $file_type . '/' . date('Ymd') . '/' . $filename;

    if (! move_uploaded_file($temp, $file_path)) { // 从缓存中转存
        return '从缓存中转存失败！';
    }

    // 如果是图片，进行等比例缩放和水印
    if (is_image($file_path)) {
        // 进行等比例缩放
        if (($reset = resize_img($file_path, $file_path, $max_width, $max_height)) !== true) {
            return $reset;
        }
        // 图片打水印
        if ($watermark) {
            watermark_img($file_path);
        }
    }

    // 如果启用OSS，上传到OSS并删除本地文件
    if ($enableOss) {
        try {
            // 从数据库读取OSS配置
            $ossConfig = array(
                'enabled' => true,
                'provider' => Config::get('oss_provider', 'db'),
                'access_key_id' => Config::get('oss_access_key_id', 'db'),
                'access_key_secret' => Config::get('oss_access_key_secret', 'db'),
                'endpoint' => Config::get('oss_endpoint', 'db'),
                'bucket' => Config::get('oss_bucket', 'db'),
                'custom_domain' => Config::get('oss_custom_domain', 'db'),
                'upload_path' => Config::get('oss_upload_path', 'db') ?: 'upload',
                'use_path_style' => Config::get('oss_use_path_style', 'db') == '1',
                'acl' => Config::get('oss_acl', 'db') ?: 'public-read',
                'cache_control' => 'max-age=31536000',
            );

            $oss = new OssUpload($ossConfig);

            // 构建OSS对象路径
            $objectPath = $file_type . '/' . date('Ymd') . '/' . $filename;

            // 上传到OSS
            $result = $oss->uploadFile($file_path, $objectPath);

            if ($result['success']) {
                // 删除本地临时文件
                @unlink($file_path);
                return $result['url']; // 返回OSS URL
            } else {
                // OSS上传失败，保留本地文件
                error_log('OSS上传失败: ' . $result['message']);
                $save_file = str_replace(ROOT_PATH, '', $file_path); // 获取文件站点路径
                return $save_file;
            }
        } catch (Exception $e) {
            // OSS异常，保留本地文件并记录错误
            error_log('OSS上传异常: ' . $e->getMessage());
            $save_file = str_replace(ROOT_PATH, '', $file_path); // 获取文件站点路径
            return $save_file;
        }
    }

    // 本地存储模式
    $save_file = str_replace(ROOT_PATH, '', $file_path); // 获取文件站点路径
    return $save_file;
}
```

## 步骤2：修改 apps/admin/view/default/system/config.html

### 2.1 添加配置标签

在 `<ul class="layui-tab-title">` 中添加：

```html
<li lay-id="t11">OSS云存储配置</li>
```

### 2.2 添加配置表单

在 `<div class="layui-tab-content">` 的末尾、`</div>` 之前添加：

参考 `admin_config.html` 文件中的完整代码。

### 2.3 在控制器中添加处理逻辑

在 `apps/admin/controller/system/ConfigController.php` 的 switch 语句中添加：

```php
case 'oss':
    success('修改成功！', url('/admin/Config/index' . get_tab('t11'), false));
    break;
```

## 步骤3：创建临时目录

```bash
mkdir -p runtime/temp_oss
chmod 777 runtime/temp_oss
```

## 步骤4：执行数据库安装

在数据库中执行 `install.sql` 文件：

```bash
mysql -u用户名 -p 数据库名 < apps/oss_plugin/install.sql
```

或在phpMyAdmin等工具中导入该文件。

## 步骤5：清除缓存

清除PbootCMS的配置缓存：

1. 登录后台
2. 进入"系统管理"
3. 点击"清除缓存"

或手动删除：

```bash
rm -rf runtime/cache/config
```

## 步骤6：测试

1. 访问后台：`http://你的域名/admin.php`
2. 进入"系统管理" → "系统配置" → "OSS云存储配置"
3. 填写配置信息并保存
4. 上传一张图片测试
5. 检查文件URL是否为OSS域名

## 恢复原状（卸载插件）

### 1. 执行卸载脚本

```bash
mysql -u用户名 -p 数据库名 < apps/oss_plugin/uninstall.sql
```

### 2. 恢复文件修改

1. 恢复 `core/function/file.php` 的修改
2. 恢复 `apps/admin/view/default/system/config.html` 的修改
3. 恢复 `apps/admin/controller/system/ConfigController.php` 的修改

### 3. 删除插件目录

```bash
rm -rf apps/oss_plugin
```

### 4. 清除缓存

同上，清除PbootCMS缓存。

## 注意事项

1. **备份数据库**：执行安装/卸载SQL前务必备份
2. **备份文件**：修改核心文件前先备份
3. **测试环境**：建议先在测试环境验证
4. **CORS配置**：务必在OSS控制台配置CORS规则
5. **权限设置**：确保 `runtime/temp_oss` 目录可写

## 故障排查

### 问题1：找不到类 OssUpload
**解决**：检查 `core/function/file.php` 是否正确引入了OSS类

### 问题2：上传失败，提示OSS未启用
**解决**：检查数据库中 `oss_enabled` 字段是否为 '1'

### 问题3：签名错误
**解决**：检查AccessKey ID和Secret是否正确，确认Endpoint和Bucket匹配

### 问题4：上传成功但无法访问
**解决**：确认文件权限为 `public-read`，检查Bucket读写权限

### 问题5：配置页面显示异常
**解决**：清除浏览器缓存和PbootCMS缓存
