<?php
/**
 * Plugin Name: WooCommerce CartaSi X-Pay Payment Gateway
 * Plugin URI: 
 * Description: Extends WooCommerce with CartaSi X-Pay Payment Gateway.
 * Version: 1.0.1
 * Author: Alian Schiavoncini
 * Text Domain: woo-xpay-payment-gateway
 *
 * Copyright: © 2016 Alian Schiavoncini
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( !function_exists( 'wxpgt_cartasi_init' ) ) { 

	add_action('plugins_loaded', 'wxpgt_cartasi_init', 0);
	
	function wxpgt_cartasi_init() {
	
		if ( !class_exists( 'WC_Payment_Gateway' ) ) return;
	
		class WC_Gateway_CartaSi extends WC_Payment_Gateway {
		
			protected $msg = array();
			
			/**
			 * Constructor for the gateway
			 */
			public function __construct() {
				
				$this->id                 		= 'cartasi';
				$this->icon 			  		= plugins_url( 'images/logo.png' , __FILE__ );
				$this->has_fields         		= true;
				$this->method_title       		= __( 'CartaSi X-Pay', 'woo-xpay-payment-gateway' );
				$this->method_description 		= __( 'CartaSi X-Pay is the most popular credit card payment gateway for online shopping in Italy.', 'woo-xpay-payment-gateway' );
		
				// Load the settings.
				$this->init_form_fields();
				$this->init_settings();
		
				// Define user set variables
				$this->title       				= $this->get_option( 'title' );
				$this->description  			= $this->get_option( 'description' );
				$this->instructions 			= $this->get_option( 'instructions', $this->description );
		
				$this->cartasi_alias 			= $this->settings['cartasi_alias'];
				$this->cartasi_mac 				= $this->settings['cartasi_mac'];
				$this->cartasi_form_language 	= $this->settings['cartasi_form_language'];
				$this->cartasi_test_mode 		= $this->settings['cartasi_test_mode'];
				$this->notify_url 				= str_replace( 'https:', 'http:', home_url( '/wc-api/WC_Gateway_CartaSi' )  );
				$this->liveurl 					= 'https://coll-ecommerce.keyclient.it/ecomm/ecomm/DispatcherServlet';
				$this->productionurl			= 'https://ecommerce.keyclient.it/ecomm/ecomm/DispatcherServlet';
				$this->msg['message'] 			= '';
				$this->msg['class'] 			= '';
		
				// Actions
				add_action( 'woocommerce_api_wc_gateway_cartasi', array($this, 'wxpgt_check_cartasi_response') );
				add_action( 'valid-cartasi-request', array($this, 'successful_request') );
	
				if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
					add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
				} else {
					add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
				}
	
				add_action( 'woocommerce_thankyou_cartasi', array( $this, 'wxpgt_thankyou_page' ) );
				add_action( 'woocommerce_receipt_cartasi', array( $this, 'wxpgt_receipt_page' ) );
		
			} //end __construct
	
			function init_form_fields() {
	
				$cartasi_form_language_ids = array(
													"ITA-ENG" => "ITA-ENG",
													"ITA" => "ITA",
													"ENG" => "ENG",
													"SPA" => "SPA",
													"FRA" => "FRA",
													"GER" => "GER",
													"JPN" => "JPN",
													);
	
				$this->form_fields = array(
					'enabled' => array(
						'title' => __('Enable/Disable', 'woo-xpay-payment-gateway'),
						'type' => 'checkbox',
						'label' => __('Enable CartaSi X-Pay Payment Module.', 'woo-xpay-payment-gateway'),
						'default' => 'no'
						),
					'title' => array(
						'title' => __('Title:', 'woo-xpay-payment-gateway'),
						'type'=> 'text',
						'description' => __('This controls the title which the user sees during checkout.', 'woo-xpay-payment-gateway'),
						'default' => __('CartaSi X-Pay', 'woo-xpay-payment-gateway')
						),
					'description' => array(
						'title' => __('Description:', 'woo-xpay-payment-gateway'),
						'type' => 'textarea',
						'description' => __('This controls the description which the user sees during checkout.', 'woo-xpay-payment-gateway'),
						'default' => __('Pay securely by Credit or Debit card or internet banking through CartaSi X-Pay Secure Servers.', 'woo-xpay-payment-gateway')
						),
					'cartasi_alias' => array(
						'title' => __('CartaSi Alias', 'woo-xpay-payment-gateway'),
						'type' => 'text',
						'description' => __('This id (ALIAS) given to Merchant by CartaSi X-Pay.', 'woo-xpay-payment-gateway')
						),
					'cartasi_mac' => array(
						'title' => __('CartaSi MAC', 'woo-xpay-payment-gateway'),
						'type' => 'text',
						'description' =>  __('Given to Merchant by CartaSi X-Pay.', 'woo-xpay-payment-gateway')
						),
					'cartasi_form_language' => array(
						'title' => __('Language form', 'woo-xpay-payment-gateway'),
						'type' => 'select',
						'options' => $cartasi_form_language_ids,
						'description' =>  __('Select the language for CartaSi X-Pay form', 'woo-xpay-payment-gateway')
						),
					'cartasi_test_mode' => array(
						'title' => __('Enable/Disable TEST Mode', 'woo-xpay-payment-gateway'),
						'type' => 'checkbox',
						'label' => __('Enable CartaSi X-Pay Payment Module in testing mode.', 'woo-xpay-payment-gateway'),
						'default' => 'no'
						),
				);
	
			} //end init_form_fields
	
			/**
			 * Receipt Page
			 **/
			function wxpgt_receipt_page($order_id) {
				echo '<p>'.__('Please click the button below to pay with CartaSi X-Pay.', 'woo-xpay-payment-gateway').'</p>';
				echo $this->wxpgt_generate_cartasi_form($order_id);
			}
	
			/**
			 * Thankyou Page
			 **/
			function wxpgt_thankyou_page($order){
			  if (!empty($this->instructions))
				echo wpautop( wptexturize( $this->instructions ) );
			
			}		
	
			/**
			 * Process the payment and return the result
			 **/
			function process_payment($order_id){
				$order = new WC_Order($order_id);
				update_post_meta($order_id,'_post_data',$_POST);
				return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url( true ));
			}
	
			/**
			 * Check for valid CartaSi X-Pay server callback
			 **/
			function wxpgt_check_cartasi_response() {
				
				global $woocommerce;
	
				$msg['class']   = 'error';
				$msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
				
				if (!isset($_REQUEST)) { return false; }
				
				$bank_reply = false;
				foreach($_REQUEST as $k => $v) {
					$bank_reply .= $k.': '.$v.'<br>';				
				}
	
				if (isset($_REQUEST['codTrans']) && isset($_REQUEST['esito'])) {
					
					$codTrans = $_REQUEST['codTrans'];
					if (strpos($codTrans, '_') !== false) {
						$order_id = explode('_', $codTrans);
						$order_id = (int)$order_id[0];
					}else{
						$order_id = (int)$codTrans;
					}
	
					$esito = $_REQUEST['esito'];
	
					if (isset($_REQUEST['languageId']) && !empty($_REQUEST['languageId'])) {
						$language_id = strtoupper($_REQUEST['languageId']);
					}else{
						$language_id = 'ENG';					
					}
					
					if ($order_id != '') {
	
						try{
	
							$order = new WC_Order($order_id);
	
							$session_time = '';
							$session_order_total = '';
							if (isset($_REQUEST['session_id'])) {
								$session_id = explode("_", $_REQUEST['session_id']);
								$session_time = $session_id[0];
								$session_order_total = $session_id[1];
							}
	
							$order_total = $order->get_total();
	
							$transauthorised = false;
							
							if (($order->status !== 'completed') && ($order_total == $session_order_total)) {
	
								if ($esito == 'OK') {
	
									$transauthorised = true;
									$msg['class'] = 'success';
	
									switch($language_id) {
										case 'ITA' : 
											$msg['message'] = "Grazie per il tuo ordine. La transazione è stata effettuata correttamente. Il tuo ordine verr&agrave; spedito a breve.";
											break;
										case 'ITA-ENG' : 
											$msg['message'] = "Grazie per il tuo ordine. La transazione è stata effettuata correttamente. Il tuo ordine verr&agrave; spedito a breve.";
											$msg['message'] .= "<br>Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.";
											break;
										default : 
											$msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.";
									}
									
									if ($order->status == 'processing') {
	
									}else{
										$order->payment_complete();
										$order->add_order_note('CartaSi X-Pay payment successful<br>Bank Reply Details<br>'.$bank_reply);
										$order->add_order_note($msg['message']);
										$woocommerce->cart->empty_cart();
									}
	
								}else{
									
									$msg['class'] = 'error';
	
									switch($language_id) {
										case 'ITA' : 
											$msg['message'] = "La transazione è stata rifiutata dalla banca. Ti invitiamo a riprovare.";
											break;
										case 'ITA-ENG' : 
											$msg['message'] = "La transazione è stata rifiutata dalla banca. Ti invitiamo a riprovare.";
											$msg['message'] .= "<br>Thank you for shopping with us. However, the transaction has been declined.";
											break;
										default : 
											$msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
									}
	
								}
	
								if ($transauthorised == false) {
									$order->update_status('failed');
									$order->add_order_note('Failed');
									$order->add_order_note($msg['message']);
								}
	
							}
							
						}catch(Exception $e) {
							
							$msg['class'] = 'error';
	
							switch($language_id) {
								case 'ITA' : 
									$msg['message'] = "La transazione è stata rifiutata dalla banca. Ti invitiamo a riprovare.";
									break;
								case 'ITA-ENG' : 
									$msg['message'] = "La transazione è stata rifiutata dalla banca. Ti invitiamo a riprovare.";
									$msg['message'] .= "<br>Thank you for shopping with us. However, the transaction has been declined.";
									break;
								default : 
									$msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
							}
	
						}
	
					}
	
				}
	
	
				if ( function_exists( 'wc_add_notice' ) ) {
					wc_add_notice( $msg['message'], $msg['class'] );
				}else{
					if($msg['class']=='success'){
						$woocommerce->add_message( $msg['message']);
					}else{
						$woocommerce->add_error( $msg['message'] );
					}
					$woocommerce->set_messages();
				}
	
				$redirect_url = $this->get_return_url( $order );
				wp_redirect( $redirect_url );
				exit;
	
			}
			
			/**
			 * Generate CartaSi X-Pay button link
			 **/
			public function wxpgt_generate_cartasi_form($order_id) {
				global $woocommerce;
				$order = new WC_Order($order_id);
				
				//check if in test mode
				$cartasi_test_mode = $this->cartasi_test_mode;
				if ($cartasi_test_mode == 'yes') {
					$importo = 1;
					$order_id = $order_id.'_'.time();
					$form_url = $this->liveurl;
				}else{
					//replace dot float number
					$importo = $order->order_total;
					$importo = str_replace(".", "", $importo);
					$importo = str_replace(",", "", $importo);
					$form_url = $this->productionurl;
				}
				
				//merchant url
				$merchant_url = $this->notify_url;
				
				//session_id
				$session_id = time().'_'.$order->order_total;

				//merchant url
				$url_back = $order->get_cancel_order_url();
				
				//merchant form language
				$language_id = 'ITA-ENG';
				if (isset($this->cartasi_form_language)) {
					$language_id = $this->cartasi_form_language;
				}
				
				//calculate mac code
				$cartasi_mac = $this->cartasi_mac;
				$cartasi_mac = 'codTrans='.$order_id.'divisa=EURimporto='.$importo.$cartasi_mac;
				//$cartasi_mac = 'codTrans=testCILME534divisa=EURimporto=1esempiodicalcolomac';
				$cartasi_mac = sha1($cartasi_mac);
				
				$cartasi_xpay_args = array(
										'alias'						=> $this->cartasi_alias,
										'importo' 					=> $importo,
										'divisa' 					=> 'EUR',
										'codTrans' 					=> $order_id,
										'mail' 						=> $order->billing_email,
										'url' 						=> $merchant_url,
										'session_id' 				=> $session_id,
										'url_back' 					=> $url_back,
										'languageId'				=> $language_id,
										'mac' 						=> $cartasi_mac,
									);
	
				$cartasi_xpay_args_array = array();
				foreach($cartasi_xpay_args as $key => $value) {
					$value = addslashes($value);
					$cartasi_xpay_args_array[] = '<input type="hidden" name="'.$key.'" value="'.$value.'" />'.PHP_EOL;
				}
				$cartasi_xpay_inputs = implode('', $cartasi_xpay_args_array);
				
				$submit_form = '
							<form action="'.$form_url.'" method="post" id="cartasi_xpay_payment_form">
								'.$cartasi_xpay_inputs.'
								<input type="submit" class="button alt" id="submit_cartasi_payment_form" value="'.__('Pay via CartaSi X-Pay', 'woo-xpay-payment-gateway').'" /> 
								<a class="button" style="float:right;" href="'.$url_back.'">'.__('Cancel order &amp; restore cart', 'woo-xpay-payment-gateway').'</a>
							</form>
						
							<script type="text/javascript">
							jQuery( document ).ready(function() {
								
								jQuery(function() {
									
									jQuery("body").block(
									{
										message: "<img src=\"'.WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/images/ajax-loader.gif\" alt=\"Redirecting...\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to CartaSi X-Pay to make the payment.', 'woo-xpay-payment-gateway').'",
											overlayCSS: {
												background: "#fff",
												opacity: 0.6
										},
										css: {
												padding:        20,
												textAlign:      "center",
												color:          "#555",
												border:         "3px solid #aaa",
												backgroundColor:"#fff",
												cursor:         "wait",
												lineHeight:"32px"
										}
									});
				
									jQuery("#submit_cartasi_payment_form").click();
						
								});
		
							});
							</script>
						
						';
						
				return $submit_form;
	
			}
	
		} //end class
	
		/**
		 * Add the Gateway to WooCommerce
		 **/
		function wxpgt_add_cartasi_gateway($methods) {
			$methods[] = 'WC_Gateway_CartaSi';
			return $methods;
		}
		
		add_filter('woocommerce_payment_gateways', 'wxpgt_add_cartasi_gateway' );
	
	} //end wxpgt_cartasi_init()

}
?>