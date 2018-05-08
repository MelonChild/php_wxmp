<?php
/*
 *  Copyright (c) 2014 The CCP project authors. All Rights Reserved.
 *
 */
namespace Wxmp;

class WXMP
{
    private $appid; //
    private $appsecret;
    private $accesstoken = '';
    private $expire_in = 0; //accesstoken  过期时间
    private $BodyType = "json"; //包体格式，可填值：json 、xml
    private $enabeLog = true; //日志开关。可填值：true、
    private $Filename = "./mplog.txt"; //日志文件
    private $Handle;
    private $batch; //时间戳


	
	public static function world()
    {
        return 'Hello World!';
    }

    /**
     * 设置应用ID
     *
     * @param AppId 应用ID
     */
    public function setAppId($appid)
    {
        $this->appid = $appid;
    }

    /**
     * 设置应用密匙
     *
     * @param Appsecret 应用密匙
     */
    public function setAppSecret($appsecret)
    {
        $this->appsecret = $appsecret;
    }

    /**
     * 打印日志
     *
     * @param log 日志内容
     */
    public function showlog($log)
    {
        if ($this->enabeLog) {
            fwrite($this->Handle, $log . "\n");
        }
    }

    /**
     * 发起HTTPS请求
     *
     * @param url 请求路径
     * @param data 发送数据
     * @param header 请求头部信息
     * @param post 请求方式  默认为1 post请求   0为get 请求
     */
    public function curl_post($url, $data, $header, $post = 1)
    {
        //初始化curl
        $ch = curl_init();
        //参数设置
        $res = curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POST, $post);
        if ($post) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        $result = curl_exec($ch);
        //连接失败
        if ($result == false) {
            if ($this->BodyType == 'json') {
                $result = "{\"errcode\":\"1001\",\"errmsg\":\"网络错误\"}";
            } else {
                $result = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?><Response><errcode>1001</errcode><errmsg>网络错误</errmsg></Response>";
            }
        }

