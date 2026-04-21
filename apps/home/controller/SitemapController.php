<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年7月15日
 *  生成sitemap文件
 */
namespace app\home\controller;

use core\basic\Controller;
use app\home\model\SitemapModel;
use core\basic\Url;
use core\basic\Config;

class SitemapController extends Controller
{

    protected $model;

    public function __construct()
    {
        $this->model = new SitemapModel();
    }

    public function index()
    {
        header("Content-type:text/xml;charset=utf-8");
        $str = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $str .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n" ;
        $str .= $this->makeNode('', date('Y-m-d'), '1.00', 'always'); // 根目录
        
        $sorts = $this->model->getSorts();
        $Parser = new ParserController();
        foreach ($sorts as $value) {
            if ($value->outlink) {
                continue;
            } elseif ($value->type == 1) {
                $link = $Parser->parserLink(1, $value->urlname, 'about', $value->scode, $value->filename);
                $str .= $this->makeNode($link, date('Y-m-d'), '0.80', 'daily');
            } else {
                $link = $Parser->parserLink(2, $value->urlname, 'list', $value->scode, $value->filename);
                $str .= $this->makeNode($link, date('Y-m-d'), '0.80', 'daily');
                $contents = $this->model->getSortContent($value->scode);
                foreach ($contents as $value2) {
                    if ($value2->outlink) { // 外链
                        continue;
                    } else {
                        $link = $Parser->parserLink(2, $value2->urlname, 'content', $value2->scode, $value2->sortfilename, $value2->id, $value2->filename);
                    }
                    $str .= $this->makeNode($link, date('Y-m-d', strtotime($value2->date)), '0.60', 'daily');
                }
            }
        }
        echo $str . "\n</urlset>";
    }

    // 生成结点信息
    private function makeNode($link, $date, $priority = 0.60, $changefreq = 'always')
    {
        $node = '
<url>
    <loc>' . get_http_url() . $link . '</loc>' . $this->makeAlternateLinks($link) . '
    <priority>' . $priority . '</priority>
    <lastmod>' . $date . '</lastmod>
    <changefreq>' . $changefreq . '</changefreq>
</url>';
        return $node;
    }

    // 生成 alternate hreflang 链接
    private function makeAlternateLinks($link)
    {
        $lgs = Config::get('lgs') ?: [];
        $html = '';
        foreach ($lgs as $code => $config) {
            $url = $this->buildLanguageUrl($link, $code);
            $html .= "\n    " . '<xhtml:link rel="alternate" hreflang="' . $code . '" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" />';
        }
        $default = get_default_lg();
        if ($default && isset($lgs[$default])) {
            $url = $this->buildLanguageUrl($link, $default);
            $html .= "\n    " . '<xhtml:link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" />';
        }
        return $html;
    }

    // 构建指定语言 URL
    private function buildLanguageUrl($link, $lang)
    {
        $link = trim($link);
        $link = preg_replace('#^https?://[^/]+#i', '', $link);
        $link = trim($link, '/');
        $siteDir = trim(SITE_INDEX_DIR, '/');
        if ($siteDir && strpos($link, $siteDir) === 0) {
            $link = trim(substr($link, strlen($siteDir)), '/');
        }
        $lgs = Config::get('lgs') ?: [];
        if ($lgs) {
            $langs = implode('|', array_map('preg_quote', array_keys($lgs)));
            $link = preg_replace('#^(' . $langs . ')(/|$)#i', '', $link);
        }
        $result = get_http_url();
        if ($siteDir) {
            $result .= '/' . $siteDir;
        }
        $result .= '/' . $lang;
        if ($link !== '') {
            $result .= '/' . $link;
        } else {
            $result .= '/';
        }
        return $result;
    }

    // 文本格式
    public function linkTxt()
    {
        header("Content-type:text/plain;charset=utf-8");
        $sorts = $this->model->getSorts();
        $Parser = new ParserController();
        $str = get_http_url() . "\n";
        foreach ($sorts as $value) {
            if ($value->outlink) {
                continue;
            } elseif ($value->type == 1) {
                $link = $Parser->parserLink(1, $value->urlname, 'about', $value->scode, $value->filename);
                $str .= get_http_url() . $link . "\n";
            } else {
                $link = $Parser->parserLink(2, $value->urlname, 'list', $value->scode, $value->filename);
                $str .= get_http_url() . $link . "\n";
                $contents = $this->model->getSortContent($value->scode);
                foreach ($contents as $value2) {
                    if ($value2->outlink) { // 外链
                        continue;
                    } else {
                        $link = $Parser->parserLink(2, $value2->urlname, 'content', $value2->scode, $value2->sortfilename, $value2->id, $value2->filename);
                    }
                    $str .= get_http_url() . $link . "\n";
                }
            }
        }
        echo $str;
    }
}