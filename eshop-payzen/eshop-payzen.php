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

/*
Plugin Name: eShop PayZen Gateway
Plugin URI: http://www.lyra-network.com
Description: PayZen Payment gateway for eShop WordPress Plugin.
Author: Lyra Network
Version: 1.0b
Author URI: http://www.lyra-network.com
License:
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

define('ESHOP_PAYZEN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ESHOP_PAYZEN_PLUGIN_PATH', plugin_dir_path(__FILE__));

// check requirements before activating plugin
function eshop_payzen_activate() {
	if (!is_plugin_active('eshop/eshop.php')) {
		deactivate_plugins(plugin_basename( __FILE__ )); // Deactivate ourself
	
		$message = sprintf(__('Sorry! In order to use eShop %s Gateway plugin you need to install and activate the eShop plugin.', 'payzen'), 'PayZen');
		wp_die($message, 'eShop PayZen Gateway Plugin', array('back_link' => true));
	}
}
register_activation_hook(__FILE__, 'eshop_payzen_activate');

// add a link from plugin list to parameters
function eshop_payzen_add_link($links, $file) {
	$links[] = '<a href="'.admin_url('options-general.php?page=eshop-settings.php&mstatus=Merchant#payzen').'">' . __('Settings', 'payzen') .'</a>';
	return $links;
}
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'eshop_payzen_add_link',  10, 2);

// load language for PayZen plugin
function eshop_payzen_load_lang() {
	// load translation files
	load_plugin_textdomain('payzen', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('init', 'eshop_payzen_load_lang', 1);

// load admin module settings
function eshop_payzen_setting_load($settings) {
	add_meta_box('eshop-m-payzen', 'PayZen', 'eshop_payzen_box', $settings->pagehook, 'normal', 'core');
	
	// if payzen_reset is set, reset configuration and reload admin page
	if(!empty($_REQUEST) && key_exists('payzen_reset', $_REQUEST) && $_REQUEST['payzen_reset'] == 'true') {
		$eshopoptions = get_option('eshop_plugin_settings');
		unset($eshopoptions['payzen']);
		update_option('eshop_plugin_settings', $eshopoptions);
		
		$link = add_query_arg('page', 'eshop-settings.php', admin_url('options-general.php'));
		$link = add_query_arg('mstatus', 'Merchant', $link);
		wp_redirect($link);
		die();
	}
}
add_action('eshop_setting_merchant_load', 'eshop_payzen_setting_load');

function eshop_payzen_box($eshopoptions) {
	$eshopoptions = eshop_payzen_set_default_values($eshopoptions);

	// include api file
	require_once (ESHOP_PAYZEN_PLUGIN_PATH . 'includes/payzen_api.php');
	
	$payzenApi = new PayzenApi();
	$languages = $payzenApi->getSupportedLanguages();
	
	$payzen = stripslashes_deep($eshopoptions['payzen']);
	?>
		<a id="payzen"></a>
		
		<fieldset>
			<?php 
				$img_url = ESHOP_PAYZEN_PLUGIN_URL . 'images/payzen_logo.png';
				echo '<p class="eshopgateway"><img src="' . $img_url . '" alt="PayZen" title="PayZen" /></p>'."\n";
			?>
			<p class="cbox">
				<input id="eshop_payzen_enable" name="eshop_method[]" type="checkbox" value="payzen"<?php if(in_array('payzen', (array)$eshopoptions['method'])) echo ' checked="checked"'; ?> />
				<label for="eshop_payzen_enable" class="eshopmethod"><?php echo sprintf(__('Accept payments by %s', 'payzen'),  'PayZen'); ?></label>
			</p>
			
			<?php
				$resetLink = add_query_arg('page', 'eshop-settings.php', admin_url('options-general.php'));
				$resetLink = add_query_arg('mstatus', 'Merchant', $resetLink);
				$resetLink = add_query_arg('payzen_reset', 'true', $resetLink); 
			?>
			<a href="<?php echo $resetLink; ?>"><?php _e('Reset', 'payzen');?></a>
			<small style="font-style: italic; font-size: smaller; padding-left: 15px;"><?php echo(sprintf(__('Click here to reset %s module configuration', 'payzen'), 'PayZen')); ?></small>
			
			<br />
			<br />	
			<fieldset style="border: 2px solid #EEEEEE;">
				<legend style="font-weight: bold; font-size: 13px;"><?php _e('Module informations', 'payzen'); ?></legend>
				<br />
				
				<?php _e('Developped by', 'payzen') ?>: <b><a href="http://www.lyra-network.com/" target="_blank">Lyra-Network</a></b><br/>
				<?php _e('Contact email', 'payzen') ?>: <b><a href="mailto:support@payzen.eu" target="_blank">support@payzen.eu</a></b><br/>
				<?php _e('Module version', 'payzen') ?>: <b>1.0b</b><br/>
				<?php _e('Gateway version', 'payzen') ?>: <b>V2</b><br/>
				<?php _e('Tested with', 'payzen') ?>: <b>eShop 6.3.1</b>
			</fieldset>
				
			<fieldset style="border: 2px solid #EEEEEE;">
				<legend style="font-weight: bold; font-size: 13px;"><?php _e('Payment gateway access', 'payzen'); ?></legend>
				<br />
				
				<label for="eshop_payzen_site_id"><?php _e('Site ID', 'payzen'); ?></label>
				<input id="eshop_payzen_site_id" name="eshop_payzen_site_id" type="text" value="<?php echo $payzen['payzen_site_id']; ?>" size="30" />
				<small style="font-style: italic; font-size: smaller; padding-left: 15px;"><?php _e('Site ID provided by the payment gateway', 'payzen'); ?></small><br />
				
				<label for="eshop_payzen_key_test"><?php _e('Test certificate', 'payzen'); ?></label>
				<input id="eshop_payzen_key_test" name="eshop_payzen_key_test" type="text" value="<?php echo $payzen['payzen_key_test']; ?>" size="30" />
				<small style="font-style: italic; font-size: smaller; padding-left: 15px;"><?php _e('Certificate provided by the gateway', 'payzen'); ?></small><br />
			
				<label for="eshop_payzen_key_prod"><?php _e('Production certificate', 'payzen'); ?></label>
				<input id="eshop_payzen_key_prod" name="eshop_payzen_key_prod" type="text" value="<?php echo $payzen['payzen_key_prod']; ?>" size="30" />
				<small style="font-style: italic; font-size: smaller; padding-left: 15px;"><?php _e('Certificate provided by the gateway', 'payzen'); ?></small><br />
				
				<label for="eshop_payzen_ctx_mode"><?php _e('Mode', 'payzen'); ?></label>
				<select id="eshop_payzen_ctx_mode" name="eshop_payzen_ctx_mode">
					<option value="TEST" <?php if($payzen['payzen_ctx_mode'] == 'TEST') echo('selected="selected"'); ?>>TEST</option>
					<option value="PRODUCTION" <?php if($payzen['payzen_ctx_mode'] == 'PRODUCTION') echo('selected="selected"'); ?>>PRODUCTION</option>
				</select>
				<small style="font-style: italic; font-size: smaller; padding-left: 15px;"><?php _e('The module context mode', 'payzen'); ?></small><br />
				
				<label for="eshop_payzen_platform_url"><?php _e('Gateway URL', 'payzen'); ?></label>
				<input id="eshop_payzen_platform_url" name="eshop_payzen_platform_url" type="text" value="<?php echo $payzen['payzen_platform_url']; ?>" size="65" />
				<small style="font-style: italic; font-size: smaller; padding-left: 15px;"><?php _e('URL the client will be redirected to for payment', 'payzen'); ?></small><br />
				
				<label for="eshop_payzen_debug"><?php _e('Debugging', 'payzen'); ?></label>
				<select id="eshop_payzen_debug" name="eshop_payzen_debug">
					<option value="False" <?php if($payzen['payzen_debug'] == 'False') echo('selected="selected"'); ?>><?php _e('Disabled', 'payzen'); ?></option>
					<option value="True" <?php if($payzen['payzen_debug'] == 'True') echo('selected="selected"'); ?>><?php _e('Enabled', 'payzen'); ?></option>
				</select>
				<small style="font-style: italic; font-size: smaller; padding-left: 15px;"><?php _e('Activate debug and logging', 'payzen'); ?></small><br />
			</fieldset>
		
			<fieldset style="border: 2px solid #EEEEEE;">
				<legend style="font-weight: bold; font-size: 13px;"><?php _e('Payment page', 'payzen'); ?></legend>
				<br />
				
				<label for="eshop_payzen_language"><?php _e('Default language', 'payzen'); ?></label>
				<select id="eshop_payzen_language" name="eshop_payzen_language">
					<?php 
					foreach ($languages as $code => $label) {
						$selected = $payzen['payzen_language'] == $code ? ' selected="selected"' : '';
						echo '<option value="' . $code . '"' . $selected . '>' . __($label, 'payzen') . '</option>';
					}
					?>
				</select>
				<small style="font-style: italic; font-size: smaller; padding-left: 15px;"><?php _e('Default language on the payment page', 'payzen'); ?></small><br />
				
				<label for="eshop_payzen_available_languages"><?php _e('Available languages', 'payzen'); ?></label>
				<select id="eshop_payzen_available_languages" name="eshop_payzen_available_languages[]" multiple="multiple" size="8">
					<?php 
					foreach ($languages as $code => $label) {
						$selected = in_array($code, (array)$payzen['payzen_available_languages']) ? ' selected="selected"' : '';
						echo '<option value="' . $code . '"' . $selected . '>' . __($label, 'payzen') . '</option>';
					}
					?>
				</select>
				<small style="font-style: italic; font-size: smaller; padding-left: 15px;"><?php _e('Available languages on payment page, select none to use gateway config', 'payzen'); ?></small><br />
				
				<label for="eshop_payzen_shop_name"><?php _e('Shop name', 'payzen'); ?></label>
				<input id="eshop_payzen_shop_name" name="eshop_payzen_shop_name" type="text" value="<?php echo $payzen['payzen_shop_name']; ?>" size="30" />
				<small style="font-style: italic; font-size: smaller; padding-left: 15px;"><?php _e('Shop name to display on the payment page, leave blank to use gateway config', 'payzen'); ?></small><br />
				
				<label for="eshop_payzen_shop_url"><?php _e('Shop URL', 'payzen'); ?></label>
				<input id="eshop_payzen_shop_url" name="eshop_payzen_shop_url" type="text" value="<?php echo $payzen['payzen_shop_url']; ?>" size="65" />
				<small style="font-style: italic; font-size: smaller; padding-left: 15px;"><?php _e('Shop URL to display on the payment page, leave blank to use gateway config', 'payzen'); ?></small><br />
				
				<label for="eshop_payzen_capture_delay"><?php _e('Delay', 'payzen'); ?></label>
				<input id="eshop_payzen_capture_delay" name="eshop_payzen_capture_delay" type="text" value="<?php echo $payzen['payzen_capture_delay']; ?>" size="30" />
				<small style="font-style: italic; font-size: smaller; padding-left: 15px;"><?php _e('Delay before banking (in days)', 'payzen'); ?></small><br />
				
				<label for="eshop_payzen_validation_mode"><?php _e('Validation mode', 'payzen'); ?></label>
				<select id="eshop_payzen_validation_mode" name="eshop_payzen_validation_mode">
					<option value="" <?php if($payzen['payzen_validation_mode'] == '') echo('selected="selected"'); ?>><?php _e('Default', 'payzen'); ?></option>
					<option value="0" <?php if($payzen['payzen_validation_mode'] == '0') echo('selected="selected"'); ?>><?php _e('Automatic', 'payzen'); ?></option>
					<option value="1" <?php if($payzen['payzen_validation_mode'] == '1') echo('selected="selected"'); ?>><?php _e('Manual', 'payzen'); ?></option>
				</select>
				<small style="font-style: italic; font-size: smaller; padding-left: 15px;"><?php _e('If manual is selected, you will have to confirm payments manually in your bank backoffice', 'payzen'); ?></small><br />
				
				<label for="eshop_payzen_payment_cards"><?php _e('Available payment cards', 'payzen'); ?></label>
				<select id="eshop_payzen_payment_cards" name="eshop_payzen_payment_cards[]" multiple="multiple" size="8">
					<option value="CB" <?php if(in_array('CB', (array)$payzen['payzen_payment_cards'])) echo('selected="selected"'); ?>>Carte Bleue</option>
					<option value="MASTERCARD" <?php if(in_array('MASTERCARD', (array)$payzen['payzen_payment_cards'])) echo('selected="selected"'); ?>>Mastercard</option>
					<option value="MAESTRO" <?php if(in_array('MAESTRO', (array)$payzen['payzen_payment_cards'])) echo('selected="selected"'); ?>>Maestro</option>
					<option value="VISA" <?php if(in_array('VISA', (array)$payzen['payzen_payment_cards'])) echo('selected="selected"'); ?>>Visa</option>
					<option value="VISA_ELECTRON" <?php if(in_array('VISA_ELECTRON', (array)$payzen['payzen_payment_cards'])) echo('selected="selected"'); ?>>Visa Electron</option>
					<option value="AMEX" <?php if(in_array('AMEX', (array)$payzen['payzen_payment_cards'])) echo('selected="selected"'); ?>>American Express</option>
					<option value="E-CARTEBLEUE" <?php if(in_array('E-CARTEBLEUE', (array)$payzen['payzen_payment_cards'])) echo('selected="selected"'); ?>>E-Carte bleue</option>
				</select>
				<small style="font-style: italic; font-size: smaller; padding-left: 15px;"><?php _e('Select none to use gateway config', 'payzen'); ?></small><br />
				
				<label for="eshop_payzen_min_amount_3ds"><?php _e('Minimum amount for which activate 3DS', 'payzen'); ?></label>
				<input id="eshop_payzen_min_amount_3ds" name="eshop_payzen_min_amount_3ds" type="text" value="<?php echo $payzen['payzen_min_amount_3ds']; ?>" size="30" />
				<small style="font-style: italic; font-size: smaller; padding-left: 15px;"><?php _e('Only if you have subscribed to Selective 3-D Secure option', 'payzen'); ?></small><br />
			</fieldset>
			
			<fieldset style="border: 2px solid #EEEEEE;">
				<legend style="font-weight: bold; font-size: 13px;"><?php _e('Amount restrictions', 'payzen'); ?></legend>
				<br />
				
				<label for="eshop_payzen_amount_min"><?php _e('Minimum amount', 'payzen'); ?></label>
				<input id="eshop_payzen_amount_min" name="eshop_payzen_amount_min" type="text" value="<?php echo $payzen['payzen_amount_min']; ?>" size="30" /><br />
				
				<label for="eshop_payzen_amount_max"><?php _e('Maximum amount', 'payzen'); ?></label>
				<input id="eshop_payzen_amount_max" name="eshop_payzen_amount_max" type="text" value="<?php echo $payzen['payzen_amount_max']; ?>" size="30" /><br />
			</fieldset>
				
			<fieldset style="border: 2px solid #EEEEEE;">
				<legend style="font-weight: bold; font-size: 13px;"><?php _e('Return to shop', 'payzen'); ?></legend>
				<br />
				
				<label for="eshop_payzen_redirect_enabled"><?php _e('Automatic redirection', 'payzen'); ?></label>
				<select id="eshop_payzen_redirect_enabled" name="eshop_payzen_redirect_enabled">
					<option value="False" <?php if($payzen['payzen_redirect_enabled'] == 'False') echo('selected="selected"'); ?>><?php _e('Disabled', 'payzen'); ?></option>
					<option value="True" <?php if($payzen['payzen_redirect_enabled'] == 'True') echo('selected="selected"'); ?>><?php _e('Enabled', 'payzen'); ?></option>
				</select>
				<small style="font-style: italic; font-size: smaller; padding-left: 15px;"><?php _e('If enabled, the client is automatically forwarded to your site at the end of the payment process', 'payzen'); ?></small><br />
				
				<label for="eshop_payzen_redirect_success_timeout"><?php _e('Success forward timeout', 'payzen'); ?></label>
				<input id="eshop_payzen_redirect_success_timeout" name="eshop_payzen_redirect_success_timeout" type="text" value="<?php echo $payzen['payzen_redirect_success_timeout']; ?>" size="30" />
				<small style="font-style: italic; font-size: smaller; padding-left: 15px;"><?php _e('Time in seconds (0-300) before the client is automatically forwarded to your site when the payment was successful', 'payzen'); ?></small><br />
				
				<label for="eshop_payzen_redirect_success_message"><?php _e('Success forward message', 'payzen'); ?></label>
				<input id="eshop_payzen_redirect_success_message" name="eshop_payzen_redirect_success_message" type="text" value="<?php echo $payzen['payzen_redirect_success_message']; ?>" size="65" />
				<small style="font-style: italic; font-size: smaller; padding-left: 15px;"><?php _e('Message posted on the payment platform before forwarding when the payment was successful', 'payzen'); ?></small><br />
				
				<label for="eshop_payzen_redirect_error_timeout"><?php _e('Failure forward timeout', 'payzen'); ?></label>
				<input id="eshop_payzen_redirect_error_timeout" name="eshop_payzen_redirect_error_timeout" type="text" value="<?php echo $payzen['payzen_redirect_error_timeout']; ?>" size="30" />
				<small style="font-style: italic; font-size: smaller; padding-left: 15px;"><?php _e('Time in seconds (0-300) before the client is automatically forwarded to your site when the payment failed', 'payzen'); ?></small><br />
				
				<label for="eshop_payzen_redirect_error_message"><?php _e('Failure forward message', 'payzen'); ?></label>
				<input id="eshop_payzen_redirect_error_message" name="eshop_payzen_redirect_error_message" type="text" value="<?php echo $payzen['payzen_redirect_error_message']; ?>" size="65" />
				<small style="font-style: italic; font-size: smaller; padding-left: 15px;"><?php _e('Message posted on the payment platform before forwarding when the payment failed', 'payzen'); ?></small><br />
				
				<label for="eshop_payzen_return_mode"><?php _e('Return mode', 'payzen'); ?></label>
				<select id="eshop_payzen_return_mode" name="eshop_payzen_return_mode">
					<option value="GET" <?php if($payzen['payzen_return_mode'] == 'GET') echo('selected="selected"'); ?>>GET</option>
					<option value="POST" <?php if($payzen['payzen_return_mode'] == 'POST') echo('selected="selected"'); ?>>POST</option>
				</select>
				<small style="font-style: italic; font-size: smaller; padding-left: 15px;"><?php _e('How the client will transmit the payment result', 'payzen'); ?></small><br />
				
				<?php _e('Check URL', 'payzen') ?><br />
				<?php _e('Important, copy in your bank backoffice the following URL', 'payzen') ?>: <b><?php echo(add_query_arg('eshopaction', 'payzenipn', get_permalink($eshopoptions['cart_success'])));?></b>
			</fieldset>
		</fieldset>
<?php
}
	
function eshop_payzen_set_default_values($eshopoptions) {
	if(empty($eshopoptions) || !key_exists('payzen', $eshopoptions)) { // if module not installed yet
		// activate PayZen payment method
		$eshopoptions['method'][] = 'payzen';
			
		// set default values
		$eshopoptions['payzen'] = array(
				'payzen_debug' => 'True',
				'payzen_site_id' => '12345678',
				'payzen_ctx_mode' => 'TEST',
				'payzen_key_test' => '1111111111111111',
				'payzen_key_prod' => '2222222222222222',
				'payzen_platform_url' => 'https://secure.payzen.eu/vads-payment/',

				'payzen_language' => 'fr',
				'payzen_available_languages' => '',
				'payzen_shop_name' => '',
				'payzen_shop_url' => '',
				'payzen_capture_delay' => '',
				'payzen_validation_mode' => '',
				'payzen_payment_cards' => '',
				'payzen_min_amount_3ds' => '',
					
				'payzen_amount_min' => '',
				'payzen_amount_max' => '',
					
				'payzen_redirect_enabled' => 'False',
				'payzen_redirect_success_timeout' => 5,
				'payzen_redirect_success_message' => 'Votre paiement a bien été pris en compte, vous allez être redirigé dans quelques instants.',
				'payzen_redirect_error_timeout' => 5,
				'payzen_redirect_error_message' => 'Une erreur est survenue, vous allez être redirigé dans quelques instants.',
				'payzen_return_mode' => 'GET'
		);
	}

	return $eshopoptions;
}

function eshop_payzen_save($eshopoptions, $posted) {
	global $wpdb;
	
	$admin_vars = array('payzen_debug', 'payzen_site_id', 'payzen_ctx_mode', 'payzen_key_test', 'payzen_key_prod', 
			'payzen_platform_url', 'payzen_language', 'payzen_available_languages', 'payzen_shop_name', 
			'payzen_shop_url', 'payzen_capture_delay', 'payzen_validation_mode', 'payzen_payment_cards', 
			'payzen_min_amount_3ds', 'payzen_amount_min', 'payzen_amount_max', 'payzen_redirect_enabled', 
			'payzen_redirect_success_timeout', 'payzen_redirect_success_message', 'payzen_redirect_error_timeout', 
			'payzen_redirect_error_message', 'payzen_return_mode');
	
	// prepare posted variables for saving
	foreach ($admin_vars as $var) {
		$eshopoptions['payzen'][$var] = $wpdb->escape($posted['eshop_' . $var]);
	}
	
	return $eshopoptions;
}
add_filter('eshop_setting_merchant_save', 'eshop_payzen_save', 10, 2);

// redirect to the platform function
function eshop_payzen_redirect($espost) {
	// this function is intentionally let empty
}

// adding the image for this gateway, for use on the front end of the site
function eshop_payzen_img($array) {
	$array['path'] = ESHOP_PAYZEN_PLUGIN_PATH . 'images/payzen.png';
	$array['url'] = ESHOP_PAYZEN_PLUGIN_URL . 'images/payzen.png';

	return $array;
}
add_filter('eshop_merchant_img_payzen', 'eshop_payzen_img');

// adding necessary link for the redirect to payment gateway
function eshop_payzen_inc_path($path, $paymentmethod) {
	global $blog_id, $eshopoptions;
	
	// duty free amount
	$amount = number_format($_SESSION['final_price' . $blog_id], 2, '.', '');
	if(($eshopoptions['payzen']['payzen_amount_min'] != '' && $amount < $eshopoptions['payzen']['payzen_amount_min'])
			|| ($eshopoptions['payzen']['payzen_amount_max'] != '' && $amount > $eshopoptions['payzen']['payzen_amount_max'])) {
		
		foreach($eshopoptions['method'] as $index => $method) {
			if($method == 'payzen') {
				unset($eshopoptions['method'][$index]);
				break;
			}
		}
	}
	
	if($paymentmethod == 'payzen') {
		return ESHOP_PAYZEN_PLUGIN_PATH . 'includes/payzen.php';
	}

	return $path;
}
add_filter('eshop_mg_inc_path', 'eshop_payzen_inc_path', 10, 2);

// adding the necessary link to the index file for this gateway
function eshop_payzen_inc_idx_path($path, $paymentmethod) {
	if($paymentmethod == 'payzen') {
		return ESHOP_PAYZEN_PLUGIN_PATH . 'includes/index.php';
	}
	
	return $path;
}
add_filter('eshop_mg_inc_idx_path', 'eshop_payzen_inc_idx_path', 10, 2);

// adding the necessary link for the instant payment notification of the gateway
function eshop_payzen_inc_ipn_path($eshopaction) {
	if($eshopaction == 'payzenipn') {
		include_once ESHOP_PAYZEN_PLUGIN_PATH . 'includes/payzen.php';
	}
}
add_action('eshop_include_mg_ipn', 'eshop_payzen_inc_ipn_path');

// message on return to store
function eshop_payzen_return($echo, $eshopaction, $postit) {
	global $wpdb, $eshopoptions;

	if($eshopaction == 'payzenipn' && key_exists('status', $_GET)) {
		if(is_array($postit)) {
			if(key_exists('pass_prod_msg', $postit) && $postit['pass_prod_msg']) {
				$msg = __('<u>GOING INTO PRODUCTION</u><br />You want to know how to put your shop into production mode, please go to this URL : ', 'payzen');
				$msg .= '<a href="https://secure.payzen.eu/html/faq/prod" target="_blank">https://secure.payzen.eu/html/faq/prod</a>';
				
				$echo .= '<div style="padding: 7px; border: 2px solid #8FAE1B;">' . $msg . '</div><br />';
			}
			
			if(key_exists('url_check_warn', $postit) && $postit['url_check_warn']) {
				$serverUrlWarn = sprintf(__('The automatic notification (peer to peer connection between the payment platform and your shopping cart solution) hasn\'t worked. Have you correctly set up the server URL in your %s backoffice ?', 'payzen'), 'PayZen');
				$serverUrlWarn .= '<br />';
				$serverUrlWarn .= __('For understanding the problem, please read the documentation of the module : <br />&nbsp;&nbsp;&nbsp;- Chapter &laquo;To read carefully before going further&raquo;<br />&nbsp;&nbsp;&nbsp;- Chapter &laquo;Server URL settings&raquo;', 'payzen');
				
				$echo .= '<div style="padding: 7px; border: 2px solid #B81C23;">' . $serverUrlWarn . '</div><br />';
			}
		}
		
		if($_GET['status'] == md5('success')) {
			$echo .= '<h3 class="success">' . __('The payment was successful', 'payzen') . ' !</h3>';
			$echo .= '<p>'.__('Your order payment has been succesfully received.', 'payzen') . '<br />';
			$echo .= sprintf(__('Your transaction ID is %s.', 'payzen'), $postit['trans_id']) . '</p>';
			
			// downloads
			$ordersTbl = $wpdb->prefix . 'eshop_orders';
			$downloadsTbl = $wpdb->prefix . 'eshop_download_orders';
				
			$status = $wpdb->get_var("SELECT status FROM $ordersTbl WHERE checkid = '" . $postit['check_id'] . "' AND downloads = 'yes' LIMIT 1");
			if($status == 'Sent' || $status == 'Completed') {
				$download = $wpdb->get_row("SELECT email, code FROM $downloadsTbl WHERE checkid = '" . $postit['check_id'] . "' AND downloads > 0 LIMIT 1");
			
				if($download->email != '' && $download->code != '') {
					//display form only if there are downloads!
					$echo .= '<form method="post" class="dform" action="' . get_permalink($eshopoptions['show_downloads']) . '">
								<p class="submit">
									<input name="email" type="hidden" value="' . $download->email . '" />
									<input name="code" type="hidden" value="' . $download->code . '" />
									<span class="buttonwrap">
										<input type="submit" id="submit" class="button" name="Submit" value="' . __('View your downloads','eshop') . '" />
									</span>
								</p>
							</form>';
				}
			}
		} else {
			$echo .= '<h3 class="eshoperror error">' . __('The payment failed.', 'payzen') . '</h3>';
			if($_GET['status'] == md5('error')) {
				$echo .= '<p>' . __('Your order payment has not been accepted.', 'payzen') . '<br />';
				
			} else {
				$echo .= '<p>' . __('An error has occured during the payment process.', 'payzen') . '<br />';
			}
			$echo .= __('Please try to checkout your order again.', 'payzen') . '</p>';
			$echo .= '<p>' . __('We have not emptied your shopping cart in case you want to make changes.', 'payzen') . '</p>';
		}
	}
	
	return $echo;
}
add_filter('eshop_show_success', 'eshop_payzen_return', 10, 3);

?>