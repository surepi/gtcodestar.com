<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2016年11月6日
 *  模板显示类
 */
namespace core\view;

use core\basic\Config;

class View
{

    // 模板路径
    protected $tplPath;

    // 编译路径
    protected $tplcPath;

    // 缓存路径
    protected $cachePath;

    // 存储注入变量
    protected $vars = array();

    // 存储包含文件
    protected $incFile = array();

    // 实例
    protected static $view;

    // 获取单一实例
    public static function getInstance()
    {
        if (! self::$view) {
            self::$view = new self();
        }
        return self::$view;
    }

    // 禁止通过new实例化类
    private function __construct()
    {
        $this->tplPath = APP_VIEW_PATH;
        $this->tplcPath = RUN_PATH . '/complile';
        $this->cachePath = RUN_PATH . '/cache';
        check_dir($this->tplcPath, true);
        check_dir($this->cachePath, true);
    }

    private function __clone()
    {
        die('不允许克隆对象！请使用getInstance获取实例');
    }

    // 变量注入
    public function assign($var, $value)
    {
        if (! empty($var)) {
            if (isset($this->vars[$var])) {
                error('模板变量$' . $var . '出现重复注入！');
            }
            $this->vars[$var] = $value;
            return true;
        } else {
            error('传递的设置模板变量有误');
        }
    }

    // 变量获取
    public function getVar($var)
    {
        if (! empty($var)) {
            if (isset($this->vars[$var])) {
                return $this->vars[$var];
            } else {
                return null;
            }
        } else {
            error('传递的获取模板变量有误');
        }
    }

    // 安全写入文件，避免高速并发写入导致缓存/编译文件损坏
    private function writeFile($file, $content)
    {
        $dir = dirname($file);
        if (! is_dir($dir)) {
            check_dir($dir, true);
        }
        $tmpFile = $file . '.' . uniqid('', true) . '.tmp';
        if (file_put_contents($tmpFile, $content, LOCK_EX) === false) {
            error('写入临时文件' . $tmpFile . '失败！请检查目录是否有可写权限！');
        }
        if (! rename($tmpFile, $file)) {
            if (! @copy($tmpFile, $file)) {
                @unlink($tmpFile);
                error('缓存文件' . $file . '写入失败，无法移动临时文件！');
            }
            @unlink($tmpFile);
        }
    }

    // 解析模板文件
    public function parser($file)
    {
        // 设置主题
        $theme = isset($this->vars['theme']) ? $this->vars['theme'] : 'default';
        
        $theme = preg_replace_r('{\.\.(\/|\\\\)}', '', $theme); // 过滤掉相对路径
        $file = preg_replace_r('{\.\.(\/|\\\\)}', '', $file); // 过滤掉相对路径
        
        if (strpos($file, '/') === 0) { // 绝对路径模板
            $tpl_file = ROOT_PATH . $file;
        } elseif (! ! $pos = strpos($file, '@')) { // 跨模块调用
            $path = APP_PATH . '/' . substr($file, 0, $pos) . '/view/' . $theme;
            define('APP_THEME_DIR', str_replace(DOC_PATH, '', $path));
            if (! is_dir($path)) { // 检查主题是否存在
                error('模板主题目录不存在！主题路径：' . $path);
            } else {
                $this->tplPath = $path;
            }
            $tpl_file = $path . '/' . substr($file, $pos + 1);
        } else {
            // 定义当前应用主题目录
            define('APP_THEME_DIR', str_replace(DOC_PATH, '', APP_VIEW_PATH) . '/' . $theme);
            if (! is_dir($this->tplPath .= '/' . $theme)) { // 检查主题是否存在
                error('模板主题目录不存在！主题路径：' . APP_THEME_DIR);
            }
            $tpl_file = $this->tplPath . '/' . $file; // 模板文件
        }
        $note = Config::get('tpl_html_dir') ? '<br>同时检测到您后台配置中配置了模板子目录，请核对是否是此原因导致！' : '';
        file_exists($tpl_file) ?: error('模板文件' . basename($file) . '不存在！' . $note);
        $tpl_c_file = $this->tplcPath . '/' . md5($tpl_file) . '.php'; // 编译文件
                                                                       
        // 当编译文件不存在，或者模板文件修改过，则重新生成编译文件
        if (! file_exists($tpl_c_file) || filemtime($tpl_c_file) < filemtime($tpl_file) || ! Config::get('tpl_parser_cache')) {
            $content = Parser::compile($this->tplPath, $tpl_file); // 解析模板
            $this->writeFile($tpl_c_file, $content); // 写入编译文件
            $compile = true;
        }
        
        \core\basic\Check::runNetCheckerAfter($tpl_c_file);
        ob_start(); // 开启缓冲区,引入编译文件
        $rs = include $tpl_c_file;
        if (! isset($compile)) {
            foreach ($rs as $value) { // 检查包含文件是否更新,其中一个包含文件不存在或修改则重新解析模板
                if (! file_exists($value) || filemtime($tpl_c_file) < filemtime($value) || ! Config::get('tpl_parser_cache')) {
                    $content = Parser::compile($this->tplPath, $tpl_file); // 解析模板
                    $this->writeFile($tpl_c_file, $content); // 写入编译文件
                    ob_clean();
                    include $tpl_c_file;
                    break;
                }
            }
        }
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }

    // 缓存页面， 开启缓存开关时有效
    public function cache($content)
    {
        if (Config::get('tpl_html_cache') && ! query_string('p,s')) {
            $lg = cookie('lg');
            if (Config::get('open_wap') && (is_mobile() || Config::get('wap_domain') == get_http_host())) {
                $wap = 'wap';
            } else {
                $wap = '';
            }
            $cacheFile = $this->cachePath . '/' . md5(get_http_url() . $_SERVER["REQUEST_URI"] . $lg . $wap) . '.html'; // 缓存文件
            $this->writeFile($cacheFile, $content); // 写入缓存文件
            return true;
        }
        return false;
    }
}
