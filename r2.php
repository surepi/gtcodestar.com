<?php
/**
 * R2 一键迁移工具
 * 
 * 功能：
 * 1. 扫描 static/upload/ 下所有文件上传到 R2
 * 2. 替换数据库中所有 /static/upload/ 路径为 R2 CDN 域名
 * 3. 对比本地与 R2 文件，确认一致后删除本地文件
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
$dir = isset($_GET['dir']) ? $_GET['dir'] : 'upload';

// 支持两个目录
$dirMap = [
    'upload' => ['path' => ROOT_PATH . '/static/upload', 'prefix' => ''],
    'images' => ['path' => ROOT_PATH . '/static/images', 'prefix' => 'images/'],
];
if (!isset($dirMap[$dir])) $dir = 'upload';
$uploadDir = $dirMap[$dir]['path'];
$r2Prefix = $dirMap[$dir]['prefix'];

header('Content-Type: text/html; charset=utf-8');

// ========== 扫描文件 ==========
if ($action === 'scan') {
    $files = scanUploadFiles($uploadDir);
    echo json_encode([
        'total' => count($files),
        'size_mb' => round(array_sum(array_map('filesize', $files)) / 1024 / 1024, 1),
        'dir' => $dir,
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
        $relativePath = str_replace($uploadDir . '/', '', $file);
        $r2ObjectName = $r2Prefix . $relativePath;
        $localSize = filesize($file);

        // 检查 R2 上是否已存在且大小一致，一致则跳过
        $head = $r2->headObject($r2ObjectName);
        if ($head['exists'] && $head['size'] == $localSize) {
            $results[] = [
                'file' => $relativePath,
                'success' => true,
                'url' => '',
                'message' => '已存在，跳过'
            ];
            $successCount++;
            continue;
        }

        $result = $r2->upload($file, $r2ObjectName);
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
        $customDomain = trim($row['value']);
        if ($customDomain && !preg_match('#^https?://#i', $customDomain)) {
            $customDomain = 'https://' . $customDomain;
        }
        $customDomain = rtrim($customDomain, '/');
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

    // ===== 同时替换 /static/images/ =====
    $oldPrefix2 = '/static/images/';
    $newPrefix2 = $customDomain . '/' . $uploadPrefix . '/images/';

    $tables2 = [
        ['ay_content', 'ico'],
        ['ay_content', 'content'],
        ['ay_content', 'pics'],
        ['ay_slide', 'pic'],
        ['ay_content_sort', 'ico'],
        ['ay_content_sort', 'pic'],
        ['ay_content_sort', 'def1'],
        ['ay_content_sort', 'def2'],
        ['ay_content_sort', 'def3'],
        ['ay_site', 'logo'],
        ['ay_link', 'logo'],
        ['ay_label', 'value'],
    ];

    foreach ($tables2 as $item) {
        $table = $item[0];
        $col = $item[1];
        $stmt = $pdo->prepare("UPDATE {$table} SET {$col} = REPLACE({$col}, ?, ?) WHERE {$col} LIKE ?");
        $stmt->execute([$oldPrefix2, $newPrefix2, '%' . $oldPrefix2 . '%']);
        $cnt = $stmt->rowCount();
        if ($cnt > 0) {
            $key = "{$table}.{$col}(images)";
            $replaced[$key] = $cnt;
        }
    }

    // 清理所有表中 R2 URL 里的双斜杠（排除 https://）
    $cleanTables = [
        ['ay_content', 'ico'],
        ['ay_content', 'content'],
        ['ay_content', 'pics'],
        ['ay_slide', 'pic'],
        ['ay_content_sort', 'ico'],
        ['ay_content_sort', 'pic'],
        ['ay_site', 'logo'],
        ['ay_link', 'logo'],
    ];
    $cleanCount = 0;
    foreach ($cleanTables as $item) {
        $table = $item[0];
        $col = $item[1];
        // 把 https://xxx.com//path 中间的 // 替换成 /（但保留 https:// 的 //）
        $stmt = $pdo->prepare("UPDATE {$table} SET {$col} = REPLACE({$col}, '://', ':##TEMP##') WHERE {$col} LIKE ?");
        $stmt->execute(['%' . $customDomain . '%']);
        $stmt = $pdo->prepare("UPDATE {$table} SET {$col} = REPLACE({$col}, '//', '/') WHERE {$col} LIKE ?");
        $stmt->execute(['%' . str_replace('://', ':##TEMP##', $customDomain) . '%']);
        $stmt = $pdo->prepare("UPDATE {$table} SET {$col} = REPLACE({$col}, ':##TEMP##', '://') WHERE {$col} LIKE ?");
        $stmt->execute(['%##TEMP##%']);
        $cleanCount += $stmt->rowCount();
    }
    if ($cleanCount > 0) {
        $replaced['清理双斜杠'] = $cleanCount;
    }

    echo json_encode([
        'success' => true,
        'old_prefix' => $oldPrefix,
        'new_prefix' => $newPrefix,
        'replaced' => $replaced,
        'total_rows' => array_sum($replaced)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ========== 对比本地与 R2 文件 ==========
if ($action === 'compare_batch') {
    header('Content-Type: application/json; charset=utf-8');

    if (!$r2->isEnabled()) {
        die(json_encode(['success' => false, 'message' => 'R2未启用']));
    }

    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;

    $files = scanUploadFiles($uploadDir);
    $total = count($files);
    $batch = array_slice($files, $offset, $limit);

    $results = [];
    $matchCount = 0;
    $missCount = 0;

    foreach ($batch as $file) {
        $relativePath = str_replace($uploadDir . '/', '', $file);
        $r2ObjectName = $r2Prefix . $relativePath;
        $localSize = filesize($file);

        // 用 HEAD 请求检查 R2 上是否存在且大小一致
        $r2Check = $r2->headObject($r2ObjectName);

        if ($r2Check['exists'] && $r2Check['size'] == $localSize) {
            $results[] = [
                'file' => $relativePath,
                'status' => 'match',
                'local_size' => $localSize,
                'r2_size' => $r2Check['size']
            ];
            $matchCount++;
        } elseif ($r2Check['exists']) {
            $results[] = [
                'file' => $relativePath,
                'status' => 'size_mismatch',
                'local_size' => $localSize,
                'r2_size' => $r2Check['size']
            ];
            $missCount++;
        } else {
            $results[] = [
                'file' => $relativePath,
                'status' => 'missing',
                'local_size' => $localSize,
                'r2_size' => 0
            ];
            $missCount++;
        }
    }

    echo json_encode([
        'total' => $total,
        'offset' => $offset,
        'processed' => count($batch),
        'match_count' => $matchCount,
        'miss_count' => $missCount,
        'done' => ($offset + $limit) >= $total,
        'results' => $results
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ========== 删除已同步的本地文件 ==========
if ($action === 'delete_batch') {
    header('Content-Type: application/json; charset=utf-8');

    if (!$r2->isEnabled()) {
        die(json_encode(['success' => false, 'message' => 'R2未启用']));
    }

    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;

    $files = scanUploadFiles($uploadDir);
    $total = count($files);
    // 注意：每删一批文件列表会变，所以每次都从头扫描，取前 limit 个
    $batch = array_slice($files, 0, $limit);

    $results = [];
    $deletedCount = 0;
    $skippedCount = 0;

    foreach ($batch as $file) {
        $relativePath = str_replace($uploadDir . '/', '', $file);
        $r2ObjectName = $r2Prefix . $relativePath;
        $localSize = filesize($file);

        // 先确认 R2 上存在且大小一致
        $r2Check = $r2->headObject($r2ObjectName);

        if ($r2Check['exists'] && $r2Check['size'] == $localSize) {
            // 安全删除本地文件
            if (unlink($file)) {
                $results[] = ['file' => $relativePath, 'status' => 'deleted'];
                $deletedCount++;
            } else {
                $results[] = ['file' => $relativePath, 'status' => 'delete_failed'];
                $skippedCount++;
            }
        } else {
            $results[] = [
                'file' => $relativePath,
                'status' => 'skipped',
                'reason' => $r2Check['exists'] ? '大小不一致' : 'R2上不存在'
            ];
            $skippedCount++;
        }
    }

    // 删除后清理空目录
    cleanEmptyDirs($uploadDir);

    // 重新计算剩余文件
    $remaining = count(scanUploadFiles($uploadDir));

    echo json_encode([
        'total_before' => $total,
        'processed' => count($batch),
        'deleted' => $deletedCount,
        'skipped' => $skippedCount,
        'remaining' => $remaining,
        'done' => $remaining == 0 || ($deletedCount == 0 && $skippedCount > 0),
        'results' => $results
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
        .btn-dark { background: #333; }
        .btn:disabled { opacity: .5; cursor: not-allowed; }
        .progress-bar { width: 100%; height: 24px; background: #e8e8e8; border-radius: 12px; overflow: hidden; margin: 12px 0; }
        .progress-fill { height: 100%; transition: width .3s; text-align: center; color: #fff; font-size: 12px; line-height: 24px; }
        .progress-fill.blue { background: #1890ff; }
        .progress-fill.green { background: #52c41a; }
        .progress-fill.orange { background: #fa8c16; }
        .progress-fill.red { background: #ff4d4f; }
        .log { max-height: 300px; overflow-y: auto; background: #1e1e1e; color: #d4d4d4; padding: 12px; border-radius: 6px; font-family: monospace; font-size: 13px; line-height: 1.6; margin-top: 12px; }
        .log .ok { color: #52c41a; }
        .log .err { color: #ff4d4f; }
        .log .info { color: #1890ff; }
        .log .warn { color: #faad14; }
        .warn-box { padding: 12px; background: #fff7e6; border: 1px solid #ffe58f; border-radius: 6px; margin: 12px 0; color: #874d00; }
        .danger-box { padding: 12px; background: #fff1f0; border: 1px solid #ffa39e; border-radius: 6px; margin: 12px 0; color: #a8071a; }
        .stats { margin: 8px 0; font-size: 14px; color: #666; }
    </style>
</head>
<body>
<div class="container">
    <h1>☁️ R2 存储一键迁移</h1>

    <div class="card">
        <h2>选择目录</h2>
        <div style="margin-bottom:8px">
            <label><input type="radio" name="dir" value="upload" checked onchange="switchDir(this.value)"> <code>static/upload/</code>（上传文件）</label>
            &nbsp;&nbsp;
            <label><input type="radio" name="dir" value="images" onchange="switchDir(this.value)"> <code>static/images/</code>（模板图片）</label>
        </div>
        <div class="stats" id="dir-info"></div>
    </div>

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
            <div class="progress-fill blue" id="upload-fill" style="width:0%">0%</div>
        </div>
        <div class="stats" id="upload-stats"></div>
        <div class="log" id="upload-log" style="display:none"></div>
    </div>

    <div class="card">
        <h2>第三步：替换数据库链接</h2>
        <div class="warn-box">⚠️ 此操作将修改数据库，建议先备份！会将所有 <code>/static/upload/</code> 路径替换为 R2 CDN 域名。</div>
        <button class="btn btn-danger" id="btn-replace" onclick="replaceDb()">替换数据库链接</button>
        <div id="replace-result" style="margin-top:12px"></div>
    </div>

    <div class="card">
        <h2>第四步：对比文件</h2>
        <p>逐个检查本地文件是否已在 R2 上存在，且文件大小一致。</p>
        <button class="btn btn-primary" id="btn-compare" onclick="startCompare()">开始对比</button>
        <button class="btn btn-warning" id="btn-compare-stop" onclick="stopCompare()" style="display:none">暂停</button>
        <div class="progress-bar" id="compare-progress" style="display:none">
            <div class="progress-fill green" id="compare-fill" style="width:0%">0%</div>
        </div>
        <div class="stats" id="compare-stats"></div>
        <div class="log" id="compare-log" style="display:none"></div>
    </div>

    <div class="card">
        <h2>第五步：删除本地文件</h2>
        <div class="danger-box">🗑️ <strong>危险操作！</strong>只会删除 R2 上已存在且大小一致的本地文件。不一致或 R2 上不存在的文件会被跳过保留。<br>建议先完成第四步对比确认无误后再操作。</div>
        <button class="btn btn-dark" id="btn-delete" onclick="startDelete()">删除已同步的本地文件</button>
        <button class="btn btn-warning" id="btn-delete-stop" onclick="stopDelete()" style="display:none">暂停</button>
        <div class="progress-bar" id="delete-progress" style="display:none">
            <div class="progress-fill red" id="delete-fill" style="width:0%">0%</div>
        </div>
        <div class="stats" id="delete-stats"></div>
        <div class="log" id="delete-log" style="display:none"></div>
    </div>

    <div class="card">
        <p><a href="admin.php">← 返回后台</a></p>
    </div>
</div>

<script>
/* ===== 目录切换 ===== */
var currentDir = 'upload';

