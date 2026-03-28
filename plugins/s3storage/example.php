<?php
/**
 * S3存储插件使用示例
 * 
 * 本文件展示了各种使用场景的代码示例
 */

// 引入插件增强上传函数
require ROOT_PATH . '/plugins/s3storage/upload_enhanced.php';

// ==================== 示例1: 简单单文件上传 ====================
function example1_simple_upload()
{
    // 基本上传
    $result = upload_with_s3('file', 'jpg,png,gif');
    
    if (empty($result['error'])) {
        echo "上传成功!\n";
        echo "本地路径: " . $result['local'] . "\n";
        echo "S3 URL: " . $result['s3'] . "\n";
        
        // 使用文件URL(优先S3)
        $url = $result['s3'] ?: get_http_url() . '/' . $result['local'];
        echo "文件访问URL: " . $url . "\n";
    } else {
        echo "上传失败: " . $result['error'] . "\n";
    }
}

// ==================== 示例2: 使用简化版函数 ====================
function example2_simple_function()
{
    $url = upload_to_s3_simple('avatar', 'jpg,png');
    
    if ($url) {
        echo "上传成功, URL: " . $url . "\n";
        
        // 直接保存到数据库
        // UPDATE ay_member SET pic='$url' WHERE id=1
        
    } else {
        echo "上传失败\n";
    }
}

// ==================== 示例3: 批量上传 ====================
function example3_batch_upload()
{
    // 批量上传多个文件
    $urls = upload_to_s3_batch('images', 'jpg,png,gif');
    
    if (!empty($urls)) {
        echo "成功上传 " . count($urls) . " 个文件:\n";
        foreach ($urls as $url) {
            echo "- " . $url . "\n";
        }
        
        // 批量保存到数据库
        // foreach ($urls as $url) {
        //     INSERT INTO ay_content_images (pic, create_time) VALUES ('$url', now())
        // }
    }
}

// ==================== 示例4: 文章内容编辑器集成 ====================
function example4_editor_upload()
{
    // 编辑器图片上传处理
    $result = upload_with_s3('file', 'jpg,png,gif,jpeg,webp');
    
    if (empty($result['error'])) {
        // 返回给编辑器的JSON
        $response = array(
            'error' => 0,
            'url' => $result['s3'] ?: get_http_url() . '/' . $result['local']
        );
        
        header('Content-Type: application/json');
        echo json_encode($response);
    } else {
        $response = array(
            'error' => 1,
            'message' => $result['error']
        );
        
        header('Content-Type: application/json');
        echo json_encode($response);
    }
}

// ==================== 示例5: 带图片尺寸控制的上传 ====================
function example5_resize_upload()
{
    // 上传并自动缩放到指定尺寸
    $result = upload_with_s3('photo', 'jpg,png', 1920, 1080);
    
    if (empty($result['error'])) {
        echo "上传并缩放成功!\n";
        echo "URL: " . $result['s3'] . "\n";
    }
}

// ==================== 示例6: 会员头像上传 ====================
function example6_avatar_upload()
{
    // 上传头像并限制尺寸
    $result = upload_with_s3('avatar', 'jpg,png', 200, 200);
    
    if (empty($result['error'])) {
        $avatar_url = $result['s3'] ?: get_http_url() . '/' . $result['local'];
        
        // 更新会员头像
        // UPDATE ay_member SET pic='$avatar_url' WHERE id={$uid}
        
        echo "头像更新成功: " . $avatar_url . "\n";
    }
}

// ==================== 示例7: 只上传到S3,不上传本地 ====================
function example7_s3_only()
{
    // 先用系统函数上传到本地临时目录
    if (!function_exists('upload')) {
        require CORE_PATH . '/function/file.php';
    }
    
    $local_result = upload('temp_file', 'jpg,png');
    
    if (is_array($local_result) && !empty($local_result[0])) {
        require ROOT_PATH . '/plugins/s3storage/upload_s3.php';
        
        $local_file = ROOT_PATH . '/' . $local_result[0];
        $s3_result = upload_to_s3($local_file, 'custom/path/' . basename($local_file));
        
        if ($s3_result['success']) {
            echo "S3上传成功: " . $s3_result['url'] . "\n";
            
            // 删除本地文件
            @unlink($local_file);
        }
    }
}

