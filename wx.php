<?php
    class WeiXin {
      private $appId;
      private $appSecret;

      //如果是企业公众号，请填写：com  个人公众号:person
      private $tag;

      public function __construct($appId, $appSecret,$tag) {
        //init weixin
        $this->appId = $appId;
        $this->appSecret = $appSecret;
        $this->tag =  $tag;
      }

      public function oauthor2($yourUrl){
            if(!isset($_GET['code'])){//如果没有验证，需要去验证一下
                 $yourUrlx = urlencode($yourUrl);
                 $wexinurl = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=' .$this->appId.'&redirect_uri='. $yourUrlx .'&response_type=code&scope=snsapi_userinfo&state=STATE#wechat_redirect';
                 header("Location:".$wexinurl );
            }
            else{//如果已经认证了，也要检查一下code是否是真实的
                $openidurl = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=" . $this->appId . "&secret=" . $this->appSecret . "&code=" . $_GET['code'] . "&grant_type=authorization_code";
                //var_dump( $openidurl  );
                $res = $this->httpGet($openidurl);
               
                if(strpos($res,"errcode") == true ){

                     $yourUrlx = urlencode($yourUrl);
                     $wexinurl = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=' .$this->appId.'&redirect_uri='. $yourUrlx .'&response_type=code&scope=snsapi_userinfo&state=STATE#wechat_redirect';
                     header("Location:".$wexinurl );
                }
            }
      }

      public function getSignPackage() {
        $jsapiTicket = $this->getJsApiTicket();

        // 注意 URL 一定要动态获取，不能 hardcode.
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $url = "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

        $timestamp = time();
        $nonceStr = $this->createNonceStr();

        // 这里参数的顺序要按照 key 值 ASCII 码升序排序
        $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";

        $signature = sha1($string);

        $signPackage = array(
          "appId"     => $this->appId,
          "nonceStr"  => $nonceStr,
          "timestamp" => $timestamp,
          "url"       => $url,
          "signature" => $signature,
          "rawString" => $string
        );
        return $signPackage; 
      }

      private function createNonceStr($length = 16) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
          $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
      }

      private function getJsApiTicket() {


        //我将token放在数据库里面了，你使用的时候根据实际情况来写就可以了
        global $dosql;
        $data = $dosql->GetOne("SELECT * FROM `#@__infoimg` WHERE classid=28 and delstate='' and checkinfo='true' order by  orderid; ");

        if ($data['a_token_expire_time'] < time()) {
          $accessToken = $this->getAccessToken();

          // 如果是企业号用以下 URL 获取 ticket
          $url = "" ;
          if($this->tag == "com"){
              $url = "https://qyapi.weixin.qq.com/cgi-bin/get_jsapi_ticket?access_token=$accessToken";
          }
          else{
              $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=$accessToken";
          }
              $res = json_decode($this->httpGet($url));


          if(isset($res->ticket)){
              $ticket = $res->ticket;
              if ($ticket) {
                $data['a_ticket_expire_time'] = time() + 7000;
                $data['a_ticket'] = $ticket;

                //更新ticket
                $sql = "update  `#@__infoimg` set a_ticket='" . $data['a_ticket'] . "' , a_ticket_expire_time= " . $data['a_ticket_expire_time'] . "  where classid=28 " ;
                $dosql->QueryNone($sql);

              }
           }
        } else {
          $ticket = $data['a_ticket'];
        }

        return $ticket;
      }


      private function getAccessToken() {
         // 这里获得token

        //我将token放在数据库里面了，你使用的时候根据实际情况来写就可以了
        global $dosql;
        $data = $dosql->GetOne("SELECT * FROM `#@__infoimg` WHERE classid=28 and delstate='' and checkinfo='true' order by  orderid; ");


        if ($data['a_token_expire_time'] < time()) {

          // 如果是企业号用以下URL获取access_token
          if($this->tag == "com"){
              $url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=$this->appId&corpsecret=$this->appSecret";
          }
          else{
              $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$this->appId&secret=$this->appSecret";
          }
          $res = json_decode($this->httpGet($url));
          if(isset($res->access_token)){
              $access_token = $res->access_token;
              if ($access_token) {
                $data['a_token_expire_time'] = time() + 7000;
                $data['a_token'] = $access_token;


                //更新token
                $sql = "update  `#@__infoimg` set a_token='" . $data['a_token'] . "' , a_token_expire_time=" . $data['a_token_expire_time'] . "  where classid=28 " ;
                $dosql->QueryNone($sql);

              }
          }
        } else {
          $access_token = $data['a_token'];
        }
        return $access_token;
      }

      private function httpGet($url) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 500);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_URL, $url);

        $res = curl_exec($curl);
        curl_close($curl);
        return $res;
      }
    }


?>
