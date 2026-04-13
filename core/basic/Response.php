<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年11月5日
 *  内容输出类
 */
namespace core\basic;

class Response
{

    // 根据配置文件选择
    public static function handle($data)
    {
        if (Config::get('return_data_type') == 'html') {
            print_r($data);
        } else {
            if (array_key_exists('code', $data)) {
                $code = $data['code'];
                unset($data['code']);
                self::json($code, $data);
            } else {
                self::json(1, $data);
            }
        }
    }

    // 服务端API返回JSON数据
    public static function json($code, $data, $tourl = null)
    {
        @ob_clean();
        $output['code'] = $code ?: 0;
        $output['data'] = $data ?: array();
        $output['tourl'] = $tourl ?: "";
        
        if (defined('ROWTOTAL')) {
            $output['rowtotal'] = ROWTOTAL;
            $output['totalCount'] = ROWTOTAL;
            $output['totalPages'] = PAGECOUNT;
            $output['pageSize'] = PAGESIZE;
            $output['pageIndex'] = PAGE;
        } else {
            // 兼容对象类型：仅在数组或实现 Countable 接口时使用 count()
            if (is_array($data) || $data instanceof \Countable) {
                $output['rowtotal'] = count($data);
                $output['totalCount'] = 0;
                $output['totalPages'] = 0;
                $output['pageSize'] = 0;
                $output['pageIndex'] = 0;
            } else {
                // 对象或标量一律视为单条记录，避免对不可计数对象调用 count()
                $output['rowtotal'] = 1;
                $output['totalCount'] = 0;
                $output['totalPages'] = 0;
                $output['pageSize'] = 0;
                $output['pageIndex'] = 0;
            }
        }
        }
        
        if (PHP_VERSION >= 5.4) { // 中文不编码 5.4+
            $option = JSON_UNESCAPED_UNICODE;
        } else {
            $option = JSON_HEX_TAG;
        }
        echo json_encode($output, $option);
        exit();
    }
}