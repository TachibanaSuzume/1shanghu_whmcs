<?php

//ini_set('date.timezone','Asia/Shanghai');
//error_reporting(E_ERROR);

require_once "weixin/Eshanghu.php";
require_once "weixin/Eshanghu_jsAPI.php";
 
function weixin_config() {
    if( !empty($GLOBALS["CONFIG"]["SystemSSLURL"]) ) 
    {
        $result = $GLOBALS["CONFIG"]["SystemSSLURL"] . "/";
    }
    else
    {
        if( ($result = $GLOBALS["CONFIG"]["SystemURL"]) ) 
        {
            $result = $GLOBALS["CONFIG"]["SystemURL"] . "/";
        }
        else
        {
            throw new \Exception("无法从全局变量中获取 WHMCS 地址");
        }

    }
    $configarray = array(
     "FriendlyName" => array("Type" => "System", "Value"=>"易商户支付"),
     "app_key" => array("FriendlyName" => "AppKey", "Type" => "text", "Size" => "128", ),
     "app_secret" => array("FriendlyName" => "AppSecret", "Type" => "text", "Size" => "128", ),
     "mch_id" => array("FriendlyName" => "商户号", "Type" => "text", "Size" => "32", ),
     "info_important" => array("FriendlyName" => "请将下方目录加入JSAPI支付目录\n" . $result . "modules/gateways/weixin/", "Description" => "我已照做", "Type" => "yesno"),
    );
    return $configarray;
}

function weixin_link($params) 
{
    # Invoice Variables
    $invoiceid = $params['invoiceid'];
    $description = $params["description"];
    $amount = $params['amount']; # Format: ##.##
    $currency = $params['currency']; # Currency Code
    //echo $invoiceid;
    # System Variables
    $companyname = $params['companyname'];
    $systemurl = $params['systemurl'];
    $timestamp = time();
    
    $wxconfig['app_key'] = $params['app_key'];
    $wxconfig['mch_id'] = $params['mch_id'];
    $wxconfig['app_secret'] = $params['app_secret'];
    $wxconfig['notify'] = $systemurl . "modules/gateways/weixin/notify.php";
    $dataarr = array(
        "invoiceid" => $invoiceid,
        "tamp" => $timestamp,
        "sign" => weixin_safetyCheck("", array( $invoiceid ), true, $timestamp),
    );
    $openidurl = $systemurl . "modules/gateways/weixin/jsapi.php?data=" . str_replace("=", "", base64_encode(json_encode($dataarr)));
    $param['body'] = $companyname . "-" . $invoiceid;
    $param['out_trade_no'] = $invoiceid;
    $param['total_fee'] = $amount * 100;

    require_once 'weixin/Mobile_Detect.php';
    $detect = new Mobile_Detect;
    if($detect->isMobile()){
        $eshanghu = new Eshanghu_jsAPI($wxconfig);
        $openidurl = $eshanghu->getOpenIDUrl($openidurl, $params['app_key'], $params['mch_id']);
        $code = '
        <div id="wximg" class="wx" style="max-width: 230px;margin: 0 auto">
            <img alt="模式二扫码支付" src="' . $systemurl . 'modules/gateways/weixin/qrcode.php?data=' . urlencode($openidurl) . '" style="width:100%;height:100%;"/>
            <a class="wx" href="weixin://scan">
                <img style="width:100%;height:100%;" src="' . $systemurl . 'modules/gateways/weixin/image/jsapi_pay_before.png" border=0>
            </a>
        </div>';
        $code_ajax = '
        <!--微信支付ajax跳转-->
            <script>
            //设置每隔 3000 毫秒执行一次 load() 方法
            setInterval(function(){load()}, 3000);
            function load(){
                var xmlhttp;
                if (window.XMLHttpRequest){
                    // code for IE7+, Firefox, Chrome, Opera, Safari
                    xmlhttp=new XMLHttpRequest();
                }else{
                    // code for IE6, IE5
                    xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
                }
                xmlhttp.onreadystatechange=function(){
                    if (xmlhttp.readyState==4 && xmlhttp.status==200){
                        trade_state=xmlhttp.responseText;
                        if(trade_state=="SUCCESS"){
                            window.location.href="'.$systemurl.'/viewinvoice.php?id='.$invoiceid.'";
                        }
                    }
                }
                //invoice_status.php 文件返回订单状态，通过订单状态确定支付状态
                xmlhttp.open("get","'.$systemurl.'/modules/gateways/weixin/invoice_status.php?invoiceid='.$invoiceid.'",true);
                //下面这句话必须有
                //把标签/值对添加到要发送的头文件。
                //xmlhttp.setRequestHeader("Content-type","application/x-www-form-urlencoded");
                //xmlhttp.send("out_trade_no=002111");
                xmlhttp.send();
            }
        </script>';
        
        $code = $code . $code_ajax;
        return $code;

    }else{
        $eshanghu = new Eshanghu($wxconfig);
        
        // $param["spbill_create_ip"] =$_SERVER['REMOTE_ADDR'];//客户端IP地址
        //调用统一下单API接口

        $result = $eshanghu->create(mt_rand(10000, 99999) . $param['out_trade_no'], $param['body'], $param['total_fee']);
        
        if($result["code"] == 200)
        {
            $url2 = $result['data']["code_url"];
            $link = urlencode($url2);
            $code = '
            <div id="wximg" class="wx" style="max-width: 230px;margin: 0 auto">
                <img alt="模式二扫码支付" src="'.$systemurl.'/modules/gateways/weixin/qrcode.php?data='.$link.'" style="width:190px;height:190px;"/>
            </div>
            <div id="wxDiv" class="wx" style="max-width: 230px;margin: 0 auto">
                <img src="'.$systemurl.'/modules/gateways/weixin/image/logo.png" border=0 width=160>
            </div>
            ';
        
            $code_ajax = '
            <!--微信支付ajax跳转-->
                <script>
                //设置每隔 3000 毫秒执行一次 load() 方法
                setInterval(function(){load()}, 3000);
                function load(){
                    var xmlhttp;
                    if (window.XMLHttpRequest){
                        // code for IE7+, Firefox, Chrome, Opera, Safari
                        xmlhttp=new XMLHttpRequest();
                    }else{
                        // code for IE6, IE5
                        xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
                    }
                    xmlhttp.onreadystatechange=function(){
                        if (xmlhttp.readyState==4 && xmlhttp.status==200){
                            trade_state=xmlhttp.responseText;
                            if(trade_state=="SUCCESS"){
                                document.getElementById("wximg").style.display="none";
                                document.getElementById("wxDiv").innerHTML="支付成功";
                                //延迟 2 秒执行 tz() 方法
                                setTimeout(function(){tz()}, 3000);
                                function tz(){
                                    window.location.href="'.$systemurl.'/viewinvoice.php?id='.$invoiceid.'";
                                }
                            }
                        }
                    }
                    //invoice_status.php 文件返回订单状态，通过订单状态确定支付状态
                    xmlhttp.open("get","'.$systemurl.'/modules/gateways/weixin/invoice_status.php?invoiceid='.$invoiceid.'",true);
                    //下面这句话必须有
                    //把标签/值对添加到要发送的头文件。
                    //xmlhttp.setRequestHeader("Content-type","application/x-www-form-urlencoded");
                    //xmlhttp.send("out_trade_no=002111");
                    xmlhttp.send();
                }
            </script>';
            
            $code = $code . $code_ajax;
            
            if (stristr($_SERVER['PHP_SELF'], 'viewinvoice')) {
                return $code;
            } 
        }    
        
        else
        {
            //echo 2;
            return $result["message"];
        }
    }
    
}


