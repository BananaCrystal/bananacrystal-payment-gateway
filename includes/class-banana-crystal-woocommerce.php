<?php

class Woocommerce_Banana_Crystal extends WC_Payment_Gateway {

	function __construct() {

		// global ID
		$this->id = "wo_banana_crystal";

		// Show Title
		$this->method_title = __( "Banana Crystal", 'wo-banana-crystal' );

		// Show Description
		$this->method_description = __( "Send and receive secure peer to peer payments to anyone instantly at no cost to you.", 'wo-banana-crystal' );

		// vertical tab title
		$this->title = __( "Banana Crystal", 'wo-banana-crystal' );


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
		}		
	} // Here is the  End __construct()

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
				'default'	=> __( 'Banana Crystal', 'wo-banana-crystal' ),
			),
			'description' => array(
				'title'		=> __( 'Description', 'wo-banana-crystal' ),
				'type'		=> 'textarea',
				'desc_tip'	=> __( 'Payment title of checkout process.', 'wo-banana-crystal' ),
				'default'	=> __( 'Pay with Banana Crystal gateway.', 'wo-banana-crystal' ),
				'css'		=> 'max-width:450px;'
			),
			'store_username' => array(
				'title'		=> __( 'Banana Crystal Store Username', 'wo-banana-crystal' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This is the store username provided by Banana Crystal when you signed up for an account.', 'wo-banana-crystal' ),
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
        $params = '?amount='.$order->order_total.'&note=Payment through woocommerce store&ref='.$order_id.'&redirect_url=';
		// urlencode($this->get_return_url( $order ));
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

}