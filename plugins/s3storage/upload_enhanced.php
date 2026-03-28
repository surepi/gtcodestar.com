<?php
/**
 * 增强的上传函数 - 支持本地+S3双存储
 * 
 * 使用方法:
 * 1. 在需要的地方引入本文件
 * 2. 使用 upload_with_s3() 函数替代系统的 upload() 函数
 */

// 引入S3扩展函数
require_once __DIR__ . '/upload_s3.php';

/**
 * 增强版上传函数 - 本地+S3双存储
 * @param string $input_name 表单字段名
 * @param string $file_ext 允许的文件扩展名(逗号分隔)
 * @param int $max_width 最大宽度
 * @param int $max_height 最大高度
 * @param bool $watermark 是否打水印
 * @param bool $upload_to_s3 是否上传到S3
 * @return array 返回结果 ['local' => string, 's3' => string|false, 'error' => string]
 */
function upload_with_s3($input_name, $file_ext = null, $max_width = null, $max_height = null, $watermark = false, $upload_to_s3 = true)
{
    // 引入系统上传函数
    if (!function_exists('upload')) {
        require CORE_PATH . '/function/file.php';
    }
    
    // 先上传到本地
    $local_result = upload($input_name, $file_ext, $max_width, $max_height, $watermark);
    
    // 如果上传失败
    if (is_string($local_result)) {
        return array(
            'local' => '',
            's3' => false,
            'error' => $local_result
        );
    }
    
    // 获取本地文件路径
    $local_file = '';
    if (is_array($local_result) && count($local_result) > 0) {
        $local_file = ROOT_PATH . '/' . $local_result[0];
    }
    
    // 初始化返回结果
    $result = array(
        'local' => is_array($local_result) && count($local_result) > 0 ? $local_result[0] : '',
        's3' => false,
        'error' => ''
    );
    
    // 如果启用S3且要求上传到S3
    if ($upload_to_s3 && is_s3_enabled() && !empty($local_file) && file_exists($local_file)) {
        // 生成S3远程路径(保持原有路径结构)
        $remote_path = str_replace(ROOT_PATH, '', $local_file);
        $remote_path = ltrim($remote_path, '/');
        $remote_path = preg_replace('/^static\//', '', $remote_path); // 移除static前缀
        
        // 上传到S3
        $s3_result = upload_to_s3($local_file, $remote_path);
        
        if ($s3_result['success']) {
            $result['s3'] = $s3_result['url'];
        } else {
            $result['error'] = 'S3上传失败: ' . $s3_result['error'];
            // 注意: 即使S3上传失败,文件已经上传到本地,所以不算完全失败
        }
    }
    
    return $result;
}

/**
 * 上传单文件并返回S3 URL(简化版)
 * @param string $input_name 表单字段名
 * @param string $file_ext 允许的文件扩展名
 * @return string 返回文件URL,失败返回false
 */
function upload_to_s3_simple($input_name, $file_ext = null)
{
    $result = upload_with_s3($input_name, $file_ext);
    
    if (!empty($result['error'])) {
        return false;
    }
    
    // 优先返回S3 URL,如果没有则返回本地URL
    if (!empty($result['s3'])) {
        return $result['s3'];
    } elseif (!empty($result['local'])) {
        return get_http_url() . '/' . $result['local'];
    }
    
    return false;
}

/**
 * 批量上传并返回S3 URL
 * @param string $input_name 表单字段名
 * @param string $file_ext 允许的文件扩展名
 * @return array 返回URL数组
 */
function upload_to_s3_batch($input_name, $file_ext = null)
{
    $urls = array();
    
    // 引入系统上传函数
    if (!function_exists('upload')) {
        require CORE_PATH . '/function/file.php';
    }
    
    // 先上传到本地
    $local_result = upload($input_name, $file_ext);
    
    if (is_string($local_result)) {
        return array();
    }
    
    // 如果启用S3
    if (is_s3_enabled() && is_array($local_result)) {
        foreach ($local_result as $local_path) {
            $local_file = ROOT_PATH . '/' . $local_path;
            
            if (file_exists($local_file)) {
                // 生成S3远程路径
                $remote_path = str_replace(ROOT_PATH, '', $local_file);
                $remote_path = ltrim($remote_path, '/');
                $remote_path = preg_replace('/^static\//', '', $remote_path);
                
                // 上传到S3
                $s3_result = upload_to_s3($local_file, $remote_path);
                
                if ($s3_result['success']) {
                    $urls[] = $s3_result['url'];
                } else {
                    // S3失败返回本地URL
                    $urls[] = get_http_url() . '/' . $local_path;
                }
            }
        }
    } elseif (is_array($local_result)) {
        // S3未启用,返回本地URL
        foreach ($local_result as $local_path) {
            $urls[] = get_http_url() . '/' . $local_path;
        }
    }
    
    return $urls;
}
