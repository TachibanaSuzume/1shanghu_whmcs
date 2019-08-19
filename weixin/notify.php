<?php

# Required File Includes
include("../../../init.php");
include("../../../includes/functions.php");
include("../../../includes/gatewayfunctions.php");
include("../../../includes/invoicefunctions.php");

require_once "Eshanghu.php";

use Illuminate\Database\Capsule\Manager as Capsule;

$gatewaymodule = "weixin"; # Enter your gateway module name here replacing template
$GATEWAY = getGatewayVariables($gatewaymodule);

$url			= $GATEWAY['systemurl'];
$companyname 	= $GATEWAY['companyname'];
$currency		= $GATEWAY['currency'];

if (!$GATEWAY["type"]) die("Module Not Activated"); # Checks gateway module is active before accepting callback

$wxconfig['app_key'] = $GATEWAY['app_key'];
$wxconfig['mch_id'] = $GATEWAY['mch_id'];
$wxconfig['app_secret'] = $GATEWAY['app_secret'];
$wxconfig['notify'] = '';

$wxpay = new Eshanghu($wxconfig);

if(count($_POST) < 1){
	exit;
}
$verify_result = $wxpay->callback($_POST);

if(!$verify_result) { 
	logTransaction($GATEWAY["name"], $_POST, "Unsuccessful");
	echo("Unsuccessful");
	exit;
}

# Get Returned Variables
$status = $verify_result['status_text'];    //获取易商户传递过来的交易状态
$invoiceid = $verify_result['out_trade_no']; //获取易商户传递过来的订单号
$invoiceid = substr($invoiceid, 5);
$transid = $verify_result['order_sn'];       //获取易商户传递过来的交易号
$amount = $verify_result['total_fee'] / 100;       //获取易商户传递过来的总价格
$fee = 0;

if($status == 'PAID' ) {
	
	$paidcurrency = "CNY"; /////////////////////////////////使用的货币符号
	
	$currency_data     = Capsule::table('tblcurrencies')->where('code',$paidcurrency)->get();
	$paidcurrencyid =  $currency_data[0]->id;

	$result = select_query("tblinvoices", "", array( "id" => $invoiceid ));
	if($result->status != "Paid"){
		$userid = $result->userid;
		$currency = getCurrency( $userid );
		
		if ($paidcurrencyid != $currency['id']) {
			$amount = convertCurrency( $amount, $paidcurrencyid, $currency['id'] );
			$fee = convertCurrency( $fee, $paidcurrencyid, $currency['id'] );
		}
		
		$invoiceid = checkCbInvoiceID($invoiceid, $GATEWAY["name"]); # Checks invoice ID is a valid invoice number or ends processing
		// checkCbTransID($transid); # Checks transaction number isn't already in the database and ends processing if it does
		addInvoicePayment($invoiceid, $transid, $amount, $fee, $gatewaymodule);
		logTransaction($GATEWAY["name"], $_GET,"Successful");
		echo 'success';
	}
}

?>