function switchDir(d) {
    currentDir = d;
    fetch('?action=scan&dir=' + d).then(r => r.json()).then(data => {
        document.getElementById('dir-info').innerHTML =
            '当前目录: <code>static/' + d + '/</code> | 文件数: ' + data.total + ' | 大小: ' + data.size_mb + ' MB';
    }).catch(e => {
        document.getElementById('dir-info').innerHTML = '扫描失败';
    });
}
switchDir('upload');

/* ===== 上传 ===== */
var uploading = false, uploadStopped = false;

function testR2() {
    document.getElementById('test-result').innerHTML = '测试中...';
    fetch('?action=test').then(r => r.json()).then(d => {
        document.getElementById('test-result').innerHTML = d.success
            ? '<span style="color:#52c41a">✅ ' + d.message + '</span>'
            : '<span style="color:#ff4d4f">❌ ' + d.message + '</span>';
    }).catch(e => {
        document.getElementById('test-result').innerHTML = '<span style="color:#ff4d4f">请求失败</span>';
    });
}

function startUpload() {
    if (uploading) return;
    uploading = true; uploadStopped = false;
    document.getElementById('btn-upload').disabled = true;
    document.getElementById('btn-stop').style.display = 'inline-block';
    show('upload-progress'); show('upload-log');
    document.getElementById('upload-log').innerHTML = '';
    uploadBatch(0);
}
function stopUpload() {
    uploadStopped = true;
    document.getElementById('btn-stop').style.display = 'none';
    document.getElementById('btn-upload').disabled = false;
    uploading = false;
    appendLog('upload-log', '⏸ 已暂停', 'info');
}
function uploadBatch(offset) {
    if (uploadStopped) return;
    fetch('?action=upload_batch&dir=' + currentDir + '&offset=' + offset + '&limit=10').then(r => r.json()).then(d => {
        var pct = Math.min(100, Math.round((offset + d.processed) / d.total * 100));
        setProgress('upload-fill', pct);
        document.getElementById('upload-stats').innerHTML =
            '已处理: ' + (offset + d.processed) + ' / ' + d.total + ' | 本批成功: ' + d.success_count + '/' + d.processed;
        d.results.forEach(r => {
            appendLog('upload-log', r.success ? (r.message === '已存在，跳过' ? '⏭️ ' + r.file + ' (已存在)' : '✅ ' + r.file) : '❌ ' + r.file + ' — ' + r.message, r.success ? 'ok' : 'err');
        });
        if (!d.done && !uploadStopped) { uploadBatch(offset + d.processed); }
        else if (d.done) {
            appendLog('upload-log', '🎉 全部上传完成！', 'info');
            uploading = false;
            document.getElementById('btn-upload').disabled = false;
            document.getElementById('btn-stop').style.display = 'none';
        }
    }).catch(e => {
        appendLog('upload-log', '❌ 请求失败: ' + e.message, 'err');
        uploading = false; document.getElementById('btn-upload').disabled = false;
    });
}

