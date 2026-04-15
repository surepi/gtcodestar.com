<?php
/**
 * S3存储配置控制器
 */

namespace plugin\s3storage\admin;

use core\basic\Controller;

class ConfigController extends Controller
{
    private $pluginPath;
    private $configFile;

    public function __construct()
    {
        $this->pluginPath = ROOT_PATH . '/plugins/s3storage';
        $this->configFile = $this->pluginPath . '/plugin.json';
    }

    /**
     * 配置页面
     */
    public function index()
    {
        $config = $this->getConfig();
        
        $this->assign('config', $config);
        $this->display($this->pluginPath . '/admin/view/config.html');
    }

    /**
     * 保存配置
     */
    public function save()
    {
        if (!$_POST) {
            return;
        }

        $config = array(
            'enabled' => post('enabled', 'int') ?: 0,
            'bucket' => post('bucket'),
            'access_key' => post('access_key'),
            'secret_key' => post('secret_key'),
            'region' => post('region') ?: 'us-east-1',
            'endpoint' => post('endpoint'),
            'use_path_style' => post('use_path_style', 'int') ?: 0,
            'cdn_domain' => post('cdn_domain'),
            'upload_path' => post('upload_path') ?: 'uploads',
            'acl' => post('acl') ?: 'public-read'
        );

        // 保存配置
        $result = $this->saveConfig($config);

        if ($result) {
            alert_location('配置保存成功！', url('/plugin/s3storage/admin/Config/index'));
        } else {
            alert_back('配置保存失败！');
        }
    }

    /**
     * 测试连接
     */
    public function test()
    {
        require_once $this->pluginPath . '/S3Storage.php';
        
        $s3 = new \plugin\s3storage\S3Storage();
        
        if (!$s3->isEnabled()) {
            return json(array('code' => 0, 'data' => 'S3存储未启用'));
        }

        $result = $s3->testConnection();

        if ($result['success']) {
            return json(array('code' => 1, 'data' => '连接测试成功'));
        } else {
            return json(array('code' => 0, 'data' => $result['error']));
        }
    }

    /**
     * 获取配置
     */
    private function getConfig()
    {
        if (file_exists($this->configFile)) {
            $pluginData = json_decode(file_get_contents($this->configFile), true);
            return $pluginData['config'] ?? array();
        }
        return array();
    }

    /**
     * 保存配置
     */
    private function saveConfig($config)
    {
        try {
            $pluginData = array();
            
            // 读取现有配置文件
            if (file_exists($this->configFile)) {
                $pluginData = json_decode(file_get_contents($this->configFile), true);
            }
            
            // 更新配置
            $pluginData['config'] = $config;
            
            // 写入文件
            return file_put_contents($this->configFile, json_encode($pluginData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (\Exception $e) {
            return false;
        }
    }
}