// ==================== 示例8: 手动上传已有文件到S3 ====================
function example8_manual_upload()
{
    require ROOT_PATH . '/plugins/s3storage/upload_s3.php';
    
    // 手动指定本地文件
    $local_file = ROOT_PATH . '/static/local/image.jpg';
    
    // 上传到S3
    $result = upload_to_s3($local_file, 'uploads/20250313/' . basename($local_file));
    
    if ($result['success']) {
        echo "手动上传成功!\n";
        echo "S3 URL: " . $result['url'] . "\n";
        echo "Bucket: " . $result['bucket'] . "\n";
        echo "Path: " . $result['path'] . "\n";
    } else {
        echo "上传失败: " . $result['error'] . "\n";
    }
}

// ==================== 示例9: 删除S3文件 ====================
function example9_delete_file()
{
    require ROOT_PATH . '/plugins/s3storage/upload_s3.php';
    
    // 删除S3上的文件
    $remote_path = 'uploads/20250313/image.jpg';
    
    if (delete_from_s3($remote_path)) {
        echo "S3文件删除成功\n";
    } else {
        echo "S3文件删除失败\n";
    }
}

// ==================== 示例10: 检查插件状态 ====================
function example10_check_status()
{
    require ROOT_PATH . '/plugins/s3storage/upload_s3.php';
    
    if (is_s3_enabled()) {
        echo "S3存储已启用\n";
    } else {
        echo "S3存储未启用\n";
    }
}

// ==================== 示例11: 内容发布时上传图片 ====================
function example11_content_publish()
{
    // 模拟内容发布场景
    if (post('submit')) {
        // 上传缩略图
        $thumbnail = upload_to_s3_simple('thumbnail', 'jpg,png');
        
        // 上传多图
        $gallery = upload_to_s3_batch('gallery', 'jpg,png,gif');
        
        // 保存到数据库
        $data = array(
            'title' => post('title'),
            'thumbnail' => $thumbnail,
            'pics' => implode(',', $gallery),
            'content' => post('content'),
            'date' => date('Y-m-d H:i:s')
        );
        
        // INSERT INTO ay_content ...
        
        echo "内容发布成功!\n";
        echo "缩略图: " . $thumbnail . "\n";
        echo "图集: " . implode(',', $gallery) . "\n";
    }
}

// ==================== 示例12: 批量迁移本地文件到S3 ====================
function example12_migrate_to_s3()
{
    require ROOT_PATH . '/plugins/s3storage/upload_s3.php';
    
    if (!is_s3_enabled()) {
        echo "S3未启用,无法迁移\n";
        return;
    }
    
    // 扫描本地upload目录
    $upload_dir = ROOT_PATH . '/static/upload';
    
    if ($handle = opendir($upload_dir)) {
        $count = 0;
        
        while (false !== ($file = readdir($handle))) {
            if ($file != '.' && $file != '..' && is_file($upload_dir . '/' . $file)) {
                $local_file = $upload_dir . '/' . $file;
                
                // 上传到S3
                $remote_path = 'migration/' . date('Ymd') . '/' . $file;
                $result = upload_to_s3($local_file, $remote_path);
                
                if ($result['success']) {
                    echo "迁移成功: " . $file . " -> " . $result['url'] . "\n";
                    $count++;
                } else {
                    echo "迁移失败: " . $file . " - " . $result['error'] . "\n";
                }
            }
        }
        
        closedir($handle);
        echo "共迁移 " . $count . " 个文件\n";
    }
}

// ==================== 示例13: 图片打水印后上传 ====================
function example13_watermark_upload()
{
    // 上传并自动打水印
    $result = upload_with_s3('file', 'jpg,png', null, null, true);
    
    if (empty($result['error'])) {
        echo "上传并打水印成功: " . $result['s3'] . "\n";
    }
}

// ==================== 示例14: 获取上传结果详细信息 ====================
function example14_detailed_result()
{
    $result = upload_with_s3('file', 'jpg,png');
    
    if (empty($result['error'])) {
        // 保存到数据库时可以同时保存本地和S3路径
        $db_data = array(
            'local_path' => $result['local'],
            's3_url' => $result['s3'],
            'create_time' => date('Y-m-d H:i:s'),
            's3_enabled' => !empty($result['s3']) ? 1 : 0
        );
        
        print_r($db_data);
    }
}

// ==================== 使用说明 ====================
/*
使用步骤:
1. 确保已安装AWS SDK: composer require aws/aws-sdk-php
2. 在后台配置好S3参数
3. 在需要的地方引入: require ROOT_PATH . '/plugins/s3storage/upload_enhanced.php'
4. 调用相应函数即可

注意事项:
- 插件默认同时保存到本地和S3
- S3上传失败不影响本地存储
- 建议在生产环境使用前充分测试
- 定期检查S3存储费用和配额
*/
