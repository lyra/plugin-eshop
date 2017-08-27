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

if ('payzen.class.php' == basename($_SERVER['SCRIPT_FILENAME'])) {
	die ('<h2>Direct File Access Prohibited</h2>');
}
    
require_once (ESHOP_PAYZEN_PLUGIN_PATH . 'includes/payzen_api.php');

class payzen_class {
	var $fields = array();
	
	var $api;
	var $ipn_data;
	var $debug;
	var $autoredirect;
	var $log_file;
	var $log_file_max_size;
   
  	/**
    * Initialization constructor. Called when class is created.
    */
   	function payzen_class() {
      	$this->api = null;
      	$this->ipn_data = null;
      	$this->debug = false;
      	$this->log_file = 'payzen.log';
      	$this->log_file_max_size = 2 * 1024 * 1024; // 1 GB
   	}
   
	function get_api() {
		if($this->api == null) {
			$this->api = new PayzenApi('UTF-8');
		}
		
		return $this->api;
   	}
   	
   	function get_ipn_data($payzenoptions) {
   		$request = stripslashes_deep($_REQUEST);
   		
   		$this->ipn_data = new PayzenResponse(
    			$request,
    			$payzenoptions['payzen_ctx_mode'],
    			$payzenoptions['payzen_key_test'],
    			$payzenoptions['payzen_key_prod']
    	);
   		
   		return $this->ipn_data;
   	}
   
    /**
   	 * Adds a key=>value pair to the fields array, which is what will be sent to PayZen as POST 
   	 * variables. If the value is already in the array, it will be overwritten.
   	 * 
   	 * @param string $key
   	 * @param string $value
   	 */
   	function add_field($key, $value) {
   		$this->fields[$key] = $value;
   	}

   	/**
     *  The user will briefly see a message on the screen that reads: "Please wait, your order is being processed ..." 
     *  and then immediately is redirected to PayZen.
     * 
     * @return string
     */
   	function submit_payzen_post() {
    	$echo = '<form method="POST" class="eshop eshop-confirm" action="' . $this->autoredirect . "\"><div>\n";

      	foreach ($this->fields as $key => $value) {
			$pos = strpos($key, 'amount');
			if ($pos === false) {
				$value=stripslashes($value);
				$echo.= "<input type=\"hidden\" name=\"$key\" value=\"$value\"/>\n";
			} else {
				$echo .= eshopTaxCartFields($key, $value);
      		}
      	}
      	$echo.='<label for="ppsubmit" class="finalize"><small>'.sprintf(__('<strong>Note:</strong> Submit to finalize order at %s.', 'payzen'), 'PayZen').'</small><br />
      		<input class="button submit2" type="submit" id="ppsubmit" name="ppsubmit" value="'.__('Proceed to Checkout &raquo;','payzen').'" /></label>';
	  	$echo.="</div></form>\n";
      
      	return $echo;
   	}
   
	/**
	 *  The user will briefly see a message on the screen that reads: "Please wait, your order is being processed..." 
	 *  and then immediately is redirected to PayZen.
	 *  
	 * @param array $post
	 * @return string
	 */
	function eshop_submit_payzen_post($post) {
		$this->get_api()->setFromArray(stripslashes_deep($post));
		
		$echo = '<div id="process">
			<p><strong>' . __('Please wait, your order is being processed&#8230;', 'payzen') . '</strong></p>
			<p>' . sprintf(__('If you are not automatically redirected to %s, please use the <em>Proceed to %s</em> button.', 'payzen'), ' PayZen',  'PayZen').'</p>
			
			<form method="POST" id="eshopgateway" class="eshop" action="' . $this->get_api()->platformUrl . '">
			<p>';
		
		$echo .= $this->get_api()->getRequestFieldsHtml();
		
		$echo .='<input class="button" type="submit" id="ppsubmit" value="'. sprintf(__('Proceed to %s', 'payzen'), ' PayZen').'" /></p>
			</form></div>';
		
		return $echo;
   	}
   	
   	function parse_info($data) {
		$vars = explode('&', $data);
		if(!$vars || !is_array($vars)) {
			return array();
		}
   		
		$result = array();
   		
   		foreach($vars as $var) {
   			$x = explode('=', $var);
   			$result[$x[0]] = $x[1];
   		}
   		
   		return $result;
   	}
   
	function log($message, $extra=array(), $level='INFO') {
		if (!$this->debug) {
			return;  // is logging turned off ?
		}
	   	
	   	// Timestamp
	   	$text = '[' . date('m-d-Y g:i:s') . '] - ';
	   	
	   	$text .= strtoupper($level) . ' - ';
	   	$text .= $message;
	   	
	   	if(!empty($extra)) {
	   		$text .= ", details: \n<pre>" . print_r($extra, true) . '</pre>';
	   	}
	   	
	   	$mode = 'a';
	   	if(file_exists($this->log_file) && filesize($this->log_file) > $this->log_file_max_size) {
	   		// if max_size reached, re-create file
	   		$mode = 'w';
	   	}
	   	// write to log
	   	$fp = fopen($this->log_file, $mode);
	   	
	   	fwrite($fp, $text . "\n\n");
	   	
	   	fclose($fp);  // close file
   }
}   