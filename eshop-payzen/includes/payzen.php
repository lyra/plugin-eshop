<?php
#####################################################################################################
#
#					Module pour la plateforme de paiement PayZen
#						Version : 1.0b (révision 47247)
#									########################
#					Développé pour eShop
#						Version : 6.3.1
#						Compatibilité plateforme : V2
#									########################
#					Développé par Lyra Network
#						http://www.lyra-network.com/
#						22/05/2013
#						Contact : support@payzen.eu
#
#####################################################################################################

global $wpdb, $wp_query, $wp_rewrite, $blog_id, $eshopoptions;

// required files ...
require_once (ESHOP_PAYZEN_PLUGIN_PATH . 'includes/payzen.class.php');
require_once (ESHOP_PATH . 'cart-functions.php');

// initiate an instance of the class
$payzen = new payzen_class(); 

// admin configuration error
$configError = __('There appears to have been an error, please contact the site admin', 'payzen');

if($eshopoptions['checkout'] != '') {
	$payzen->autoredirect = add_query_arg('eshopaction', 'redirect', get_permalink($eshopoptions['checkout']));
} else {
	die('<p>' . $configError . '</p>');
}

// activate logging ? 
$payzen->debug = ($eshopoptions['payzen']['payzen_debug'] == 'True');

$eshopaction = $wp_query->query_vars['eshopaction'];
if(!$eshopaction) {
	$eshopaction = 'process'; // default action is 'process'
}