/* ===== 替换数据库 ===== */
function replaceDb() {
    if (!confirm('确定要替换数据库中的文件链接吗？建议先备份数据库！')) return;
    document.getElementById('btn-replace').disabled = true;
    document.getElementById('replace-result').innerHTML = '正在替换...';
    fetch('?action=replace_db').then(r => r.json()).then(d => {
        if (d.success) {
            var html = '<p style="color:#52c41a">✅ 替换完成！共修改 ' + d.total_rows + ' 条记录</p>';
            html += '<p>旧前缀: <code>' + d.old_prefix + '</code></p>';
            html += '<p>新前缀: <code>' + d.new_prefix + '</code></p><ul>';
            for (var k in d.replaced) { if (d.replaced[k] > 0) html += '<li>' + k + ': ' + d.replaced[k] + ' 条</li>'; }
            html += '</ul>';
            document.getElementById('replace-result').innerHTML = html;
        } else {
            document.getElementById('replace-result').innerHTML = '<span style="color:#ff4d4f">❌ ' + d.message + '</span>';
        }
        document.getElementById('btn-replace').disabled = false;
    }).catch(e => {
        document.getElementById('replace-result').innerHTML = '<span style="color:#ff4d4f">请求失败</span>';
        document.getElementById('btn-replace').disabled = false;
    });
}

