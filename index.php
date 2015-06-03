<?php
/*
Plugin Name: WooCommerce CCAvenue gateway
Plugin URI: http://www.mrova.com/
Description: Extends WooCommerce with mrova ccavenue gateway.
Version: 1.2.4
Author: mRova
Author URI: http://www.mrova.com/

Copyright: © 2009-2014 mRova.
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
if (!defined('ABSPATH')) {
	exit;
}

add_action('plugins_loaded', 'woocommerce_mrova_ccave_init', 0);

function woocommerce_mrova_ccave_init() {

	if (!class_exists('WC_Payment_Gateway')) {
		return;
	}

	/**
	 * Gateway class
	 */
	class WC_Mrova_Ccave extends WC_Payment_Gateway {
		public function __construct() {
			// Go wild in here
			$this->id = 'ccavenue';
			$this->method_title = __('CCAvenue', 'mrova');
			$this->icon = plugins_url('images/logo.gif', __FILE__);
			$this->has_fields = false;

			$this->init_form_fields();
			$this->init_settings();

			$this->title = $this->settings['title'];
			$this->description = $this->settings['description'];
			$this->merchant_id = $this->settings['merchant_id'];
			$this->working_key = $this->settings['working_key'];
			$this->access_code = $this->settings['access_code'];

			$this->liveurl = 'https://secure.ccavenue.com/transaction/transaction.do?command=initiateTransaction';
			$this->notify_url = str_replace('https:', 'http:', home_url('/wc-api/WC_Mrova_Ccave'));

			$this->msg['message'] = "";
			$this->msg['class'] = "";
			//add_action('init', array(&$this, 'check_ccavenue_response'));
			//update for woocommerce >2.0
			add_action('woocommerce_api_wc_mrova_ccave', array(
				$this,
				'check_ccavenue_response',
			));

			add_action('valid-ccavenue-request', array(
				$this,
				'successful_request',
			));
			if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
				add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
					$this,
					'process_admin_options',
				));
			} else {
				add_action('woocommerce_update_options_payment_gateways', array(&$this,
					'process_admin_options',
				));
			}
			add_action('woocommerce_receipt_ccavenue', array(
				$this,
				'receipt_page',
			));
			add_action('woocommerce_thankyou_ccavenue', array(
				$this,
				'thankyou_page',
			));
		}

		function init_form_fields() {

			$this->form_fields = array(
				'enabled' => array(
					'title' => __('Enable/Disable', 'mrova'),
					'type' => 'checkbox',
					'label' => __('Enable CCAvenue Payment Module.', 'mrova'),
					'default' => 'no',
				),
				'title' => array(
					'title' => __('Title:', 'mrova'),
					'type' => 'text',
					'description' => __('This controls the title which the user sees during checkout.', 'mrova'),
					'default' => __('CCAvenue', 'mrova'),
				),
				'description' => array(
					'title' => __('Description:', 'mrova'),
					'type' => 'textarea',
					'description' => __('This controls the description which the user sees during checkout.', 'mrova'),
					'default' => __('Pay securely by Credit or Debit card or internet banking through CCAvenue Secure Servers.', 'mrova'),
				),
				'merchant_id' => array(
					'title' => __('Merchant ID', 'mrova'),
					'type' => 'text',
					'description' => __('This id(USER ID) available at "Generate Working Key" of "Settings and Options at CCAvenue."'),
				),
				'working_key' => array(
					'title' => __('Working Key', 'mrova'),
					'type' => 'text',
					'description' => __('Given to Merchant by CCAvenue', 'mrova'),
				),
				'access_code' => array(
					'title' => __('Access Code', 'mrova'),
					'type' => 'text',
					'description' => __('Given to Merchant by CCAvenue', 'mrova'),
				),
			);
		}
		/**
		 * Admin Panel Options
		 * - Options for bits like 'title' and availability on a country-by-country basis
		 *
		 */
		public function admin_options() {
			echo '<h3>' . __('CCAvenue Payment Gateway', 'mrova') . '</h3>';
			echo '<p>' . __('CCAvenue is most popular payment gateway for online shopping in India') . '</p>';
			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';
		}
		/**
		 *  There are no payment fields for CCAvenue, but we want to show the description if set.
		 *
		 */
		function payment_fields() {
			if ($this->description) {
				echo wpautop(wptexturize($this->description));
			}

		}
		/**
		 * Receipt Page
		 *
		 */
		function receipt_page($order) {

			echo '<p>' . __('Thank you for your order, please click the button below to pay with CCAvenue.', 'mrova') . '</p>';
			echo $this->generate_ccavenue_form($order);
		}
		/**
		 * Process the payment and return the result
		 *
		 */
		function process_payment($order_id) {
			$order = new WC_Order($order_id);
			return array(
				'result' => 'success',
				'redirect' => $order->get_checkout_payment_url(true),
			);
		}
		/**
		 * Check for valid CCAvenue server callback
		 *
		 */
		function check_ccavenue_response() {
			global $woocommerce;

			$msg['class'] = 'error';
			$msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";

			if (isset($_REQUEST['encResp'])) {

				$encResponse = $_REQUEST["encResp"];
				$rcvdString = decrypt($encResponse, $this->working_key);

				$decryptValues = array();

				parse_str($rcvdString, $decryptValues);
				$order_id_time = $decryptValues['order_id'];
				$order_id = explode('_', $decryptValues['order_id']);
				$order_id = (int) $order_id[0];

				if ($order_id != '') {
					try {
						$order = new WC_Order($order_id);
						$order_status = $decryptValues['order_status'];
						$transauthorised = false;
						if ($order->status !== 'completed') {
							if ($order_status == "Success") {
								$transauthorised = true;
								$msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.";
								$msg['class'] = 'success';
								if ($order->status != 'processing') {
									$order->payment_complete();
									$order->add_order_note('CCAvenue payment successful<br/>Bank Ref Number: ' . $decryptValues['bank_ref_no']);
									$woocommerce->cart->empty_cart();
								}
							} else if ($order_status === "Aborted") {
								$msg['message'] = "Thank you for shopping with us. We will keep you posted regarding the status of your order through e-mail";
								$msg['class'] = 'success';
							} else if ($order_status === "Failure") {
								$msg['class'] = 'error';
								$msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
							} else {
								$msg['class'] = 'error';
								$msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
							}

							if ($transauthorised == false) {
								$order->update_status('failed');
								$order->add_order_note('Failed');
								$order->add_order_note($this->msg['message']);
							}
						}
					} catch (Exception $e) {

						$msg['class'] = 'error';
						$msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
					}
				}
			}

			if (function_exists('wc_add_notice')) {
				wc_add_notice($msg['message'], $msg['class']);
			} else {
				if ($msg['class'] == 'success') {
					$woocommerce->add_message($msg['message']);
				} else {
					$woocommerce->add_error($msg['message']);
				}
				$woocommerce->set_messages();
			}
			$redirect_url = get_permalink(woocommerce_get_page_id('myaccount'));
			wp_redirect($redirect_url);
			exit;
		}
		/*
		//Removed For WooCommerce 2.0
		function showMessage($content){
		return '<div class="box '.$this -> msg['class'].'-box">'.$this -> msg['message'].'</div>'.$content;
		}*/
		/**
		 * Generate CCAvenue button link
		 *
		 */
		public function generate_ccavenue_form($order_id) {
			global $woocommerce;
			$order = new WC_Order($order_id);
			$order_id = $order_id . '_' . date("ymds");
			$ccavenue_args = array(
				'merchant_id' => $this->merchant_id,
				'amount' => $order->order_total,
				'order_id' => $order_id,
				'redirect_url' => $this->notify_url,
				'cancel_url' => $this->notify_url,
				'billing_name' => $order->billing_first_name . ' ' . $order->billing_last_name,
				'billing_address' => mrovaRemoveCharacters(trim($order->billing_address_1, ',')),
				'billing_country' => wc()->countries->countries[$order->billing_country],
				'billing_state' => $order->billing_state,
				'billing_city' => $order->billing_city,
				'billing_zip' => $order->billing_postcode,
				'billing_tel' => $order->billing_phone,
				'billing_email' => $order->billing_email,
				'delivery_name' => $order->shipping_first_name . ' ' . $order->shipping_last_name,
				'delivery_address' => mrovaRemoveCharacters($order->shipping_address_1),
				'delivery_country' => $order->shipping_country,
				'delivery_state' => $order->shipping_state,
				'delivery_tel' => '',
				'delivery_city' => $order->shipping_city,
				'delivery_zip' => $order->shipping_postcode,
				'language' => 'EN',
				'currency' => get_woocommerce_currency(),
			);

			foreach ($ccavenue_args as $param => $value) {
				$paramsJoined[] = "$param=$value";
			}
			$merchant_data = implode('&', $paramsJoined);
			$encrypted_data = encrypt($merchant_data, $this->working_key);
			$ccavenue_args_array = array();
			$ccavenue_args_array[] = "<input type='hidden' name='encRequest' value='$encrypted_data'/>";
			$ccavenue_args_array[] = "<input type='hidden' name='access_code' value='{$this->access_code}'/>";

			wc_enqueue_js('
    $.blockUI({
        message: "' . esc_js(__('Thank you for your order. We are now redirecting you to CcAvenue to make payment.', 'woocommerce')) . '",
        baseZ: 99999,
        overlayCSS:
        {
            background: "#fff",
            opacity: 0.6
        },
        css: {
            padding:        "20px",
            zindex:         "9999999",
            textAlign:      "center",
            color:          "#555",
            border:         "3px solid #aaa",
            backgroundColor:"#fff",
            cursor:         "wait",
            lineHeight:     "24px",
        }
    });
jQuery("#submit_ccavenue_payment_form").click();
');

			$form = '<form action="' . esc_url($this->liveurl) . '" method="post" id="ccavenue_payment_form" target="_top">
' . implode('', $ccavenue_args_array) . '
<!-- Button Fallback -->
<div class="payment_buttons">
    <input type="submit" class="button alt" id="submit_ccavenue_payment_form" value="' . __('Pay via CCAvenue', 'woocommerce') . '" /> <a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">' . __('Cancel order &amp; restore cart', 'woocommerce') . '</a>
</div>
<script type="text/javascript">
    jQuery(".payment_buttons").hide();
</script>
</form>';
			return $form;
		}
		// get all pages
		function get_pages($title = false, $indent = true) {
			$wp_pages = get_pages('sort_column=menu_order');
			$page_list = array();
			if ($title) {
				$page_list[] = $title;
			}

			foreach ($wp_pages as $page) {
				$prefix = '';
				// show indented child pages?
				if ($indent) {
					$has_parent = $page->post_parent;
					while ($has_parent) {
						$prefix .= ' - ';
						$next_page = get_page($has_parent);
						$has_parent = $next_page->post_parent;
					}
				}
				// add to page list array array
				$page_list[$page->ID] = $prefix . $page->post_title;
			}
			return $page_list;
		}
	}
	/**
	 * Add the Gateway to WooCommerce
	 *
	 */
	function woocommerce_add_mrova_ccave_gateway($methods) {
		$methods[] = 'WC_Mrova_Ccave';
		return $methods;
	}

	add_filter('woocommerce_payment_gateways', 'woocommerce_add_mrova_ccave_gateway');
}
/*
ccavenue functions
 */

