<?php
/**
 * S3兼容的OSS上传类
 * 支持阿里云OSS、腾讯云COS、AWS S3、MinIO等
 * 不依赖SDK，使用原生PHP实现
 */

class OssUpload
{
    private $config;
    private $accessKeyId;
    private $accessKeySecret;
    private $endpoint;
    private $bucket;
    private $uploadPath;
    private $customDomain;
    private $usePathStyle;
    private $acl;
    private $provider;

    /**
     * 构造函数
     */
    public function __construct($config = [])
    {
        if (!function_exists('curl_init')) {
            throw new Exception('CURL扩展未安装');
        }

        $this->config = $config;
        $this->accessKeyId = $config['access_key_id'];
        $this->accessKeySecret = $config['access_key_secret'];
        $this->endpoint = $config['endpoint'];
        $this->bucket = $config['bucket'];
        $this->uploadPath = rtrim($config['upload_path'], '/');
        $this->customDomain = $config['custom_domain'];
        $this->usePathStyle = $config['use_path_style'];
        $this->acl = $config['acl'];
        $this->provider = $config['provider'] ?? 'aliyun';
    }

    /**
     * 上传文件到OSS
     * @param string $localFile 本地文件路径
     * @param string $objectName OSS对象名称（不含路径前缀）
     * @param string $contentType 内容类型
     * @return array 返回上传结果
     */
    public function uploadFile($localFile, $objectName, $contentType = null)
    {
        if (!file_exists($localFile)) {
            return ['success' => false, 'message' => '文件不存在'];
        }

        // 构建完整的对象路径
        $objectPath = $this->uploadPath . '/' . $objectName;

        // 自动检测内容类型
        if (!$contentType) {
            $contentType = $this->detectMimeType($localFile);
        }

        // 构建URL
        $url = $this->buildUrl($objectPath);

        // 准备请求头
        $headers = [
            'Content-Type: ' . $contentType,
            'Host: ' . $this->getHost(),
            'x-amz-acl: ' . $this->acl,
            'Cache-Control: ' . $this->config['cache_control'] ?? 'max-age=31536000',
        ];

        // 根据服务商添加特定头部
        if ($this->provider === 'aliyun') {
            $headers[] = 'x-oss-object-acl: ' . $this->acl;
        } elseif ($this->provider === 'tencent') {
            $headers[] = 'x-cos-acl: ' . $this->acl;
        }

        // 上传文件
        $fileHandle = fopen($localFile, 'rb');
        if (!$fileHandle) {
            return ['success' => false, 'message' => '无法打开文件'];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_PUT, 1);
        curl_setopt($ch, CURLOPT_INFILE, $fileHandle);
        curl_setopt($ch, CURLOPT_INFILESIZE, filesize($localFile));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);

        // 签名请求
        $this->signRequest($ch, 'PUT', $objectPath, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($fileHandle); // 关闭文件句柄

        if ($error) {
            return ['success' => false, 'message' => '上传失败: ' . $error];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            $publicUrl = $this->getPublicUrl($objectPath);
            return [
                'success' => true,
                'url' => $publicUrl,
                'object' => $objectPath,
                'message' => '上传成功'
            ];
        } else {
            return ['success' => false, 'message' => '上传失败，HTTP状态码: ' . $httpCode];
        }
    }

    /**
     * 批量上传文件
     * @param array $files 文件数组 [['local' => 'path', 'object' => 'name', 'type' => 'mime']]
     * @return array 返回批量上传结果
     */
    public function uploadBatch($files)
    {
        $results = [];
        $successCount = 0;

        foreach ($files as $file) {
            $result = $this->uploadFile(
                $file['local'],
                $file['object'],
                $file['type'] ?? null
            );

            $results[] = $result;
            if ($result['success']) {
                $successCount++;
            }
        }

        return [
            'success' => $successCount > 0,
            'total' => count($files),
            'success_count' => $successCount,
            'failed_count' => count($files) - $successCount,
            'results' => $results
        ];
    }

    /**
     * 删除文件
     * @param string $objectPath 对象路径
     * @return array
     */
    public function deleteFile($objectPath)
    {
        $url = $this->buildUrl($objectPath);

        $headers = [
            'Host: ' . $this->getHost(),
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $this->signRequest($ch, 'DELETE', $objectPath, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'message' => $httpCode >= 200 && $httpCode < 300 ? '删除成功' : '删除失败'
        ];
    }

    /**
     * 检测文件MIME类型
     */
    private function detectMimeType($file)
    {
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'mp4' => 'video/mp4',
            'mp3' => 'audio/mpeg',
            'txt' => 'text/plain',
        ];

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        return $mimeTypes[$ext] ?? 'application/octet-stream';
    }

    /**
     * 构建请求URL
     */
    private function buildUrl($objectPath)
    {
        $host = $this->getHost();
        return 'https://' . $host . '/' . ltrim($objectPath, '/');
    }

    /**
     * 获取主机名
     */
    private function getHost()
    {
        if ($this->usePathStyle) {
            return $this->endpoint . '/' . $this->bucket;
        } else {
            $endpoint = str_replace('https://', '', $this->endpoint);
            return $this->bucket . '.' . $endpoint;
        }
    }

    /**
     * 获取公开访问URL
     */
    private function getPublicUrl($objectPath)
    {
        if ($this->customDomain) {
            $domain = rtrim($this->customDomain, '/');
            return $domain . '/' . ltrim($objectPath, '/');
        } else {
            return $this->buildUrl($objectPath);
        }
    }

    /**
     * 签名请求（AWS Signature V2，兼容阿里云/腾讯云）
     */
    private function signRequest($ch, $method, $objectPath, $headers)
    {
        $host = $this->getHost();
        $uri = '/' . ltrim($objectPath, '/');

        // 构建规范字符串
        $canonicalizedHeaders = '';
        $signedHeaders = [];

        foreach ($headers as $header) {
            if (strpos($header, 'x-amz-') === 0 || strpos($header, 'x-oss-') === 0 || strpos($header, 'x-cos-') === 0) {
                $parts = explode(': ', $header);
                $signedHeaders[] = strtolower($parts[0]);
            }
        }

        $signedHeaders = array_unique($signedHeaders);
        sort($signedHeaders);
        $signedHeadersStr = implode(';', $signedHeaders);

        $resource = $uri;
        if (!$this->usePathStyle) {
            $resource = '/' . $this->bucket . $uri;
        }

        $contentType = '';
        foreach ($headers as $header) {
            if (strpos($header, 'Content-Type:') === 0) {
                $contentType = trim(str_replace('Content-Type:', '', $header));
                break;
            }
        }

        $stringToSign = $method . "\n\n" . $contentType . "\n\n";
        $stringToSign .= implode("\n", $headers) . "\n";
        $stringToSign .= $resource;

        // 计算签名
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->accessKeySecret, true));
        $authorization = 'AWS ' . $this->accessKeyId . ':' . $signature;

        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, [
            'Authorization: ' . $authorization
        ]));
    }
}