/* ===== 对比文件 ===== */
var comparing = false, compareStopped = false;
var compareMatchTotal = 0, compareMissTotal = 0;

function startCompare() {
    if (comparing) return;
    comparing = true; compareStopped = false;
    compareMatchTotal = 0; compareMissTotal = 0;
    document.getElementById('btn-compare').disabled = true;
    document.getElementById('btn-compare-stop').style.display = 'inline-block';
    show('compare-progress'); show('compare-log');
    document.getElementById('compare-log').innerHTML = '';
    compareBatch(0);
}
function stopCompare() {
    compareStopped = true;
    document.getElementById('btn-compare-stop').style.display = 'none';
    document.getElementById('btn-compare').disabled = false;
    comparing = false;
    appendLog('compare-log', '⏸ 已暂停', 'info');
}
function compareBatch(offset) {
    if (compareStopped) return;
    fetch('?action=compare_batch&dir=' + currentDir + '&offset=' + offset + '&limit=20').then(r => r.json()).then(d => {
        var pct = Math.min(100, Math.round((offset + d.processed) / d.total * 100));
        setProgress('compare-fill', pct);
        compareMatchTotal += d.match_count;
        compareMissTotal += d.miss_count;
        document.getElementById('compare-stats').innerHTML =
            '已对比: ' + (offset + d.processed) + ' / ' + d.total +
            ' | ✅ 一致: ' + compareMatchTotal + ' | ❌ 不一致/缺失: ' + compareMissTotal;
        d.results.forEach(r => {
            if (r.status === 'match') {
                appendLog('compare-log', '✅ ' + r.file + ' (' + formatSize(r.local_size) + ')', 'ok');
            } else if (r.status === 'size_mismatch') {
                appendLog('compare-log', '⚠️ ' + r.file + ' 大小不一致 (本地:' + formatSize(r.local_size) + ' R2:' + formatSize(r.r2_size) + ')', 'warn');
            } else {
                appendLog('compare-log', '❌ ' + r.file + ' R2上不存在', 'err');
            }
        });
        if (!d.done && !compareStopped) { compareBatch(offset + d.processed); }
        else if (d.done) {
            var msg = '🎉 对比完成！一致: ' + compareMatchTotal + ' | 不一致/缺失: ' + compareMissTotal;
            appendLog('compare-log', msg, 'info');
            if (compareMissTotal === 0) {
                appendLog('compare-log', '✅ 所有文件已同步，可以安全删除本地文件', 'ok');
            } else {
                appendLog('compare-log', '⚠️ 有 ' + compareMissTotal + ' 个文件不一致或缺失，请先重新上传', 'warn');
            }
            comparing = false;
            document.getElementById('btn-compare').disabled = false;
            document.getElementById('btn-compare-stop').style.display = 'none';
        }
    }).catch(e => {
        appendLog('compare-log', '❌ 请求失败: ' + e.message, 'err');
        comparing = false; document.getElementById('btn-compare').disabled = false;
    });
}