function weixin_refund($params){
    $wxconfig['app_key'] = $params['app_key'];
    $wxconfig['mch_id'] = $params['mch_id'];
    $wxconfig['app_secret'] = $params['app_secret'];
    $order_sn = $params['transid'];
    $eshanghu = new Eshanghu($wxconfig);
    $result = $eshanghu->refund($order_sn);
    
    switch( $result["code"] ) 
    {
        case "200":
            $code = array( "status" => "success", "rawdata" => date("Y-m-d h:i:sa", $timeStamp), "transid" => $refundResult["out_refund_no"], "fees" => $refundResult["refund_fee"] / 100 );
            logTransaction("weixin", json_encode($refundResult), $code["status"]);
            break;
        case "FAIL":
            $code = array( "status" => "error", "rawdata" => date("Y-m-d h:i:sa", $time_stamp) );
            logTransaction("weixin", json_encode($refundResult), $code["status"]);
            break;
    }
    return $code;
}

function weixin_safetyCheck($getSign = "", $vars = array(  ), $get = false, $timestamp)
{
    $getSign = (string) trim($getSign);
    foreach( $vars as $key => $value ) 
    {
        $param .= $value;
    }
    $localSign = base64_encode($timestamp);
    $param .= date("Y-m-d");
    $param .= $localSign;
    if( $get ) 
    {
        return (string) md5($param);
    }

    if( md5($param) != $getSign ) 
    {
        throw new \Exception("安全认证失败，请刷新网页后重试", 101);
    }

    return true;
}

?>