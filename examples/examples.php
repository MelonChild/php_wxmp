<?php
/**
 * @authors Melon (melonchild@outlook.com)
 *
 * @version 1.0
 */
require 'vendor/autoload.php';
use Wxmp\WXMP;
$client = new WXMP($appid, $appsecret);

$articles['title'] = '222test';
$articles['thumb_media_id'] = 'ZnHruPrHB7ttuT3x0l-08ayLgYla05Qnhi_yZBOIRyk';
$articles['author'] = 'melon';
$articles['show_cover_pic'] = 1;
$articles['content_source_url'] = 'http://www.aaaus.org/introduce/37';
$articles['digest'] = '222则默认抓取正文前64个字。';
$articles['content'] = '222图文消息的具体内容，支持HTML标签，必须少于2万字符，小于1M，且此处会去除JS,涉及图片url必须来源 "上传图文消息内的图片获取URL"接口获取。外部图片url将被过滤。<br><img  src="http://mmbiz.qpic.cn/mmbiz_png/tCumbUp7vQao9jAvLzBhkMPhW2cpNeViaAlwdpRYryTk6k0PTXsphQwib20P6Gmx0QPk9S2OKgXZvYgTBCAqLPNg/0?wx_fmt=png" />';

$article[0] = $articles;
$result = $mp->postAddNews($article);
print($result);