/* ===== 删除本地文件 ===== */
var deleting = false, deleteStopped = false;
var deleteTotal = 0, deletedTotal = 0, skippedTotal = 0;

function startDelete() {
    if (!confirm('确定要删除本地已同步到 R2 的文件吗？\n\n只会删除 R2 上存在且大小一致的文件，其余会跳过。')) return;
    if (deleting) return;
    deleting = true; deleteStopped = false;
    deletedTotal = 0; skippedTotal = 0; deleteTotal = 0;
    document.getElementById('btn-delete').disabled = true;
    document.getElementById('btn-delete-stop').style.display = 'inline-block';
    show('delete-progress'); show('delete-log');
    document.getElementById('delete-log').innerHTML = '';
    deleteBatch();
}
function stopDelete() {
    deleteStopped = true;
    document.getElementById('btn-delete-stop').style.display = 'none';
    document.getElementById('btn-delete').disabled = false;
    deleting = false;
    appendLog('delete-log', '⏸ 已暂停', 'info');
}
function deleteBatch() {
    if (deleteStopped) return;
    fetch('?action=delete_batch&dir=' + currentDir + '&limit=20').then(r => r.json()).then(d => {
        if (deleteTotal === 0) deleteTotal = d.total_before;
        deletedTotal += d.deleted;
        skippedTotal += d.skipped;
        var pct = deleteTotal > 0 ? Math.min(100, Math.round((deletedTotal + skippedTotal) / deleteTotal * 100)) : 100;
        setProgress('delete-fill', pct);
        document.getElementById('delete-stats').innerHTML =
            '已删除: ' + deletedTotal + ' | 跳过: ' + skippedTotal + ' | 剩余: ' + d.remaining;
        d.results.forEach(r => {
            if (r.status === 'deleted') {
                appendLog('delete-log', '🗑️ ' + r.file, 'ok');
            } else if (r.status === 'skipped') {
                appendLog('delete-log', '⏭️ ' + r.file + ' (' + r.reason + ')', 'warn');
            } else {
                appendLog('delete-log', '❌ ' + r.file + ' 删除失败', 'err');
            }
        });
        if (!d.done && !deleteStopped) { deleteBatch(); }
        else {
            appendLog('delete-log', '🎉 完成！删除: ' + deletedTotal + ' | 跳过: ' + skippedTotal + ' | 剩余: ' + d.remaining, 'info');
            deleting = false;
            document.getElementById('btn-delete').disabled = false;
            document.getElementById('btn-delete-stop').style.display = 'none';
        }
    }).catch(e => {
        appendLog('delete-log', '❌ 请求失败: ' + e.message, 'err');
        deleting = false; document.getElementById('btn-delete').disabled = false;
    });
}

/* ===== 工具函数 ===== */
function appendLog(id, msg, cls) {
    var log = document.getElementById(id);
    log.innerHTML += '<div class="' + (cls||'') + '">' + msg + '</div>';
    log.scrollTop = log.scrollHeight;
}
function setProgress(id, pct) {
    var el = document.getElementById(id);
    el.style.width = pct + '%';
    el.textContent = pct + '%';
}
function show(id) { document.getElementById(id).style.display = 'block'; }
function formatSize(bytes) {
    if (bytes < 1024) return bytes + 'B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + 'KB';
    return (bytes / 1048576).toFixed(1) + 'MB';
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

/**
 * 递归清理空目录
 */
function cleanEmptyDirs($dir) {
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            cleanEmptyDirs($path);
        }
    }
    // 再次检查目录是否为空
    $items = array_diff(scandir($dir), ['.', '..']);
    if (empty($items) && $dir !== ROOT_PATH . '/static/upload') {
        rmdir($dir);
    }
}
