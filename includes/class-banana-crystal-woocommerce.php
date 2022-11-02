<?php

class Woocommerce_Banana_Crystal extends WC_Payment_Gateway {

	function __construct() {

		// global ID
		$this->id = "wo_banana_crystal";

		// Show Title
		$this->method_title = __( "BananaCrystal", 'wo-banana-crystal' );

		// Show Description
		$this->method_description = __( "Send and receive secure peer to peer payments to anyone instantly at no cost to you.", 'wo-banana-crystal' );

		// vertical tab title
		$this->title = __( "BananaCrystal", 'wo-banana-crystal' );


		$this->icon = null;

		$this->has_fields = true;

		// setting defines
		$this->init_form_fields();

		// load time variable setting
		$this->init_settings();
		
		// Turn these settings into variables we can use
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}
		
		// further check of SSL if you want
		add_action( 'admin_notices', array( $this,	'do_ssl_check' ) );
		
		// Save settings
		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		} else {
		    add_action( 'woocommerce_api_'.$this->id, array( $this, 'process_ipn_response' ) );
			add_filter( 'woocommerce_gateway_title', array( $this,'change_payment_gateway_title'), 25, 2);

		}		
	} // Here is the  End __construct()


	/**
	 * Process IPN notification when banana crystal calls
	 * 
	 * @return (object)json
	 * */
	public function process_ipn_response() {
		global $woocommerce;
		$data = file_get_contents("php://input", false, stream_context_get_default(), 0, $_SERVER["CONTENT_LENGTH"]);
		// LOAD THE WC LOGGER
		$logger = wc_get_logger();
    
		// LOG THE IPN ORDER TO CUSTOM "banana-crystal" LOG
		$logger->info( wc_print_r( $data, true ), array( 'source' => 'banana-crystal' ) );
		
		$response = ['success' => false];
		if ($data->payment_status == 'completed') {
		    //check if request is from Banana Crystal
		   $verifyResponse = $this->verifyPayload($data);
		    
		  $order = new WC_Order( $data->order_id );
		  $order->payment_complete();
		  $response['success'] = true;
		} else if ($data->payment_status == 'failed') {
		  $order = new WC_Order( $data->order_id );
		  $order->update_status('failed', __( 'Payment status failed', 'wo-banana-crystal' ));
		}

		echo json_encode($response);
	}


	//Modify page gateway title on checkout
	public function change_payment_gateway_title( $title, $gateway_id ){
    
		if( 'wo_banana_crystal' === $gateway_id ) {
			$title = '<img title="BananaCrystal Payment Gateway" src="'.plugin_dir_url(__DIR__ ).'public/img/bananacrystal-logo.png"  class="banana-crystal-logo"/>';
		}
	
		return $title;
	}

	// administration fields for specific Gateway
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'		=> __( 'Enable / Disable', 'wo-banana-crystal' ),
				'label'		=> __( 'Enable this payment gateway', 'wo-banana-crystal' ),
				'type'		=> 'checkbox',
				'default'	=> 'no',
			),
			'title' => array(
				'title'		=> __( 'Title', 'wo-banana-crystal' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Payment title of checkout process.', 'wo-banana-crystal' ),
				'default'	=> __( 'BananaCrystal', 'wo-banana-crystal' ),
			),
			'description' => array(
				'title'		=> __( 'Description', 'wo-banana-crystal' ),
				'type'		=> 'textarea',
				'desc_tip'	=> __( 'Payment title of checkout process.', 'wo-banana-crystal' ),
				'default'	=> __( 'Peer-To-Peer Payments.', 'wo-banana-crystal' ),
				'css'		=> 'max-width:450px;'
			),
			'store_username' => array(
				'title'		=> __( 'BananaCrystal Store Username', 'wo-banana-crystal' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This is the store username provided by BananaCrystal when you signed up for an account.', 'wo-banana-crystal' ),
			),
			'help_text' => array(
                'title' => __( 'Please use this link to update in your banana crystal thank you link: <a href="'.site_url().'/?page_id=11">'.site_url().'/?page_id=11</a>', 'wo-banana-crystal' ),
                'type' => 'title',
                'desc' => __( 'Please use this link to update in your banana crystal thank you link: <a href="'.site_url().'/?page_id=11">'.site_url().'/?page_id=11</a>', 'wo-banana-crystal' ),
                'id'   => 'wo-banana-crystal_help_text',
                'css'       => 'min-width:300px;'
            )
		);		
	}
	
	// Response handled for payment gateway
	public function process_payment( $order_id ) {
        global $woocommerce;
        $order = new WC_Order( $order_id );
    
        // Mark as on-hold (we're awaiting the cheque)
        $order->update_status('on-hold', __( 'Awaiting payment confirmation', 'wo-banana-crystal' ));
    
        // Remove cart
        $woocommerce->cart->empty_cart();

        //redirect urser to store banana crystal payment page
        $params = '?amount='.$order->order_total.'&note=Payment through woocommerce store&ref='.$order_id;
        $store_user_name = $this->get_option( 'store_username' );
        $redirect_url = 'https://app.bananacrystal.com/payme/'.$store_user_name.$params;
    
        // Return thankyou redirect
        return array(
            'result' => 'success',
            'redirect' => $redirect_url
        );
	}
	
	// Validate fields
	public function validate_fields() {
		return true;
	}

	public function do_ssl_check() {
		if( $this->enabled == "yes" ) {
			if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
				echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";	
			}
		}		
	}

	private function verifyPayload($data) {
        $endpoint = 'https://app.bananacrystal.com/store_integrations/wordpress/notifications/verify';
        $postdata = json_encode($data);
    
        $ch = curl_init($endpoint); 
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
	}

}