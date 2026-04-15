<?php
/**
 * S3存储插件快速开始指南
 * 
 * 运行此文件查看快速使用说明
 */

echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S3存储插件 - 快速开始</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }
        h2 {
            color: #555;
            margin-top: 30px;
        }
        h3 {
            color: #666;
        }
        .step {
            background: #f9f9f9;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #4CAF50;
            border-radius: 3px;
        }
        .step-number {
            font-size: 24px;
            font-weight: bold;
            color: #4CAF50;
            margin-bottom: 10px;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        pre {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        pre code {
            background: none;
            padding: 0;
        }
        .success {
            color: #4CAF50;
            font-weight: bold;
        }
        .warning {
            color: #ff9800;
            font-weight: bold;
        }
        .error {
            color: #f44336;
            font-weight: bold;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 0;
        }
        .btn:hover {
            background: #45a049;
        }
        ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        li {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 S3存储插件 - 快速开始</h1>
        
        <p><strong>插件位置:</strong> <code>e:/2026-3-12/plugins/s3storage/</code></p>
        
        <div class="step">
            <div class="step-number">步骤 1: 安装 AWS SDK</div>
            <p>在项目根目录执行:</p>
            <pre><code>cd e:/2026-3-12
composer require aws/aws-sdk-php</code></pre>
            <p class="warning">如果没有composer,先下载composer.phar后执行:</p>
            <pre><code>php composer.phar require aws/aws-sdk-php</code></pre>
        </div>
        
        <div class="step">
            <div class="step-number">步骤 2: 配置插件</div>
            <p>访问后台配置页面:</p>
            <pre><code>http://yourdomain.com/admin.php?p=/plugin/s3storage/admin/Config/index</code></pre>
            <p>或直接打开配置文件编辑:</p>
            <pre><code>e:/2026-3-12/plugins/s3storage/plugin.json</code></pre>
        </div>
        
        <div class="step">
            <div class="step-number">步骤 3: 测试连接</div>
            <p>在后台配置页面点击"测试连接"按钮,确保配置正确。</p>
        </div>
        
        <div class="step">
            <div class="step-number">步骤 4: 开始使用</div>
            <p>在需要上传的PHP文件中引入插件:</p>
            <pre><code>&lt;?php
// 引入S3上传插件
require ROOT_PATH . '/plugins/s3storage/upload_enhanced.php';

// 上传文件(自动上传到本地+S3)
\$result = upload_with_s3('file', 'jpg,png,gif');

if (empty(\$result['error'])) {
    echo "本地路径: " . \$result['local'] . "\\n";
    echo "S3 URL: " . \$result['s3'] . "\\n";
    
    // 使用文件URL(优先S3)
    \$url = \$result['s3'] ?: get_http_url() . '/' . \$result['local'];
    echo "文件访问URL: " . \$url . "\\n";
} else {
    echo "上传失败: " . \$result['error'] . "\\n";
}
?&gt;</code></pre>
        </div>
        
        <h2>📝 快速使用示例</h2>
        
        <h3>示例1: 简单上传</h3>
        <pre><code>require ROOT_PATH . '/plugins/s3storage/upload_enhanced.php';
\$url = upload_to_s3_simple('image', 'jpg,png');
echo "文件URL: " . \$url;</code></pre>
        
        <h3>示例2: 批量上传</h3>
        <pre><code>require ROOT_PATH . '/plugins/s3storage/upload_enhanced.php';
\$urls = upload_to_s3_batch('images', 'jpg,png,gif');
foreach (\$urls as \$url) {
    echo \$url . "\\n";
}</code></pre>
        
        <h3>示例3: 头像上传</h3>
        <pre><code>require ROOT_PATH . '/plugins/s3storage/upload_enhanced.php';
\$result = upload_with_s3('avatar', 'jpg,png', 200, 200);
\$avatar_url = \$result['s3'] ?: get_http_url() . '/' . \$result['local'];</code></pre>
        
        <h2>⚙️ 支持的存储服务</h2>
        <ul>
            <li><strong>AWS S3官方</strong> - Endpoint留空,填写Region</li>
            <li><strong>阿里云OSS</strong> - Endpoint: https://oss-cn-hangzhou.aliyuncs.com</li>
            <li><strong>腾讯云COS</strong> - Endpoint: https://cos.ap-guangzhou.myqcloud.com</li>
            <li><strong>七牛云Kodo</strong> - Endpoint: https://s3-cn-east-1.qiniucs.com</li>
            <li><strong>MinIO</strong> - Endpoint: 你的服务器地址,勾选"使用Path Style"</li>
            <li><strong>其他S3兼容服务</strong> - 填写对应Endpoint</li>
        </ul>
        
        <h2>📚 更多资源</h2>
        <ul>
            <li><a href="README.md" target="_blank">📖 完整文档</a></li>
            <li><a href="INSTALL.md" target="_blank">🔧 安装指南</a></li>
            <li><a href="example.php" target="_blank">💡 使用示例</a></li>
        </ul>
        
        <h2>❓ 常见问题</h2>
        
        <h3>Q: 上传失败提示"AWS SDK未安装"?</h3>
        <p>A: 执行 <code>composer require aws/aws-sdk-php</code> 安装SDK</p>
        
        <h3>Q: 如何只使用S3,不保存到本地?</h3>
        <p>A: 参考example.php中的example7示例</p>
        
        <h3>Q: 如何迁移现有文件到S3?</h3>
        <p>A: 参考example.php中的example12示例</p>
        
        <h3>Q: 上传超时怎么办?</h3>
        <p>A: 在php.ini中增加upload_max_filesize、post_max_size、max_execution_time</p>
        
        <div class="step">
            <div class="step-number">✨ 特性</div>
            <ul>
                <li><span class="success">✓</span> 同时保存到本地和S3,双重保障</li>
                <li><span class="success">✓</span> S3上传失败不影响本地存储</li>
                <li><span class="success">✓</span> 支持所有S3兼容服务</li>
                <li><span class="success">✓</span> 支持CDN加速</li>
                <li><span class="success">✓</span> 支持自定义上传路径</li>
                <li><span class="success">✓</span> 提供连接测试功能</li>
                <li><span class="success">✓</span> 详细错误提示</li>
            </ul>
        </div>
        
        <p class="success">开始使用吧! 🎉</p>
    </div>
</body>
</html>
HTML;
