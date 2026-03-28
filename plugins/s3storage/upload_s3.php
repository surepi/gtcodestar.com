<?php
/**
 * S3上传扩展函数
 * 集成到系统上传流程
 */

use core\basic\Config;

/**
 * 上传文件到S3(扩展函数)
 * @param string $localFile 本地文件路径
 * @param string $remotePath 远程路径(可选)
 * @return array 返回结果
 */
function upload_to_s3($localFile, $remotePath = null)
{
    $pluginPath = ROOT_PATH . '/plugins/s3storage';
    
    // 检查插件文件是否存在
    if (!file_exists($pluginPath . '/S3Storage.php')) {
        return array(
            'success' => false,
            'error' => 'S3插件文件不存在'
        );
    }
    
    // 引入插件类
    require_once $pluginPath . '/S3Storage.php';
    
    // 创建S3实例
    $s3 = new \plugin\s3storage\S3Storage();
    
    // 检查是否启用
    if (!$s3->isEnabled()) {
        return array(
            'success' => false,
            'error' => 'S3存储未启用'
        );
    }
    
    // 上传文件
    return $s3->upload($localFile, $remotePath);
}

/**
 * 从S3删除文件
 * @param string $remotePath 远程文件路径
 * @return bool
 */
function delete_from_s3($remotePath)
{
    $pluginPath = ROOT_PATH . '/plugins/s3storage';
    
    if (!file_exists($pluginPath . '/S3Storage.php')) {
        return false;
    }
    
    require_once $pluginPath . '/S3Storage.php';
    $s3 = new \plugin\s3storage\S3Storage();
    
    if (!$s3->isEnabled()) {
        return false;
    }
    
    return $s3->delete($remotePath);
}

/**
 * 检查是否启用S3上传
 * @return bool
 */
function is_s3_enabled()
{
    $configFile = ROOT_PATH . '/plugins/s3storage/plugin.json';
    
    if (!file_exists($configFile)) {
        return false;
    }
    
    $config = json_decode(file_get_contents($configFile), true);
    return !empty($config['config']['enabled']);
}
