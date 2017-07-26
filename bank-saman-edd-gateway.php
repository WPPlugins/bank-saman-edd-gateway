<?php
/**
Plugin Name: Bank Saman EDD gateway
Version: 1.0
Description: Add Bank Saman gateway to easy digital downloads
Plugin URI: http://pamjad.me/saman-getway-edd
Author: Pouriya Amjadzadeh
Author URI: http://pamjad.me
Donate link: http://pamjad.me/donate
Tags: easy digital downloads,EDD gateways,persian banks
Requires at least: 3.0
Tested up to: 4.6.1
Stable tag: 4.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
**/


function load_textdomain_sedd() {
	load_plugin_textdomain( 'saman_edd', false, dirname( plugin_basename( __FILE__ ) ) . '/langs' ); 
}
add_action( 'init', 'load_textdomain_sedd' );

@session_start();

if ( !function_exists( 'edd_rial' ) ) {
	function edd_rial( $formatted, $currency, $price ) {
		return $price . __('RIAL', 'saman_edd');
	}
	add_filter( 'edd_rial_currency_filter_after', 'edd_rial', 10, 3 );
}

function samanadd_gateway ($gateways) {
	$gateways['saman'] = array('admin_label' => __('Saman Gateway', 'saman_edd'), 'checkout_label' => __('Pay with saman gateway', 'saman_edd'));
	return $gateways;
}
add_filter( 'edd_payment_gateways', 'samanadd_gateway' );

function samancc_form () {
	do_action( 'samancc_form_action' );
}
add_filter( 'edd_saman_cc_form', 'samancc_form' );

function samanprocess_payment ($purchase_data) {
	global $edd_options;
	$payment_data = array(
		'price' => $purchase_data['price'],
		'date' => $purchase_data['date'],
		'user_email' => $purchase_data['post_data']['edd_email'],
		'purchase_key' => $purchase_data['purchase_key'],
		'currency' => $edd_options['currency'],
		'downloads' => $purchase_data['downloads'],
		'cart_details' => $purchase_data['cart_details'],
		'user_info' => $purchase_data['user_info'],
		'status' => 'pending'
	);
	$payment = edd_insert_payment($payment_data);
	if ($payment) {
		$merchant = $edd_options['merchant'];
		$password = $edd_options['password'];
		unset($_SESSION['saman_payment']);
		$amount = str_replace(".00", "", $purchase_data['price']);
		$_SESSION['saman_payment'] = $amount;
		$callBackUrl = add_query_arg('order', 'saman', get_permalink($edd_options['success_page']));
		$send_atu="<script language='JavaScript' type='text/javascript'><!--document.getElementById('checkout_confirmation').submit();//--></script>";
		echo '<form id="checkout_confirmation"  method="post" action="https://sep.shaparak.ir/Payment.aspx" style="margin:0px"  >
		<input type="hidden" id="Amount" name="Amount" value="'.esc_attr($amount).'">
		<input type="hidden" id="MID" name="MID" value="'.esc_attr($merchant).'">
		<input type="hidden" id="ResNum" name="ResNum" value="'.esc_attr($payment).'">
		<input type="hidden" id="RedirectURL" name="RedirectURL" value="'.esc_attr($callBackUrl).'">
		<input type="submit" value="'.__('If you have not redirected click here', '').'"  />
		</form>'.$send_atu ;
		exit;
	} else {
		edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
	}
}
add_action('edd_gateway_saman', 'samanprocess_payment');

function samanverify() {
	global $edd_options;
	if (isset($_GET['order']) and $_GET['order'] == 'saman') {
		if ( !$_POST['ResNum'] OR !$_POST['RefNum'] OR !$_POST['State']){
			$output[status]	= 0;
		} else {
			$ResNum	= sanitize_text_field( $_POST['ResNum'] );
			$RefNum	= sanitize_text_field( $_POST['RefNum'] );
			$State	= sanitize_text_field( $_POST['State'] );
			
			if (isset($RefNum)) {
				if (!class_exists('nusoap_client'))
					require_once("nusoap.php");
				$merchantID = trim($edd_options['merchant']);
				$password	= $edd_options['password'];
				$soapclient = new nusoap_client('https://sep.shaparak.ir/payments/referencepayment.asmx?wsdl','wsdl');
				$soapProxy	= $soapclient->getProxy() ;
				$amount		= $soapProxy->VerifyTransaction($RefNum,$merchantID);
				if (($amount > 0) AND ($State=='OK')) {
					if($amount == $_SESSION['saman_payment']) {
						unset($_SESSION['saman_payment']);
						edd_update_payment_status($ResNum, 'publish');
						edd_send_to_success_page();
					} else {
						$res = $soapProxy->ReverseTransaction($RefNum,$merchantID,$password,$amount);
						$output[status]	= 0;
					}
				} else {
					$output[status]	= 0;
				}
			} else {
				$output[status]	= 0;
			}
		}
		if($output[status]==0){
			edd_update_payment_status(sanitize_text_field( $_POST['ResNum'] ), 'failed');
			$failed_page = get_permalink($edd_options['failure_page']);
			wp_redirect( $failed_page );
			exit;
		}
	}
}
add_action('init', 'samanverify');

function samanadd_settings ($settings) {
	$saman_settings = array (
		array (
			'id'	=>	'saman_settings',
			'name'	=>	__('<strong>Saman Setting</strong>', 'saman_edd'),
			'desc'	=>	__('Setting Saman Bank', 'saman_edd'),
			'type'	=>	'header'
		),
		array (
			'id'	=>	'merchant',
			'name'	=>	__('Merchant code', 'saman_edd'),
			'desc'	=>	'',
			'type'	=>	'text',
			'size'	=>	'regular'
		),
		array (
			'id'	=>	'password',
			'name'	=>	__('Merchant pass', 'saman_edd'),
			'desc'	=>	'',
			'type'	=>	'text',
			'size'	=>	'regular'
		)
	);
	return array_merge( $settings, $saman_settings );
}
add_filter('edd_settings_gateways', 'samanadd_settings');
?>