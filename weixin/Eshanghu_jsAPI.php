<?php

class Eshanghu_jsAPI {
    public $appKey;
    public $appSecret;
    public $subMchId;
    public $notify;

    public function __construct($config)
    {
        $this->appKey = $config['app_key'];
        $this->appSecret = $config['app_secret'];
        $this->subMchId = $config['mch_id'];
        $this->notify = $config['notify'];
    }

   /**
    * 获取OpenID的URL.
    * @param string $url
    *
    */
    public function getOpenIDUrl($url, $appkey, $sub_mch_id){
        return "https://1shanghu.com/v2/wechat/login?app_key=" . $appkey . "&sub_mch_id=" . $sub_mch_id . "&callback=" . $url;
    }

    /**
     * 创建签名.
     *
     * @param string $outTradeNo
     * @param string $subject
     * @param int    $totalFee
     * @param string $extra
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public function create($outTradeNo, $subject, $totalFee, $openid, $extra = '')
    {
        $data = [
            'app_key' => $this->appKey,
            'openid' => $openid,
            'out_trade_no' => $outTradeNo,
            'total_fee' => $totalFee,
            'subject' => $subject,
            'extra' => 'whmcs',
            'notify_url' => $this->notify,
        ];
        $data['sign'] = $this->getSign($data);

        $response = $this->httpPost('https://1shanghu.com/api/wechat/mp', $data);
        $response = json_decode($response, true);
        return $response;
    }

    /**
     * 获取签名.
     *
     * @param array $data
     *
     * @return string
     */
    public function getSign(array $data)
    {
        ksort($data);
        $need = [];
        foreach ($data as $key => $value) {
            if (! $value || $key == 'sign') {
                continue;
            }
            $need[] = "{$key}={$value}";
        }
        $string = implode('&', $need).$this->appSecret;

        return strtoupper(md5($string));
    }

    /**
     * 验证sign.
     *
     * @param array $data
     *
     * @return bool
     */
    public function verifySign(array $data)
    {
        $sign = $data['sign'];

        return strtoupper($sign) === $this->getSign($data);
    }

    /**
     * 异步回调.
     *
     * @param array $data
     *
     * @return array
     *
     * @throws \Exception
     */
    public function callback(array $data)
    {
        if (! $this->verifySign($data)) {
            return false;
        }

        return $data;
    }

    public function httpPost($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Discuz Plugin CLIENT');
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
}