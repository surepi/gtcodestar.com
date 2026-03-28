<?php
/**
 * S3存储桶上传插件
 * @copyright (C)2025
 * @description 支持上传静态文件到AWS S3或兼容S3的存储服务
 */

namespace plugin\s3storage;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class S3Storage
{
    // S3客户端实例
    private $s3Client = null;
    
    // 配置信息
    private $config = array();
    
    // 是否启用
    private $enabled = false;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->config = $this->loadConfig();
        $this->enabled = !empty($this->config['enabled']) ? true : false;
        
        if ($this->enabled) {
            $this->initS3Client();
        }
    }

    /**
     * 加载插件配置
     */
    private function loadConfig()
    {
        $configFile = ROOT_PATH . '/plugins/s3storage/plugin.json';
        
        if (file_exists($configFile)) {
            $pluginConfig = json_decode(file_get_contents($configFile), true);
            return $pluginConfig['config'] ?? array();
        }
        
        return array();
    }

    /**
     * 初始化S3客户端
     */
    private function initS3Client()
    {
        try {
            $s3Config = array(
                'version' => 'latest',
                'region' => $this->config['region'] ?: 'us-east-1',
                'credentials' => array(
                    'key' => $this->config['access_key'],
                    'secret' => $this->config['secret_key']
                )
            );
            
            // 如果配置了自定义端点(兼容其他S3服务)
            if (!empty($this->config['endpoint'])) {
                $s3Config['endpoint'] = $this->config['endpoint'];
                $s3Config['use_path_style_endpoint'] = $this->config['use_path_style'] ?: false;
            }
            
            // 检查AWS SDK是否存在
            if (!class_exists('Aws\S3\S3Client')) {
                throw new \Exception('AWS SDK for PHP未安装,请先执行: composer require aws/aws-sdk-php');
            }
            
            $this->s3Client = new S3Client($s3Config);
            
        } catch (\Exception $e) {
            error_log('S3客户端初始化失败: ' . $e->getMessage());
            $this->enabled = false;
        }
    }

    /**
     * 上传文件到S3
     * @param string $localFile 本地文件路径
     * @param string $remoteFile 远程文件路径(相对于bucket)
     * @return array 返回结果 ['success' => bool, 'url' => string, 'error' => string]
     */
    public function upload($localFile, $remoteFile = null)
    {
        // 检查是否启用
        if (!$this->enabled || !$this->s3Client) {
            return array(
                'success' => false,
                'error' => 'S3存储未启用或配置错误'
            );
        }
        
        // 检查本地文件是否存在
        if (!file_exists($localFile)) {
            return array(
                'success' => false,
                'error' => '本地文件不存在: ' . $localFile
            );
        }
        
        // 如果未指定远程路径,生成默认路径
        if (empty($remoteFile)) {
            $uploadPath = $this->config['upload_path'] ?: 'uploads';
            $remoteFile = $uploadPath . '/' . date('Ymd') . '/' . basename($localFile);
        }
        
        try {
            // 上传文件
            $result = $this->s3Client->putObject(array(
                'Bucket' => $this->config['bucket'],
                'Key' => $remoteFile,
                'SourceFile' => $localFile,
                'ACL' => $this->config['acl'] ?: 'public-read',
                'ContentType' => $this->getMimeType($localFile)
            ));
            
            // 获取文件URL
            if (!empty($this->config['cdn_domain'])) {
                // 使用CDN域名
                $url = rtrim($this->config['cdn_domain'], '/') . '/' . $remoteFile;
            } elseif (!empty($this->config['endpoint'])) {
                // 自定义端点
                $url = rtrim($this->config['endpoint'], '/') . '/' . $this->config['bucket'] . '/' . $remoteFile;
            } else {
                // AWS S3官方地址
                $url = 'https://' . $this->config['bucket'] . '.s3.' . $this->config['region'] . '.amazonaws.com/' . $remoteFile;
            }
            
            return array(
                'success' => true,
                'url' => $url,
                'path' => $remoteFile,
                'bucket' => $this->config['bucket']
            );
            
        } catch (AwsException $e) {
            return array(
                'success' => false,
                'error' => 'S3上传失败: ' . $e->getMessage()
            );
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'error' => '上传失败: ' . $e->getMessage()
            );
        }
    }

    /**
     * 删除S3文件
     * @param string $remoteFile 远程文件路径
     * @return bool
     */
    public function delete($remoteFile)
    {
        if (!$this->enabled || !$this->s3Client) {
            return false;
        }
        
        try {
            $this->s3Client->deleteObject(array(
                'Bucket' => $this->config['bucket'],
                'Key' => $remoteFile
            ));
            return true;
        } catch (AwsException $e) {
            error_log('S3删除失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 测试连接
     * @return array
     */
    public function testConnection()
    {
        if (!$this->s3Client) {
            return array(
                'success' => false,
                'error' => 'S3客户端未初始化'
            );
        }
        
        try {
            // 尝试列出bucket
            $result = $this->s3Client->headBucket(array(
                'Bucket' => $this->config['bucket']
            ));
            
            return array(
                'success' => true,
                'message' => '连接成功'
            );
            
        } catch (AwsException $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * 获取文件MIME类型
     * @param string $file 文件路径
     * @return string
     */
    private function getMimeType($file)
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file);
        finfo_close($finfo);
        return $mimeType ?: 'application/octet-stream';
    }

    /**
     * 检查是否启用
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * 获取配置信息
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }
}
