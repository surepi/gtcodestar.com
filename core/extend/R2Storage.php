<?php
/**
 * Cloudflare R2 存储类（纯PHP实现，AWS Signature V4）
 * 
 * 无需 AWS SDK，通过 curl 直接上传文件到 R2
 */

class R2Storage
{
    private $accountId;
    private $accessKeyId;
    private $secretAccessKey;
    private $bucket;
    private $customDomain;
    private $uploadPath;
    private $endpoint;
    private $region = 'auto';

    /**
     * 从 PbootCMS 数据库配置初始化
     */
    public function __construct()
    {
        $this->accountId      = $this->getConfig('r2_account_id');
        $this->accessKeyId    = $this->getConfig('r2_access_key_id');
        $this->secretAccessKey = $this->getConfig('r2_secret_access_key');
        $this->bucket         = $this->getConfig('r2_bucket');
        $customDomain = trim($this->getConfig('r2_custom_domain'));
        if ($customDomain && !preg_match('#^https?://#i', $customDomain)) {
            $customDomain = 'https://' . $customDomain;
        }
        $this->customDomain   = rtrim($customDomain, '/');
        $this->uploadPath     = trim($this->getConfig('r2_upload_path') ?: 'uploads', '/');
        $this->endpoint       = "https://{$this->accountId}.r2.cloudflarestorage.com";
    }

    /**
     * 检查是否启用
     */
    public function isEnabled()
    {
        return $this->getConfig('r2_enabled') == '1'
            && !empty($this->accountId)
            && !empty($this->accessKeyId)
            && !empty($this->secretAccessKey)
            && !empty($this->bucket);
    }

