<?php

/*
Plugin Name: Coinilla for WooCommerce
Version: 1.0
Description: coinilla.com bitcoin payment processing.
Plugin URI: https://www.coinilla.com/
Author: Coinilla
Author URI: https://www.coinilla.com/
*/

add_action('plugins_loaded', 'woocommerce_coinilla_init', 0);

function woocommerce_coinilla_init() 
{
    if (!class_exists( 'WC_Payment_Gateway' ) ) return;

	
	function CoinillaWOO_Add($methods) {
		$methods[] = 'CoinillaWOO';
		return $methods;
	}
	add_filter('woocommerce_payment_gateways', 'CoinillaWOO_Add');	
	
	class CoinillaWOO extends WC_Payment_Gateway {

	function WC_Coinilla_pay($apikey, $amount, $currency, $refnum, $desc, $return_url) {

	$payment_url = "https://www.coinilla.com/pay";

	$func = 1; // should be 1
	$data = http_build_query(
		array(
			'func'	=> $func,
			'apikey' => $apikey,
			'amount' => $amount,
			'refnum' => $refnum,
			'currency' => $currency,
			'return_url' => $return_url,
			'duplicate_refnum' => 1,
			'desc' => $desc
		)
	);
	
	$options = array('http' =>
    	array(
        'method'  => 'POST',
        'header'  => 'Content-type: application/x-www-form-urlencoded',
        'content' => $data
    	)
	);
	
	$context  = stream_context_create($options);
	
	// post request
	$result = file_get_contents($payment_url, false, $context);
	$result = @json_decode($result, true);
	
	
	if(is_array($result)) {
		if(strlen($result['invoice'])==64) {
			return array('',$payment_url."?invoice=".urlencode($result['invoice']));
		} else {
			return array($result['error'],'');
		}
	} else {
		return array('could not connect to server.','');
	}
	
	return array('API setup is incorrect.','');
	}

	function WC_Coinilla_verify($apikey, $refnum, $invoice) {
	// setup payment call
	$payment_url = "https://www.coinilla.com/verify";
	$func = 3; // should be 3
	
	$data = http_build_query(
		array(
			'func'	=> $func,
			'apikey' => $apikey,
			'refnum' => $refnum,
			'invoice' => $invoice
		)
	);
	
	$options = array('http' =>
    	array(
        'method'  => 'POST',
        'header'  => 'Content-type: application/x-www-form-urlencoded',
        'content' => $data
    	)
	);
	
	$context  = stream_context_create($options);
	
	// post request
	$result = file_get_contents($payment_url, false, $context);
	$result = @json_decode($result, true);
	
	if(is_array($result)) {
			return $result;
	} else {
		return array('error'=>'error in connection','status'=>'unknow');
	}
		
	}

		public function __construct(){
			  $this->id = 'coinilla';
			  $this->method_title = 'Coinilla';
			  $this->method_description = 'Coinilla settings';
			  $this -> icon = get_site_url().'/wp-content/plugins/coinilla-for-woocommerce/logo.png';
			  $this->has_fields = false;
			  $this->init_form_fields();
			  $this->init_settings();
			  $this->title = $this->settings['title'];
			  $this->apikey=$this->settings['apikey'];
			  $this->msg_Success=$this->settings['msg_Success'];
			  $this->msg_Fail=$this->settings['msg_Fail'];
			  $this->msg_Pending=$this->settings['msg_Pending'];
			  
			  if ( version_compare( WOOCOMMERCE_VERSION,'2.0.0', '>=')){
				  add_action( 'woocommerce_update_options_payment_gateways_'  . $this->id, array( $this, 'process_admin_options' ) );
			  } else {
				  add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
			  }
			  add_action('woocommerce_api_'  . strtolower(get_class($this)).'', array($this, 'CoinillaWOO_Response'));
			  add_action('woocommerce_receipt_'  . $this->id, array($this, 'CoinillaWOO_Request'));
		}
				
		public function init_form_fields(){
			$this->form_fields = array(
               		'enabled' => array(
						'title' => 'Enable',
						'type' => 'checkbox',
						'label' => '',
						'default' => 'yes'
					),
					'title' => array(
						'title'       => 'Title',
						'type'        => 'text',
						'default'     => 'Coinilla (pay with Bitcoin)',
					),
					'description' => array(
						'title'       => 'Description',
						'type'        => 'text',
						'default'     => 'Pay with Bitcoin',
					),
					'apikey' => array(
						'title'       => 'API KEY',
						'type'        => 'text',
					),
					'msg_Success' => array(
						'title'       => 'Successful',
						'type'        => 'textarea',
						'description' => 'Enter successful payment message here. You can use {tans_id} to get transaction id, {address} to get bitcoin address, {invoice} to get invoice id and {refnum} to get reference Id.',
						'default'     => 'Payment was successful! Order Id: {refnum}',
					),
               		'processpendingstatus' => array(
						'title' => 'Pending transaction status as Successful',
						'type' => 'checkbox',
						'description' => 'Process 0 confirmed transaction as Successful transaction?',
						'label' => '',
						'default' => 'no'
					),
					'msg_Pending' => array(
						'title'       => 'Pending',
						'type'        => 'textarea',
						'description' => 'Enter pending (unconfirmed) payment message here. You can use {tans_id} to get transaction id, {address} to get bitcoin address, {invoice} to get invoice id and {refnum} to get reference Id.',
						'default'     => 'Payment pending for manual review. please wait for contact from us. invoice Id: {invoice}',
					),
					'msg_Fail' => array(
						'title'       => 'Failed',
						'type'        => 'textarea',
						'description' => 'Enter failed payment message here. You can use {tans_id} to get transaction id, {address} to get bitcoin address, {invoice} to get invoice id and {refnum} to get reference Id.',
						'default'     => 'Payment failed! Order Id: {refnum}',
					),
				);
		}
		
		public function admin_options()
		{
			if ( $this->is_valid_for_use() ) {
				echo '<h3>'.__('Coinilla Settings').'</h3>';
				echo '<hr><table class="form-table">';
				$this->generate_settings_html();
				echo '</table>';
			} else {
				echo '<div class="inline error"><p><strong>'.__( 'Gateway Disabled').'</strong>: '.__( 'PayMeBts does not support your store currency at the moment. You may change your store currency to fix this issue.' ).'</p></div>';
			}
		}
		
		public function CoinillaWOO_Response()
		{
			global $woocommerce;
			
			
			$order_id = $woocommerce->session->order_id;
			$order = new WC_Order($order_id);
			if($order_id != '' && $order_id == $_POST['refnum'])
			{
				$_Msg=array();
				
				if($order->status !='completed')
				{
						$invoice = sanitize_text_field(isset($_POST['invoice'])?$_POST['invoice']:'');
						$refnum = sanitize_text_field(isset($_POST['refnum'])?$_POST['refnum']:'');
						$trans_id = sanitize_text_field(isset($_POST['trans_id'])?$_POST['trans_id']:'');
						$address = sanitize_text_field(isset($_POST['address'])?$_POST['address']:'');
						update_post_meta($order_id, 'WC_coinilla_invoice', $invoice);
						update_post_meta($order_id, 'WC_coinilla_refnum', $refnum);
						$result = $this->WC_Coinilla_verify($this->apikey, $refnum, $invoice);
						
						if($result['status'] == 'paid' || $result['status'] == 'paid-0') {
							if($_POST['state']=='OK' || ($this->settings['processpendingstatus']=='yes' && $_POST['state']=='PENDING'))
							{
								@session_start();
								$params['token'] =  $_SESSION['token'];
								$_Msg['message'] = $this->msg_Success;
								$_Msg['message'] = str_replace("{invoice}",$invoice,$_Msg['message']);
								$_Msg['message'] = str_replace("{refnum}",$refnum,$_Msg['message']);
								$_Msg['message'] = str_replace("{trans_id}",$trans_id,$_Msg['message']);
								$_Msg['message'] = str_replace("{address}",$address,$_Msg['message']);
								$_Msg['class'] = 'success';
								$order->payment_complete();
								$woocommerce->cart->empty_cart();
								
								$Notice = wpautop(wptexturize($_Msg['message']));
								wc_add_notice( $Notice , 'success');
								wp_redirect( add_query_arg( 'wc_status', 'success', $this->get_return_url( $order ) ) );
								exit;
							}
							
							if($_POST['state']=='PENDING' && $this->settings['processpendingstatus']=='no')
							{
								@session_start();
								$params['token'] =  $_SESSION['token'];
								$_Msg['message'] = $this->msg_Pending;
								$_Msg['message'] = str_replace("{invoice}",$invoice,$_Msg['message']);
								$_Msg['message'] = str_replace("{refnum}",$refnum,$_Msg['message']);
								$_Msg['message'] = str_replace("{trans_id}",$trans_id,$_Msg['message']);
								$_Msg['message'] = str_replace("{address}",$address,$_Msg['message']);
								$_Msg['class'] = 'success';
								$order->update_status( 'on-hold',__( 'Transaction unconfirmed or not received enough confirmations yet.' ) );
								$order->add_order_note(__( 'Transaction unconfirmed or not received enough confirmations yet.' ));
								$order->reduce_order_stock();
								$woocommerce->cart->empty_cart();
								$Notice = wpautop(wptexturize($_Msg['message']));						
								wc_add_notice( $Notice , 'pending');
								wp_redirect( add_query_arg( 'wc_status', 'pending', $this->get_return_url( $order ) ) );
								exit;
							}
						} else {
							//canceled
							if($_POST['state']=='CANCELED')
							{
								@session_start();
								$params['token'] =  $_SESSION['token'];
								$_Msg['message'] = $this->msg_Fail;
								$_Msg['message'] = str_replace("{invoice}",$invoice,$_Msg['message']);
								$_Msg['message'] = str_replace("{refnum}",$refnum,$_Msg['message']);
								$_Msg['message'] = str_replace("{trans_id}",$trans_id,$_Msg['message']);
								$_Msg['message'] = str_replace("{address}",$address,$_Msg['message']);
								$_Msg['class'] = 'error';
								$order->update_status( 'cancelled',__( 'Transaction canceled.' ) );
								$order->add_order_note(__( 'Transaction canceled.' ));
								$woocommerce->cart->empty_cart();
								$Notice = wpautop(wptexturize($_Msg['message']));
								wc_add_notice( $Notice , 'cancelled');
								wp_redirect( $woocommerce->cart->get_checkout_url() );
								exit;
							}

							//Failed
								@session_start();
								$params['token'] =  $_SESSION['token'];
								$_Msg['message'] = $this->msg_Fail;
								$_Msg['message'] = str_replace("{invoice}",$invoice,$_Msg['message']);
								$_Msg['message'] = str_replace("{refnum}",$refnum,$_Msg['message']);
								$_Msg['message'] = str_replace("{trans_id}",$trans_id,$_Msg['message']);
								$_Msg['message'] = str_replace("{address}",$address,$_Msg['message']);
								$_Msg['class'] = 'error';
								$order->update_status( 'failed',__( 'Transaction failed.' ) );
								$order->add_order_note(__( 'Transaction failed.' ));
								$woocommerce->cart->empty_cart();
								$Notice = wpautop(wptexturize($_Msg['message']));
								wc_add_notice( $Notice , 'failed');
								wp_redirect( $woocommerce->cart->get_checkout_url() );
								exit;
						}
						
					
					
						wp_redirect(  $woocommerce->cart->get_checkout_url()  );
						exit;
					}
			}
			else
			{
				$Notice = __('Order not found! .', 'woocommerce');
				wc_add_notice($Notice , 'error');
				wp_redirect($woocommerce->cart->get_checkout_url());
				exit;
				
			}
		}


		public function is_valid_for_use() {
			//return true;
			return in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_paypal_supported_currencies', array( 'BTC','USD','EUR','CNY','GBP','CHF','RUB','JPY','INR' ) ) );
		}

		function process_payment($order_id)
		{
			
			$order = new WC_Order($order_id);
			return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url( true )); 
		}

		public function CoinillaWOO_Request($order_id)
		{

			global $woocommerce;
			$order = new WC_Order($order_id);
			unset( $woocommerce->session->order_id );
			$woocommerce->session->order_id = $order_id;
			$amount = $order->order_total;

			$result = $this->WC_Coinilla_pay($this->apikey, $amount, $order->get_order_currency(), $order_id, "Payment for Order ID: ".$order_id, WC()->api_request_url('CoinillaWOO'));

			if($result[0]!='' || $result[1]=='') {
				$form = 'Error: '.$result[0];
			} else {
				$form = '<a class="button alt" href="' . $result[1] . '">' . __( 'Pay Now', 'woocommerce' ) . '</a>&nbsp;&nbsp;&nbsp;
				<a class="button cancel" href="' . $woocommerce->cart->get_checkout_url() . '">' . __( 'Back', 'woocommerce' ) . '</a><br/>';
				
			}
					 
			$form = apply_filters( 'WC_Coinila_Form', $form, $order_id, $woocommerce );	
			do_action( 'WC_Coinila_Before_Form', $order_id, $woocommerce );	
			echo $form;
			do_action( 'WC_Coinila_After_Form', $order_id, $woocommerce );
			
		}
		
	}//-Class
}