        curl_close($ch);
        return $result;
    }

    /**
     * 发起HTTPS请求
     *
     * @param url 请求路径
     * @param path 文件相对路径
     */
    public function curl_post_file($url, $path)
    {
        //初始化curl
        $ch = curl_init();
        if (class_exists('\CURLFile')) {
            curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
            $data = array('media' => new \CURLFile(realpath($path))); //>=5.5
        } else {
            if (defined('CURLOPT_SAFE_UPLOAD')) {
                curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
            }
            $data = array('media' => '@' . realpath($path)); //<=5.5
        }
        //参数设置
        $res = curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($ch);
        //连接失败
        if ($result == false) {
            if ($this->BodyType == 'json') {
                $result = "{\"errcode\":\"1001\",\"errmsg\":\"网络错误\"}";
            } else {
                $result = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?><Response><errcode>1001</errcode><errmsg>网络错误</errmsg></Response>";
            }
        }

        curl_close($ch);
        return $result;
    }

    /**
     * 获取access token
     */
    public function getAccessToken()
    {
        //鉴权信息验证，对必选参数进行判空。
        $auth = $this->accAuth();
        if ($auth != "") {
            return $auth;
        }
        $this->showlog("get accesstoken request datetime = " . date('y/m/d h:i') . "\n");

        // 生成请求URL
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$this->appid&secret=$this->appsecret";
        $this->showlog("request url = https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=xxx&secret=xxx \n");

        // 生成包头
        $header = array("Accept:application/$this->BodyType", "Content-Type:application/$this->BodyType;charset=utf-8");

        // 发送请求
        $result = $this->curl_post($url, '', $header, 0);
        $this->showlog("response body = " . $result . "\r\n");
        if ($this->BodyType == "json") { //JSON格式
            $datas = json_decode($result, true);
        } else { //xml格式
            $datas = simplexml_load_string(trim($result, " \t\n\r"));
        }

        //判断是否成功
        if (isset($datas['access_token']) && $datas['access_token']) {
            $this->accesstoken = $datas['access_token'];
            $this->expire_in = time() + $datas['expires_in'] - 120;
            $_SESSION['accesstoken'] = $datas['access_token'];
            $_SESSION['expire_in'] = time() + $datas['expires_in'] - 120;
            $_SESSION['expire_in'] = 0;
        }

        return $datas;
    }

    /**
     * 新增图文素材
     *
     * @param articles 文章数组
     */
    public function postAddNews($articles = array())
    {
        //鉴权信息验证，对必选参数进行判空。
        $auth = $this->accAuth();
        if ($auth != "") {
            return $auth;
        }

        //数据验证，对必选参数进行判空。
        $verify = $this->newsVerify($articles);
        if ($verify != "") {
            return $verify;
        }

        $this->showlog("add news request datetime = " . date('y/m/d h:i') . "\n");

        //判断accesstoken
        if (!$this->accesstoken || $this->expire_in < time()) {
            $result = $this->getAccessToken();

            if (isset($result['errcode'])) {
                return $result;
            }
        }

        // 生成请求URL
        $url = "https://api.weixin.qq.com/cgi-bin/material/add_news?access_token=" . $_SESSION['accesstoken'];
        $this->showlog("request url = https://api.weixin.qq.com/cgi-bin/material/add_news?access_token=ACCESS_TOKEN \n");

        // 生成包头
        $header = array("Accept:application/$this->BodyType", "Content-Type:application/$this->BodyType;charset=utf-8");

        // 发送请求
        $data = json_encode(array('articles' => $articles), JSON_UNESCAPED_UNICODE);
        // dd($data);
        $result = $this->curl_post($url, $data, $header);
        $this->showlog("response body = " . $result . "\r\n");
        if ($this->BodyType == "json") { //JSON格式
            $datas = json_decode($result, true);
        } else { //xml格式
            $datas = simplexml_load_string(trim($result, " \t\n\r"));
        }

        return $datas;
    }

    /**
     * 新增图文素材中图片
     *
     * @param path 文件相对路径， image 2M，支持bmp/png/jpeg/jpg/gif格式
     */
    public function postUploadNewImg($path = '')
    {
        //鉴权信息验证，对必选参数进行判空。
        $auth = $this->accAuth();
        if ($auth != "") {
            return $auth;
        }

        //数据验证，对必选参数进行判空。
        if (!$path || !file_exists($path)) {
            $data = new stdClass();
            $data->errcode = '1201';
            $data->errmsg = '文件不能为空';
            return $data;
        }

        $this->showlog("add news img request datetime = " . date('y/m/d h:i') . "\n");

        //判断accesstoken
        if (!isset($_SESSION['accesstoken']) || $_SESSION['expire_in'] < time()) {

            $result = $this->getAccessToken();

            if (isset($result['errcode'])) {
                return $result;
            }
        }

        // 生成请求URL
        $url = "https://api.weixin.qq.com/cgi-bin/media/uploadimg?access_token=" . $_SESSION['accesstoken'];
        $this->showlog("request url = https://api.weixin.qq.com/cgi-bin/media/uploadimg?access_token=ACCESS_TOKEN \n");

        // 发送请求
        $result = $this->curl_post_file($url, $path);
        $this->showlog("response body = " . $result . "\r\n");
        if ($this->BodyType == "json") { //JSON格式
            $datas = json_decode($result, true);
        } else { //xml格式
            $datas = simplexml_load_string(trim($result, " \t\n\r"));
        }

        return $datas;
    }

    /**
     * 新增其他类型的永久素材
     *
     * @param path 文件相对路径， image 2M，支持bmp/png/jpeg/jpg/gif格式
     * @param type 媒体文件类型，分别有图片（image）、语音（voice）、视频（video）和缩略图（thumb）
     */
    public function postUploadMaterial($path = '', $type = 'image')
    {
        //鉴权信息验证，对必选参数进行判空。
        $auth = $this->accAuth();
        if ($auth != "") {
            return $auth;
        }

        //数据验证，对必选参数进行判空。
        if (!$path || !file_exists($path)) {
            $data = new stdClass();
            $data->errcode = '1201';
            $data->errmsg = '文件不能为空';
            return $data;
        }

        $this->showlog("add material request datetime = " . date('y/m/d h:i') . "\n");

        //判断accesstoken
        if (!isset($_SESSION['accesstoken']) || $_SESSION['expire_in'] < time()) {

            $result = $this->getAccessToken();

            if (isset($result['errcode'])) {
                return $result;
            }
        }

        // 生成请求URL
        $url = "https://api.weixin.qq.com/cgi-bin/material/add_material?access_token=" . $_SESSION['accesstoken'] . "&type=$type";
        $this->showlog("request url = https://api.weixin.qq.com/cgi-bin/material/add_material?access_token=ACCESS_TOKEN&type=TYPE \n");

        // 发送请求
        $result = $this->curl_post_file($url, $path);
        $this->showlog("response body = " . $result . "\r\n");
        if ($this->BodyType == "json") { //JSON格式
            $datas = json_decode($result, true);
        } else { //xml格式
            $datas = simplexml_load_string(trim($result, " \t\n\r"));
        }

        return $datas;
    }

    /**
     * 查找素材
     *
     * @param media_id 要获取的素材的media_id
     */
    public function getMaterial($media_id)
    {
        //鉴权信息验证，对必选参数进行判空。
        $auth = $this->accAuth();
        if ($auth != "") {
            return $auth;
        }

        //数据验证，对必选参数进行判空。
        if (!$media_id) {
            $data = new stdClass();
            $data->errcode = '1201';
            $data->errmsg = 'media_id不能为空';
            return $data;
        }

        $this->showlog("get material request datetime = " . date('y/m/d h:i') . "\n");

        //判断accesstoken
        if (!isset($_SESSION['accesstoken']) || $_SESSION['expire_in'] < time()) {

            $result = $this->getAccessToken();

            if (isset($result['errcode'])) {
                return $result;
            }
        }

        // 生成请求URL
        $url = "https://api.weixin.qq.com/cgi-bin/material/get_material?access_token=" . $_SESSION['accesstoken'];
        $this->showlog("request url = https://api.weixin.qq.com/cgi-bin/material/get_material?access_token=ACCESS_TOKEN \n");

        // 生成包头
        $header = array("Accept:application/$this->BodyType", "Content-Type:application/$this->BodyType;charset=utf-8");

        // 发送请求
        $data = json_encode(array('media_id' => $media_id), JSON_UNESCAPED_UNICODE);

        $result = $this->curl_post($url, $data, $header);
        $this->showlog("response body = " . $result . "\r\n");
        if ($this->BodyType == "json") { //JSON格式
            $datas = json_decode($result, true);
        } else { //xml格式
            $datas = simplexml_load_string(trim($result, " \t\n\r"));
        }

        return $datas;
    }

    /**
     * 删除素材
     *
     * @param media_id 要获取的素材的media_id
     */
    public function deleteMaterial($media_id)
    {
        //鉴权信息验证，对必选参数进行判空。
        $auth = $this->accAuth();
        if ($auth != "") {
            return $auth;
        }

        //数据验证，对必选参数进行判空。
        if (!$media_id) {
            $data = new stdClass();
            $data->errcode = '1201';
            $data->errmsg = 'media_id不能为空';
            return $data;
        }

        $this->showlog("get material request datetime = " . date('y/m/d h:i') . "\n");

        //判断accesstoken
        if (!isset($_SESSION['accesstoken']) || $_SESSION['expire_in'] < time()) {

            $result = $this->getAccessToken();

            if (isset($result['errcode'])) {
                return $result;
            }
        }

        // 生成请求URL
        $url = "https://api.weixin.qq.com/cgi-bin/material/del_material?access_token=" . $_SESSION['accesstoken'];
        $this->showlog("request url = https://api.weixin.qq.com/cgi-bin/material/del_material?access_token=ACCESS_TOKEN \n");

        // 生成包头
        $header = array("Accept:application/$this->BodyType", "Content-Type:application/$this->BodyType;charset=utf-8");

        // 发送请求
        $data = json_encode(array('media_id' => $media_id), JSON_UNESCAPED_UNICODE);

        $result = $this->curl_post($url, $data, $header);
        $this->showlog("response body = " . $result . "\r\n");
        if ($this->BodyType == "json") { //JSON格式
            $datas = json_decode($result, true);
        } else { //xml格式
            $datas = simplexml_load_string(trim($result, " \t\n\r"));
        }

        return $datas;
    }

    /**
     * 更新图文素材
     *
     * @param media_id 要获取的素材的media_id
     * @param article 详情
     * @param index 要更新的文章在图文消息中的位置（多图文消息时，此字段才有意义），第一篇为0
     */
    public function updateMaterial($media_id,$article,$index=0)
    {
        //鉴权信息验证，对必选参数进行判空。
        $auth = $this->accAuth();
        if ($auth != "") {
            return $auth;
        }

        //数据验证，对必选参数进行判空。
        if (!$media_id) {
            $data = new stdClass();
            $data->errcode = '1201';
            $data->errmsg = 'media_id不能为空';
            return $data;
        }

        //数据验证，对必选参数进行判空。
        $articles[0]=$article;
        $verify = $this->newsVerify($articles);
        if ($verify != "") {
            return $verify;
        }

        $this->showlog("get material request datetime = " . date('y/m/d h:i') . "\n");

        //判断accesstoken
        if (!isset($_SESSION['accesstoken']) || $_SESSION['expire_in'] < time()) {

            $result = $this->getAccessToken();

            if (isset($result['errcode'])) {
                return $result;
            }
        }

        // 生成请求URL
        $url = "https://api.weixin.qq.com/cgi-bin/material/update_news?access_token=" . $_SESSION['accesstoken'];
        $this->showlog("request url = https://api.weixin.qq.com/cgi-bin/material/update_news?access_token=ACCESS_TOKEN \n");

        // 生成包头
        $header = array("Accept:application/$this->BodyType", "Content-Type:application/$this->BodyType;charset=utf-8");

        // 发送请求
        $data = json_encode(array('media_id' => $media_id,'index'=>$index,'articles'=>$article), JSON_UNESCAPED_UNICODE);

        $result = $this->curl_post($url, $data, $header);
        $this->showlog("response body = " . $result . "\r\n");
        if ($this->BodyType == "json") { //JSON格式
            $datas = json_decode($result, true);
        } else { //xml格式
            $datas = simplexml_load_string(trim($result, " \t\n\r"));
        }

        return $datas;
    }

    /**
     * 图文素材验证
     *
     * @param articles 文章详情数组
     */
    public function newsVerify($articles)
    {
        $data = new stdClass();
        $wrong = false;

        if (is_array($articles)) {
            foreach ($articles as $key => $article) {
                if (!isset($article['title']) || !$article['title']) {
                    $wrong = true;
                    $data->errcode = '1102';
                    $data->errmsg = '标题不可为空';
                    break;
                }
                if (!isset($article['thumb_media_id']) || !$article['thumb_media_id']) {
                    $wrong = true;
                    $data->errcode = '1103';
                    $data->errmsg = '图文消息的封面图片素材id不可为空';
                    break;
                }
                if (!isset($article['show_cover_pic']) || !$article['show_cover_pic']) {
                    $wrong = true;
                    $data->errcode = '1104';
                    $data->errmsg = '显示封面不可为空';
                    break;
                }
                if (!isset($article['content']) || !$article['content']) {
                    $wrong = true;
                    $data->errcode = '1105';
                    $data->errmsg = '具体内容不可为空';
                    break;
                }
                if (!isset($article['content_source_url']) || !$article['content_source_url']) {
                    $wrong = true;
                    $data->errcode = '1106';
                    $data->errmsg = '原文地址不可为空';
                    break;
                }
            }

        } else {
            $wrong = true;
            $data->errcode = '1101';
            $data->errmsg = '素材参数格式错误';
        }

        $data = $wrong ? $data : '';

        return $data;
    }

    /**
     * 主帐号鉴权
     */
    public function accAuth()
    {

        if ($this->appsecret == "") {
            $data = new stdClass();
            $data->errcode = '1003';
            $data->errmsg = '应用密钥为空';
            return $data;
        }
        if ($this->appid == "") {
            $data = new stdClass();
            $data->errcode = '1002';
            $data->errmsg = '应用ID为空';
            return $data;
        }
    }
}
?>