switch ($eshopaction) {
	case 'process' : 
		// Process and order ...
		
		$payzen->add_field('shipping_tax', eshopShipTaxAmt());
		
		$statesTbl = $wpdb->prefix . 'eshop_states';
		$shipState = $wpdb->get_var("SELECT stateName FROM $statesTbl WHERE id = '" . $_POST['ship_state'] . "'");
		if(!$shipState) {
			$shipState = $_POST['ship_altstate'];
		}
		$_POST['ship_state'] = $shipState;
		unset($_POST['ship_altstate']);
		
		$billState = $wpdb->get_var("SELECT stateName FROM $statesTbl WHERE id = '" . $_POST['state'] . "'");
		if(!$billState) {
			$billState = $_POST['altstate'];
		}
		$_POST['state'] = $billState;
		unset($_POST['altstate']);
		
		foreach($_POST as $key => $value) {
			//have to do a discount code check here - otherwise things just don't work - but fine for free shipping codes
			if(strstr($key, 'amount_')) {
				if(isset($_SESSION['eshop_discount' . $blog_id]) && eshop_discount_codes_check()) {
					$chkcode = valid_eshop_discount_code($_SESSION['eshop_discount' . $blog_id]);
					if($chkcode && apply_eshop_discount_code('discount') > 0) {
						$discount = apply_eshop_discount_code('discount') / 100;
						$value = $value - ($value * $discount);
						$vset = 'yes';
					}
				}
				
				if(is_discountable(calculate_total()) != 0 && !isset($vset)) {
					$discount = is_discountable(calculate_total()) / 100;
					$value = $value - ($value * $discount);
				}
				
				// amending for discounts
				$_POST[$key] = $value;
			}
			
			$payzen->add_field($key, $value);
		}
		
		if('yes' == $eshopoptions['downloads_only']) {
			$payzen->add_field('no_shipping', true);
		}
		
		// check amount restrictions
		if(($eshopoptions['status'] != 'live' && is_user_logged_in() &&  current_user_can('eShop_admin')) || $eshopoptions['status'] == 'live') {
			// show review form
			$echoit .= $payzen->submit_payzen_post();
		}
		break;
	
		
    case 'redirect':

    	// auto-redirect bits
		header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP/1.1
		header('Expires: Sun, 01 Jul 2005 00:00:00 GMT');
		header('Pragma: no-cache'); // HTTP/1.0

		// a sequence number is randomly generated
		$checkID = md5(uniqid(rand()));
		
		//affiliates
		if(isset($_COOKIE['ap_id'])) {
			$_POST['affiliate'] = $_COOKIE['ap_id'];
		}
		orderhandle($_POST, $checkID);
		if(isset($_COOKIE['ap_id'])) {
			unset($_POST['affiliate']);
		}
		
		$post = array();
		
		$currency = $payzen->get_api()->findCurrencyByAlphaCode($eshopoptions['currency']);
		if(!$currency) {
			// default PayZen currency
			$payzen->log('Currency '. $eshopoptions['currency'] . ' is not supported. Use PayZen default currency: EUR.');
			$currency = $payzen->get_api()->findCurrencyByAlphaCode('EUR');
		}
		$post['currency'] = $currency->num;
		
		// detect language
		$locale = get_locale() ? substr(get_locale(), 0, 2) : null;
		if($locale && $payzen->get_api()->isSupportedLanguage($locale)) {
			$post['language'] = strtolower($locale);
		} else {
			$post['language'] = $eshopoptions['payzen']['payzen_language'];;
		}
		
		$amount = $_POST['amount'] + $_POST['shipping_tax'];
		$post['amount'] = $currency->convertAmountToInteger($amount);
		
		// get order id and user id
		$ordersTbl = $wpdb->prefix . 'eshop_orders';
		$order = $wpdb->get_row("SELECT id, user_id FROM $ordersTbl WHERE checkid = '" . $checkID . "'", ARRAY_A);
		
		$post['order_id'] = $order['id'];
		$post['order_info'] = 'check_id=' . $checkID . '&session_id=' . session_id();
		$post['cust_email'] = $_POST['email'];
		$post['cust_id'] = $order['user_id'];
		
		// include an unmodified $wp_version
		include ABSPATH . WPINC . '/version.php'; 
		$version = $wp_version . '-' . $eshopoptions['version'];
		$post['contrib'] = 'eShop6.3.1_1.0b/' . $version;
		
		$post['cust_first_name'] = $_POST['first_name'];
		$post['cust_last_name'] = $_POST['last_name'];
		$post['cust_address'] = $_POST['address1'] . ' ' . $_POST['address2'];
		$post['cust_zip'] = $_POST['zip'];
		$post['cust_city'] = $_POST['city'];
		$post['cust_phone'] = $_POST['phone'];
		$post['cust_country'] = $_POST['country'];
		$post['cust_state'] = $_POST['state'];
		
		if(!key_exists('no_shipping', $_POST) || !$_POST['no_shipping']) {
			$post['ship_to_name'] = $_POST['ship_name'];
			$post['ship_to_street'] = $_POST['ship_address'];
			$post['ship_to_zip'] = $_POST['ship_postcode'];
			$post['ship_to_city'] = $_POST['ship_city'];
			$post['ship_to_phone_num'] = $_POST['ship_phone'];
			$post['ship_to_country'] = $_POST['ship_country'];
			$post['ship_to_state'] = $_POST['ship_state'];
		}
	
		$params = array('site_id', 'platform_url', 'key_test', 'key_prod', 'ctx_mode', 'shop_name', 'shop_url',
			'capture_delay', 'validation_mode', 'redirect_enabled', 'redirect_success_timeout',
			'redirect_success_message', 'redirect_error_timeout', 'redirect_error_message', 'return_mode'
    	);
    	foreach($params as $param) {
    		$post[$param] = $eshopoptions['payzen']['payzen_' . $param];
    	}
    	
    	$url_base = add_query_arg('eshopaction', 'payzenipn', get_permalink($eshopoptions['cart_success']));
    	$post['url_success'] = add_query_arg('status', md5('success'), $url_base);
    	$post['url_return'] = add_query_arg('status', md5('error'), $url_base);
    	$post['url_cancel'] = add_query_arg('eshopaction', 'cancel', get_permalink($eshopoptions['cart_cancel'])); // get_permalink($eshopoptions['checkout']);    	
    	
    	// available_languages is given as array by eShop
    	$availLangs = $eshopoptions['payzen']['payzen_available_languages'];
    	if(!is_array($availLangs)) {
    		$availLangs = array();
    	}
    	$post['available_languages'] = in_array('', $availLangs) ? '' : implode(';', $availLangs);
    	
    	// payment_cards is given as array by eShop
    	$payCards = $eshopoptions['payzen']['payzen_payment_cards'];
		if(!is_array($payCards)) {
			$payCards = array();
		}
    	$post['payment_cards'] = in_array('', $payCards) ? '' : implode(';', $payCards);
    	
    	// activate 3ds ?
    	$post['threeds_mpi'] = null;
    	$threeds_min_amount = $eshopoptions['payzen']['payzen_min_amount_3ds'];
    	if(isset($threeds_min_amount) && $threeds_min_amount != '' && $amount < $threeds_min_amount) {
    		$post['threeds_mpi'] = 2;
    	}
    	
    	$payzen->log('Preparing data to send to PayZen', $post);
    	
		$echoit .= $payzen->eshop_submit_payzen_post($post);
		break;
      
		
    case 'payzenipn':       // Order payment notify ...
    	$payzenResponse = $payzen->get_ipn_data($eshopoptions['payzen']);
    	
    	$payzen->log('Response received from PayZen.', $payzenResponse->raw_response);
    	
    	$from_server = ($payzenResponse->get('hash') != null);

    	if(!$payzenResponse->isAuthentified()) {
    		$payzen->log('Received invalid response from PayZen: authentication failed.', array(), 'ERROR');
    	
    		if($from_server) {
    			die($payzenResponse->getOutputForGateway('auth_fail'));
    		} else {
    			wp_die(sprintf(__('%s response authentication failure.', 'payzen'), 'PayZen'));
    		}
    	} else {
    		if($payzenResponse->get('ctx_mode') == 'TEST') {
    			$_POST['pass_prod_msg'] = 'yes';
    		}
    		
    		$infos = $payzen->parse_info($payzenResponse->get('order_info'));

    		$orderID = $payzenResponse->get('order_id');
	    	$checkID = $infos['check_id'];
	    	
	    	$transID = $payzenResponse->get('trans_id');
	    	if($payzenResponse->get('ctx_mode') == 'TEST') {
	    		$transID = 'TEST-' . $transID;
	    	}
	    	    	
	    	$ordersTbl = $wpdb->prefix . 'eshop_orders';
	    	$orderInfo = $wpdb->get_row("SELECT id, status, checkid, transid FROM $ordersTbl WHERE id = $orderID LIMIT 1", ARRAY_A);
	    	
	    	if (!isset($orderInfo) || !$orderInfo['id'] || ($orderInfo['checkid'] !== $checkID)) {
	    		$payzen->log('Error: Order #' . $orderID . ' not found or check ID does not match received order info.', array(), 'ERROR');
	    			
	    		if ($from_server) {
	    			die($payzenResponse->getOutputForGateway('order_not_found'));
	    		} else {
	    			wp_die(sprintf(__('Error : order with id #%s cannot be found.', 'payzen'), $orderID));
	    		}
	    		
	    	} elseif($orderInfo['status'] === 'Pending' || $orderInfo['status'] === 'Waiting') {
	    		// Order not processed yet or a failed order payment retry
	    		
	    		// preapre data to send notification mail to merchant
	    		$subject = 'PayZen IPN - ';
	    		
	    		if($payzenResponse->get('ctx_mode') == 'TEST') {
	    			$subject = __('Testing: ', 'payzen') . $subject;
	    		}
	    		
	    		$body =  __('An instant payment notification was received', 'payzen') . "\n";
	    		$body .= "\n" . __('for ', 'payzen') . $orderID . __(' from ', 'payzen') . 'PayZen' . __(' on ', 'payzen') . date('m/d/Y');
	    		$body .= __(' at ', 'payzen') . date('g:i A') . "\n\n" .__('Details', 'payzen') . ":\n";
	    		
	    		foreach ($payzenResponse->raw_response as $key => $value) {
	    			$body .= "\n $key: $value";
	    		}
	    		
	    		if($payzenResponse->isAcceptedPayment()) {
	    			$subject .=  __('Completed Payment', 'payzen');
	    			$body .= "\n\n" . __('The transaction was completed successfully.', 'payzen');
	    		} else {
	    			$subject .= __('Failed Payment', 'payzen');
	    			$body .= "\n\n" . __('The transaction was not completed successfully.', 'payzen');
	    		}
	    		
	    		$subject .= ' - Ref: ' . $transID;
	    		$body .= "\n\n" . __('Regards, Your friendly automated response.', 'payzen') . "\n\n";
	    		
	    		$headers = eshop_from_address();
	    		
	    		$email = $eshopoptions['business'];
	    		if(isset($eshopoptions['business_sec']) && $eshopoptions['business_sec'] != '') {
	    			$email = $eshopoptions['business_sec'];
	    		}
	    		$to = apply_filters('eshop_gateway_details_email', array($email));
	    		
	    		wp_mail($to, $subject, $body, $headers);
	    		
	    		if($payzenResponse->isAcceptedPayment()) {
	    			// Payment completed
	    			$payzen->log('Payment completed successfully.');
	    			
	    			// save transaction ID and update order status
	    			eshop_mg_process_product($transID, $checkID);
	    			
	    			// if payment success, send email to customer
	    			eshop_send_customer_email($checkID, '3');
	    			
	    			// recover session, clear it and empty the cart
	    			session_id($infos['session_id']);
	    			session_start();
	    			
	    			$_SESSION = array();
	    			session_destroy();
	    			
	    			if ($from_server) {
	    				die ($payzenResponse->getOutputForGateway('payment_ok'));
	    			} else {
	    				if($payzenResponse->get('ctx_mode') == 'TEST') {
	    					$_POST['url_check_warn'] = 'yes';
	    				}
	    				
	    				$_POST['trans_id'] = $transID;
	    				$_POST['check_id'] = $checkID;
	    				
	    				// let eshop_payzen_return show succes message
	    			}
	    			
	    		} else {
	   				$payzen->log('Payment failed. ' . $payzenResponse->getLogString());
	    			
	    			// save transaction ID and update order status
	   				eshop_mg_process_product($transID, $checkID, 'Failed');
	    			
	    			if ($from_server) {
	    				die($payzenResponse->getOutputForGateway('payment_ko'));
	    			} else {
	    				// let eshop_payzen_return show failure message
	    			}
	    			
	    		}
	    	} else {
	    		$payzen->log('Order #' . $orderID . ' is already processed.');
	    			
	    		if($payzenResponse->isAcceptedPayment() && ($orderInfo['status'] === 'Completed' || $orderInfo['status'] === 'Sent')) {
	    			// order success registered and payment succes received
	    			if ($from_server) {
	    				die ($payzenResponse->getOutputForGateway('payment_ok_already_done'));
	    			} else {
	    				$_POST['trans_id'] = $transID;
	    				$_POST['check_id'] = $checkID;
	    				
	    				// let eshop_payzen_return show succes message
	    			}
	    			
	    		} elseif(!$payzenResponse->isAcceptedPayment() && $orderInfo['status'] === 'Failed') {
	    			// order failure registered and payment error received
	    			if ($from_server) {
	    				die($payzenResponse->getOutputForGateway('payment_ko_already_done'));
	    			} else {
	    				// let eshop_payzen_return show failure message
	    			}
	    			
	    		} else {
	    			$payzen->log('Error: invalid payment code received for already processed order ' . $orderID, array(), 'ERROR');
	    			
	    			// registered order status not match payment result
	    			if ($from_server) {
	    				die($payzenResponse->getOutputForGateway('payment_ko_on_order_ok'));
	    			} else {
	    				wp_die(sprintf(__('Error : invalid payment code received for already processed order (%s).', 'payzen'), $orderID));
	    			}
	    			
	    		}
	    	}
    	}
    	
		break;
}
?>