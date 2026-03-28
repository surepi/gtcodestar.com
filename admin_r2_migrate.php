<?php
/**
 * R2 一键迁移工具
 * 
 * 功能：
 * 1. 扫描 static/upload/ 下所有文件上传到 R2
 * 2. 替换数据库中所有 /static/upload/ 路径为 R2 CDN 域名
 * 
 * 访问方式：浏览器访问 /admin_r2_migrate.php?action=xxx
 * 需要先在后台配置好 R2 参数
 */

// 安全验证：必须登录后台才能使用
define('IS_INDEX', true);
define('URL_BIND', 'admin');

require dirname(__FILE__) . '/core/init.php';

// 简易 session 验证（检查是否已登录后台）
session_start();
if (empty($_SESSION['ucode'])) {
    die('<h2>请先登录后台再访问此页面</h2><p><a href="admin.php">去登录</a></p>');
}

require_once CORE_PATH . '/extend/R2Storage.php';

$r2 = new R2Storage();
$action = isset($_GET['action']) ? $_GET['action'] : '';
$uploadDir = ROOT_PATH . '/static/upload';

header('Content-Type: text/html; charset=utf-8');

// ========== 扫描文件 ==========
if ($action === 'scan') {
    $files = scanUploadFiles($uploadDir);
    echo json_encode([
        'total' => count($files),
        'size_mb' => round(array_sum(array_map('filesize', $files)) / 1024 / 1024, 1),
        'files' => array_map(function($f) use ($uploadDir) {
            return str_replace($uploadDir . '/', '', $f);
        }, $files)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ========== 测试 R2 连接 ==========
if ($action === 'test') {
    if (!$r2->isEnabled()) {
        die(json_encode(['success' => false, 'message' => 'R2未启用，请先在后台参数配置中填写R2配置']));
    }
    $result = $r2->testConnection();
    die(json_encode($result, JSON_UNESCAPED_UNICODE));
}

// ========== 执行迁移（AJAX 逐批上传）==========
if ($action === 'upload_batch') {
    header('Content-Type: application/json; charset=utf-8');
    
    if (!$r2->isEnabled()) {
        die(json_encode(['success' => false, 'message' => 'R2未启用']));
    }

    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;

    $files = scanUploadFiles($uploadDir);
    $total = count($files);
    $batch = array_slice($files, $offset, $limit);

    $results = [];
    $successCount = 0;

    foreach ($batch as $file) {
        $relativePath = str_replace(ROOT_PATH . '/static/upload/', '', $file);
        $result = $r2->upload($file, $relativePath);
        $results[] = [
            'file' => $relativePath,
            'success' => $result['success'],
            'url' => $result['url'] ?? '',
            'message' => $result['message'] ?? ''
        ];
        if ($result['success']) {
            $successCount++;
        }
    }

    echo json_encode([
        'total' => $total,
        'offset' => $offset,
        'processed' => count($batch),
        'success_count' => $successCount,
        'done' => ($offset + $limit) >= $total,
        'results' => $results
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ========== 替换数据库链接 ==========
if ($action === 'replace_db') {
    header('Content-Type: application/json; charset=utf-8');

    if (!$r2->isEnabled()) {
        die(json_encode(['success' => false, 'message' => 'R2未启用']));
    }

    $dbConfig = require ROOT_PATH . '/config/database.php';
    $db = $dbConfig['database'];
    $pdo = new PDO(
        "mysql:host={$db['host']};port={$db['port']};dbname={$db['dbname']};charset=utf8",
        $db['user'],
        $db['passwd']
    );

    // 读取 R2 配置获取域名
    $customDomain = '';
    $stmt = $pdo->query("SELECT value FROM ay_config WHERE name = 'r2_custom_domain'");
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $customDomain = rtrim($row['value'], '/');
    }
    
    if (empty($customDomain)) {
        $stmt = $pdo->query("SELECT value FROM ay_config WHERE name = 'r2_account_id'");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $customDomain = 'https://pub-' . ($row['value'] ?? '') . '.r2.dev';
    }

    $uploadPrefix = '';
    $stmt = $pdo->query("SELECT value FROM ay_config WHERE name = 'r2_upload_path'");
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $uploadPrefix = trim($row['value'] ?: 'uploads', '/');
    }

    // /static/upload/image/xxx → {customDomain}/{uploadPrefix}/image/xxx
    $oldPrefix = '/static/upload/';
    $newPrefix = $customDomain . '/' . $uploadPrefix . '/';

    $replaced = [];

    // ay_content.ico
    $stmt = $pdo->prepare("UPDATE ay_content SET ico = REPLACE(ico, ?, ?) WHERE ico LIKE ?");
    $stmt->execute([$oldPrefix, $newPrefix, '%' . $oldPrefix . '%']);
    $replaced['ay_content.ico'] = $stmt->rowCount();

    // ay_content.content (HTML富文本)
    $stmt = $pdo->prepare("UPDATE ay_content SET content = REPLACE(content, ?, ?) WHERE content LIKE ?");
    $stmt->execute([$oldPrefix, $newPrefix, '%' . $oldPrefix . '%']);
    $replaced['ay_content.content'] = $stmt->rowCount();

    // ay_content.pics
    $stmt = $pdo->prepare("UPDATE ay_content SET pics = REPLACE(pics, ?, ?) WHERE pics LIKE ?");
    $stmt->execute([$oldPrefix, $newPrefix, '%' . $oldPrefix . '%']);
    $replaced['ay_content.pics'] = $stmt->rowCount();

    // ay_slide.pic
    $stmt = $pdo->prepare("UPDATE ay_slide SET pic = REPLACE(pic, ?, ?) WHERE pic LIKE ?");
    $stmt->execute([$oldPrefix, $newPrefix, '%' . $oldPrefix . '%']);
    $replaced['ay_slide.pic'] = $stmt->rowCount();

    // ay_content_sort.ico / pic
    $stmt = $pdo->prepare("UPDATE ay_content_sort SET ico = REPLACE(ico, ?, ?) WHERE ico LIKE ?");
    $stmt->execute([$oldPrefix, $newPrefix, '%' . $oldPrefix . '%']);
    $replaced['ay_content_sort.ico'] = $stmt->rowCount();

    $stmt = $pdo->prepare("UPDATE ay_content_sort SET pic = REPLACE(pic, ?, ?) WHERE pic LIKE ?");
    $stmt->execute([$oldPrefix, $newPrefix, '%' . $oldPrefix . '%']);
    $replaced['ay_content_sort.pic'] = $stmt->rowCount();

    // ay_site.logo
    $stmt = $pdo->prepare("UPDATE ay_site SET logo = REPLACE(logo, ?, ?) WHERE logo LIKE ?");
    $stmt->execute([$oldPrefix, $newPrefix, '%' . $oldPrefix . '%']);
    $replaced['ay_site.logo'] = $stmt->rowCount();

    // ay_link.logo
    $stmt = $pdo->prepare("UPDATE ay_link SET logo = REPLACE(logo, ?, ?) WHERE logo LIKE ?");
    $stmt->execute([$oldPrefix, $newPrefix, '%' . $oldPrefix . '%']);
    $replaced['ay_link.logo'] = $stmt->rowCount();

    // ay_label.value
    $stmt = $pdo->prepare("UPDATE ay_label SET value = REPLACE(value, ?, ?) WHERE value LIKE ?");
    $stmt->execute([$oldPrefix, $newPrefix, '%' . $oldPrefix . '%']);
    $replaced['ay_label.value'] = $stmt->rowCount();

    echo json_encode([
        'success' => true,
        'old_prefix' => $oldPrefix,
        'new_prefix' => $newPrefix,
        'replaced' => $replaced,
        'total_rows' => array_sum($replaced)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ========== 默认：显示管理界面 ==========
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>R2 存储一键迁移</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f5f5; padding: 30px; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: #fff; border-radius: 8px; padding: 24px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,.1); }
        h1 { font-size: 24px; margin-bottom: 20px; color: #333; }
        h2 { font-size: 18px; margin-bottom: 12px; color: #555; }
        .btn { display: inline-block; padding: 10px 24px; border: none; border-radius: 6px; font-size: 14px; cursor: pointer; margin: 5px 5px 5px 0; color: #fff; }
        .btn-primary { background: #1890ff; }
        .btn-success { background: #52c41a; }
        .btn-danger { background: #ff4d4f; }
        .btn-warning { background: #faad14; color: #333; }
        .btn:disabled { opacity: .5; cursor: not-allowed; }
        .progress-bar { width: 100%; height: 24px; background: #e8e8e8; border-radius: 12px; overflow: hidden; margin: 12px 0; }
        .progress-fill { height: 100%; background: #1890ff; transition: width .3s; text-align: center; color: #fff; font-size: 12px; line-height: 24px; }
        .log { max-height: 300px; overflow-y: auto; background: #1e1e1e; color: #d4d4d4; padding: 12px; border-radius: 6px; font-family: monospace; font-size: 13px; line-height: 1.6; margin-top: 12px; }
        .log .ok { color: #52c41a; }
        .log .err { color: #ff4d4f; }
        .log .info { color: #1890ff; }
        .warn { padding: 12px; background: #fff7e6; border: 1px solid #ffe58f; border-radius: 6px; margin: 12px 0; color: #874d00; }
    </style>
</head>
<body>
<div class="container">
    <h1>☁️ R2 存储一键迁移</h1>

    <div class="card">
        <h2>第一步：测试 R2 连接</h2>
        <p>确保后台「参数配置 → R2存储」已正确填写。</p>
        <button class="btn btn-primary" onclick="testR2()">测试连接</button>
        <span id="test-result"></span>
    </div>

    <div class="card">
        <h2>第二步：上传文件到 R2</h2>
        <p>扫描 <code>static/upload/</code> 下所有文件并批量上传到 R2。</p>
        <button class="btn btn-success" id="btn-upload" onclick="startUpload()">开始上传</button>
        <button class="btn btn-warning" id="btn-stop" onclick="stopUpload()" style="display:none">暂停</button>
        <div class="progress-bar" id="upload-progress" style="display:none">
            <div class="progress-fill" id="upload-fill" style="width:0%">0%</div>
        </div>
        <div id="upload-stats"></div>
        <div class="log" id="upload-log" style="display:none"></div>
    </div>

    <div class="card">
        <h2>第三步：替换数据库链接</h2>
        <div class="warn">⚠️ 此操作将修改数据库，建议先备份！会将所有 <code>/static/upload/</code> 路径替换为 R2 CDN 域名。</div>
        <button class="btn btn-danger" id="btn-replace" onclick="replaceDb()">替换数据库链接</button>
        <div id="replace-result" style="margin-top:12px"></div>
    </div>

    <div class="card">
        <p><a href="admin.php">← 返回后台</a></p>
    </div>
</div>

<script>
var uploading = false;
var stopped = false;

function testR2() {
    document.getElementById('test-result').innerHTML = '测试中...';
    fetch('?action=test')
        .then(r => r.json())
        .then(d => {
            document.getElementById('test-result').innerHTML = d.success
                ? '<span style="color:#52c41a">✅ ' + d.message + '</span>'
                : '<span style="color:#ff4d4f">❌ ' + d.message + '</span>';
        })
        .catch(e => {
            document.getElementById('test-result').innerHTML = '<span style="color:#ff4d4f">请求失败</span>';
        });
}

function startUpload() {
    if (uploading) return;
    uploading = true;
    stopped = false;
    document.getElementById('btn-upload').disabled = true;
    document.getElementById('btn-stop').style.display = 'inline-block';
    document.getElementById('upload-progress').style.display = 'block';
    document.getElementById('upload-log').style.display = 'block';
    document.getElementById('upload-log').innerHTML = '';
    uploadBatch(0);
}

function stopUpload() {
    stopped = true;
    document.getElementById('btn-stop').style.display = 'none';
    document.getElementById('btn-upload').disabled = false;
    uploading = false;
    appendLog('⏸ 已暂停', 'info');
}

function uploadBatch(offset) {
    if (stopped) return;
    fetch('?action=upload_batch&offset=' + offset + '&limit=10')
        .then(r => r.json())
        .then(d => {
            var pct = Math.min(100, Math.round((offset + d.processed) / d.total * 100));
            document.getElementById('upload-fill').style.width = pct + '%';
            document.getElementById('upload-fill').textContent = pct + '%';
            document.getElementById('upload-stats').innerHTML =
                '已处理: ' + (offset + d.processed) + ' / ' + d.total +
                ' | 本批成功: ' + d.success_count + '/' + d.processed;

            d.results.forEach(function(r) {
                if (r.success) {
                    appendLog('✅ ' + r.file, 'ok');
                } else {
                    appendLog('❌ ' + r.file + ' — ' + r.message, 'err');
                }
            });

            if (!d.done && !stopped) {
                uploadBatch(offset + d.processed);
            } else if (d.done) {
                appendLog('🎉 全部上传完成！', 'info');
                uploading = false;
                document.getElementById('btn-upload').disabled = false;
                document.getElementById('btn-stop').style.display = 'none';
            }
        })
        .catch(function(e) {
            appendLog('❌ 请求失败: ' + e.message, 'err');
            uploading = false;
            document.getElementById('btn-upload').disabled = false;
        });
}

function replaceDb() {
    if (!confirm('确定要替换数据库中的文件链接吗？建议先备份数据库！')) return;
    document.getElementById('btn-replace').disabled = true;
    document.getElementById('replace-result').innerHTML = '正在替换...';

    fetch('?action=replace_db')
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                var html = '<p style="color:#52c41a">✅ 替换完成！共修改 ' + d.total_rows + ' 条记录</p>';
                html += '<p>旧前缀: <code>' + d.old_prefix + '</code></p>';
                html += '<p>新前缀: <code>' + d.new_prefix + '</code></p>';
                html += '<ul>';
                for (var k in d.replaced) {
                    if (d.replaced[k] > 0) {
                        html += '<li>' + k + ': ' + d.replaced[k] + ' 条</li>';
                    }
                }
                html += '</ul>';
                document.getElementById('replace-result').innerHTML = html;
            } else {
                document.getElementById('replace-result').innerHTML =
                    '<span style="color:#ff4d4f">❌ ' + d.message + '</span>';
            }
            document.getElementById('btn-replace').disabled = false;
        })
        .catch(function(e) {
            document.getElementById('replace-result').innerHTML = '<span style="color:#ff4d4f">请求失败</span>';
            document.getElementById('btn-replace').disabled = false;
        });
}

function appendLog(msg, cls) {
    var log = document.getElementById('upload-log');
    log.innerHTML += '<div class="' + (cls||'') + '">' + msg + '</div>';
    log.scrollTop = log.scrollHeight;
}
</script>
</body>
</html>
<?php

/**
 * 递归扫描上传目录
 */
function scanUploadFiles($dir) {
    $files = [];
    if (!is_dir($dir)) return $files;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $files[] = $file->getPathname();
        }
    }
    sort($files);
    return $files;
}
