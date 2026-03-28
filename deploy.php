<?php
/**
 * Gitee WebHook 自动部署
 * 
 * 配置：
 * 1. 把此文件放到网站根目录
 * 2. Gitee 仓库 → 管理 → WebHooks → 添加
 *    URL: https://www.gtcodestar.com/deploy.php
 *    密码: 设置一个密码（修改下面的 SECRET）
 *    事件: Push
 * 3. 确保 PHP 用户有权限执行 git pull
 */

// ====== 配置 ======
$SECRET = 'gtcodestar_deploy_2026';  // 跟 Gitee WebHook 里设的密码一致
$REPO_PATH = dirname(__FILE__);       // 网站根目录
$LOG_FILE = $REPO_PATH . '/runtime/deploy.log';
// ==================

// 验证请求
$headers = getallheaders();
$body = file_get_contents('php://input');

// Gitee 签名验证
$token = isset($headers['X-Gitee-Token']) ? $headers['X-Gitee-Token'] : '';
if ($token !== $SECRET) {
    http_response_code(403);
    die('Forbidden');
}

// 只处理 push 事件
$event = isset($headers['X-Gitee-Event']) ? $headers['X-Gitee-Event'] : '';
if ($event !== 'Push Hook') {
    die('Not a push event');
}

// 解析推送信息
$payload = json_decode($body, true);
$ref = $payload['ref'] ?? '';
$pusher = $payload['pusher']['name'] ?? 'unknown';

// 只处理 main 分支
if ($ref !== 'refs/heads/main') {
    die('Not main branch');
}

// 执行部署
$output = [];
$commands = [
    "cd {$REPO_PATH}",
    "git fetch origin main 2>&1",
    "git reset --hard origin/main 2>&1",
];

$cmd = implode(' && ', $commands);
exec($cmd, $output, $returnCode);

// 记录日志
$log = date('Y-m-d H:i:s') . " | Pusher: {$pusher} | Code: {$returnCode}\n";
$log .= implode("\n", $output) . "\n";
$log .= str_repeat('-', 50) . "\n";
file_put_contents($LOG_FILE, $log, FILE_APPEND);

// 清理 PbootCMS 缓存
if (is_dir($REPO_PATH . '/runtime/config')) {
    array_map('unlink', glob($REPO_PATH . '/runtime/config/*'));
}
if (is_dir($REPO_PATH . '/runtime/complile')) {
    array_map('unlink', glob($REPO_PATH . '/runtime/complile/*'));
}

// 返回结果
echo json_encode([
    'success' => $returnCode === 0,
    'output' => $output
]);