    /**
     * 上传文件到 R2
     * 
     * @param string $localFile  本地文件绝对路径
     * @param string $objectName 对象名称（不含前缀），如 image/20260328/abc.jpg
     * @return array ['success' => bool, 'url' => string, 'message' => string]
     */
    public function upload($localFile, $objectName)
    {
        if (!file_exists($localFile)) {
            return ['success' => false, 'url' => '', 'message' => '本地文件不存在'];
        }

        $objectKey = $this->uploadPath . '/' . ltrim($objectName, '/');
        $contentType = $this->detectMimeType($localFile);
        $fileContent = file_get_contents($localFile);
        $contentHash = hash('sha256', $fileContent);

        $host = "{$this->accountId}.r2.cloudflarestorage.com";
        $uri = '/' . $this->bucket . '/' . $objectKey;
        $now = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');

        // Canonical Request
        $canonicalHeaders = "content-type:{$contentType}\nhost:{$host}\nx-amz-content-sha256:{$contentHash}\nx-amz-date:{$now}\n";
        $signedHeaders = 'content-type;host;x-amz-content-sha256;x-amz-date';

        $canonicalRequest = "PUT\n{$uri}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$contentHash}";

        // String to Sign
        $scope = "{$date}/{$this->region}/s3/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n{$now}\n{$scope}\n" . hash('sha256', $canonicalRequest);

        // Signing Key
        $kDate    = hash_hmac('sha256', $date, "AWS4{$this->secretAccessKey}", true);
        $kRegion  = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorization = "AWS4-HMAC-SHA256 Credential={$this->accessKeyId}/{$scope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $url = $this->endpoint . $uri;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => $fileContent,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_HTTPHEADER     => [
                "Content-Type: {$contentType}",
                "Host: {$host}",
                "x-amz-content-sha256: {$contentHash}",
                "x-amz-date: {$now}",
                "Authorization: {$authorization}",
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'url' => '', 'message' => 'CURL错误: ' . $curlError];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            $publicUrl = $this->getPublicUrl($objectKey);
            return ['success' => true, 'url' => $publicUrl, 'message' => '上传成功'];
        }

        // 尝试从 XML 响应解析错误信息
        $errorMsg = "HTTP {$httpCode}";
        if (preg_match('/<Message>(.*?)<\/Message>/s', $response, $m)) {
            $errorMsg .= ': ' . $m[1];
        }

        return ['success' => false, 'url' => '', 'message' => '上传失败: ' . $errorMsg];
    }

    /**
     * 删除 R2 文件
     */
    public function delete($objectKey)
    {
        $host = "{$this->accountId}.r2.cloudflarestorage.com";
        $uri = '/' . $this->bucket . '/' . ltrim($objectKey, '/');
        $now = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $contentHash = hash('sha256', '');

        $canonicalHeaders = "host:{$host}\nx-amz-content-sha256:{$contentHash}\nx-amz-date:{$now}\n";
        $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';

        $canonicalRequest = "DELETE\n{$uri}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$contentHash}";

        $scope = "{$date}/{$this->region}/s3/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n{$now}\n{$scope}\n" . hash('sha256', $canonicalRequest);

        $kDate    = hash_hmac('sha256', $date, "AWS4{$this->secretAccessKey}", true);
        $kRegion  = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorization = "AWS4-HMAC-SHA256 Credential={$this->accessKeyId}/{$scope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->endpoint . $uri,
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                "Host: {$host}",
                "x-amz-content-sha256: {$contentHash}",
                "x-amz-date: {$now}",
                "Authorization: {$authorization}",
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 300;
    }

    /**
     * 测试连接（HEAD bucket）
     */
    public function testConnection()
    {
        $host = "{$this->accountId}.r2.cloudflarestorage.com";
        $uri = '/' . $this->bucket;
        $now = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $contentHash = hash('sha256', '');

        $canonicalHeaders = "host:{$host}\nx-amz-content-sha256:{$contentHash}\nx-amz-date:{$now}\n";
        $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';

        $canonicalRequest = "HEAD\n{$uri}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$contentHash}";

        $scope = "{$date}/{$this->region}/s3/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n{$now}\n{$scope}\n" . hash('sha256', $canonicalRequest);

        $kDate    = hash_hmac('sha256', $date, "AWS4{$this->secretAccessKey}", true);
        $kRegion  = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorization = "AWS4-HMAC-SHA256 Credential={$this->accessKeyId}/{$scope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->endpoint . $uri,
            CURLOPT_CUSTOMREQUEST  => 'HEAD',
            CURLOPT_NOBODY         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                "Host: {$host}",
                "x-amz-content-sha256: {$contentHash}",
                "x-amz-date: {$now}",
                "Authorization: {$authorization}",
            ],
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'message' => 'CURL错误: ' . $curlError];
        }

        if ($httpCode == 200) {
            return ['success' => true, 'message' => '连接成功'];
        }

        return ['success' => false, 'message' => "连接失败，HTTP {$httpCode}"];
    }

    /**
     * 获取公开访问 URL
     */
    private function getPublicUrl($objectKey)
    {
        if ($this->customDomain) {
            return $this->customDomain . '/' . $objectKey;
        }
        // R2 默认公开访问域名格式
        return "https://pub-{$this->accountId}.r2.dev/{$objectKey}";
    }

    /**
     * 检测 MIME 类型
     */
    private function detectMimeType($file)
    {
        $mimeTypes = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'bmp'  => 'image/bmp',
            'svg'  => 'image/svg+xml',
            'ico'  => 'image/x-icon',
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt'  => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'zip'  => 'application/zip',
            'rar'  => 'application/x-rar-compressed',
            'mp4'  => 'video/mp4',
            'mp3'  => 'audio/mpeg',
            'txt'  => 'text/plain',
            'css'  => 'text/css',
            'js'   => 'application/javascript',
            'json' => 'application/json',
            'xml'  => 'application/xml',
            'ttf'  => 'font/ttf',
            'otf'  => 'font/otf',
            'woff' => 'font/woff',
            'woff2'=> 'font/woff2',
        ];

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if (isset($mimeTypes[$ext])) {
            return $mimeTypes[$ext];
        }

        // fallback: finfo
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file);
            finfo_close($finfo);
            if ($mime) return $mime;
        }

        return 'application/octet-stream';
    }

    /**
     * 从 PbootCMS 数据库读取配置
     */
    private function getConfig($name)
    {
        // 优先从 Config 类读取（含缓存）
        if (class_exists('core\basic\Config')) {
            $val = \core\basic\Config::get($name);
            if ($val !== null && $val !== false) {
                return $val;
            }
        }

        // 直接查数据库
        if (defined('ROOT_PATH') && file_exists(ROOT_PATH . '/config/database.php')) {
            static $dbConfigs = null;
            if ($dbConfigs === null) {
                $dbConfigs = [];
                try {
                    $dbConfig = require ROOT_PATH . '/config/database.php';
                    $db = $dbConfig['database'];
                    $pdo = new PDO(
                        "mysql:host={$db['host']};port={$db['port']};dbname={$db['dbname']};charset=utf8",
                        $db['user'],
                        $db['passwd']
                    );
                    $stmt = $pdo->query("SELECT name, value FROM ay_config WHERE name LIKE 'r2_%'");
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $dbConfigs[$row['name']] = $row['value'];
                    }
                } catch (Exception $e) {
                    // 数据库连接失败时静默
                }
            }
            return isset($dbConfigs[$name]) ? $dbConfigs[$name] : '';
        }

        return '';
    }
}