function encrypt($plainText, $key) {
	$secretKey = hextobin(md5($key));
	$initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
	$openMode = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', 'cbc', '');
	$blockSize = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, 'cbc');
	$plainPad = pkcs5_pad($plainText, $blockSize);
	if (mcrypt_generic_init($openMode, $secretKey, $initVector) != -1) {
		$encryptedText = mcrypt_generic($openMode, $plainPad);
		mcrypt_generic_deinit($openMode);
	}
	return bin2hex($encryptedText);
}

function decrypt($encryptedText, $key) {
	$secretKey = hextobin(md5($key));
	$initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
	$encryptedText = hextobin($encryptedText);
	$openMode = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', 'cbc', '');
	mcrypt_generic_init($openMode, $secretKey, $initVector);
	$decryptedText = mdecrypt_generic($openMode, $encryptedText);
	$decryptedText = rtrim($decryptedText, "\0");
	mcrypt_generic_deinit($openMode);
	return $decryptedText;
}
//*********** Padding Function *********************

function pkcs5_pad($plainText, $blockSize) {
	$pad = $blockSize - (strlen($plainText) % $blockSize);
	return $plainText . str_repeat(chr($pad), $pad);
}
//********** Hexadecimal to Binary function for php 4.0 version ********

function hextobin($hexString) {
	$length = strlen($hexString);
	$binString = "";
	$count = 0;
	while ($count < $length) {
		$subString = substr($hexString, $count, 2);
		$packedString = pack("H*", $subString);
		if ($count == 0) {
			$binString = $packedString;
		} else {
			$binString .= $packedString;
		}

		$count += 2;
	}
	return $binString;
}

function mrovaRemoveCharacters($str) {
	return trim(preg_replace('/ +/', ' ', preg_replace('/[^a-zA-Z0-9,\s]/', '', strip_tags($str))));
}

function mrova_debug($what) {
	echo '<pre>';
	print_r($what);
	echo '</pre>';
}
?>
