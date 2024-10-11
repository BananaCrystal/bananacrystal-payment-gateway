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
			add_action( 'wp_loaded', array( $this, 'process_subscription' ) );
			add_action( 'before_woocommerce_pay', array( $this, 'order_pay_subtitle_oval') );
		}			
	} // Here is the  End __construct()


	/**
	 * Process IPN notification when banana crystal calls
	 * 
	 * @return (object)json
	 * */
	public function process_ipn_response() {
		global $woocommerce;
		global $wpdb;
		$data = file_get_contents("php://input", false, stream_context_get_default(), 0, $_SERVER["CONTENT_LENGTH"]);
	  $data = json_decode( $data);

		// VERIFY DATA
		//check if request is from Banana Crystal
		$data->cmd = "notify-validate";
		$verifyResponse = $this->verifyPayload($data);

		// LOAD THE WC LOGGER
		$logger = wc_get_logger();

		// LOG THE IPN ORDER TO CUSTOM "banana-crystal" LOG
		$logger->info( wc_print_r( $verifyResponse , true ), array( 'source' => 'banana-crystal' ) );

		$response = ['success' => false];
		
		if ($data->payment_status == 'completed') {
			//execute subscription flow
			if (isset($data->subscription_plan_id)) {
				$plan = get_banana_crystal_subscription_plan($data->subscription_plan_id);
			    //create subscription
			    $subscription_data = [
			        'subscription_plan_id' => $plan->subscription_plan_id,
		    	    'user_id' => $data->subscriber_user_id,
		        	'subscription_title' => $plan->subscription_plan_title,
		        	'subscription_occurrence' => $plan->subscription_plan_occurrence,
		        	'subscription_amount' => $plan->subscription_plan_amount,
		        	'buyer_user_name' => $data->payer_username,
		        	'payload' => json_encode($data),
		        	'subscription_status' => 'ACTIVE',
		        	'created_at' => date('Y-m-d H:i:s'),
		        	'expired_at' => get_banana_crystal_expiry_date_by_occurence($plan->subscription_plan_occurrence)
		    	];
		    	$wpdb->insert($wpdb->prefix.'banana_crystal_subscriptions', $subscription_data);
			} else { //execute one time payment flow
				$order = new WC_Order( $data->order_id );
				$order->payment_complete();
				//ADD FILTER FOR SUCCESS TRANSACTION
				apply_filters( 'wo_banana_crystal_payment_success', ['order' => $order]);
			}
		  $response['success'] = true;

		} else if ($data->payment_status == 'failed') {
		  $order = new WC_Order( $data->order_id );
		  $order->update_status('failed', __( 'Payment status failed', 'wo-banana-crystal' ));
		}

		echo json_encode($response);
	}


	//Modify page gateway title on checkout
	public function change_payment_gateway_title( $title, $gateway_id ){
		if( 'wo_banana_crystal' === $gateway_id && isset($_GET['wc-ajax'])) {
			$title = $_GET['wc-ajax'] == 'update_order_review' ? $title.' <img title="BananaCrystal Payment Gateway" src="'.plugin_dir_url(__DIR__ ).'public/img/bananacrystal-logo.png"  class="banana-crystal-logo"/>' : $title;
		}
	
		return $title;
	}


	// administration fields for specific Gateway
	public function init_form_fields() {
	    
	    $order_param = WC_Admin_Settings::get_option('woocommerce_checkout_order_received_endpoint', 'order-received' );
	    $thankyou_page_url = wc_get_checkout_url() . $order_param . '/order_id';
	    $setting_page_url = 'https://app.bananacrystal.com/stores/';
		$sign_up_url = 'https://www.bananacrystal.com/business/';
	    $ipn_notification_url = site_url().'/?wc-api=wo_banana_crystal';
		$pay_param = WC_Admin_Settings::get_option('woocommerce_checkout_pay_endpoint', 'order-pay' );
	    $pay_page_url = wc_get_checkout_url() . $pay_param . '/order_id';

		$this->form_fields = array(
			'help_text_signup' => array(
				'title' => __('<a href="'.$sign_up_url.'" target="_blank">Sign up</a> to start accepting payments with BananaCrystal', 'wo-banana-crystal' ),
				'type' => 'title',
				'id'   => 'wo-banana-crystal_help_text_signup'
			),
			'help_text_heading1' => array(
				'title' => __('<u>Woocommerce Settings</u>', 'wo-banana-crystal' ),
				'type' => 'title',
				'id'   => 'wo-banana-crystal_help_text'
			),
			'enabled' => array(
				'title'		=> __( 'Enable / Disable', 'wo-banana-crystal' ),
				'label'		=> __( 'Enable this payment gateway', 'wo-banana-crystal' ),
				'type'		=> 'checkbox',
				'default'	=> 'no',
			),
			'title' => array(
				'title'		=> __( 'Title', 'wo-banana-crystal' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This is the title that the user sees during the checkout process.', 'wo-banana-crystal' ),
				'default'	=> __( 'Pay with ', 'wo-banana-crystal' ),
			),
			'description' => array(
				'title'		=> __( 'Description', 'wo-banana-crystal' ),
				'type'		=> 'textarea',
				'desc_tip'	=> __( 'This is the description that the user sees during the checkout process.', 'wo-banana-crystal' ),
				'default'	=> __( 'Secure, Instant, Peer-To-Peer Payments', 'wo-banana-crystal' ),
				'css'		=> 'max-width:450px;'
			),
			'store_username' => array(
				'title'		=> __( 'BananaCrystal Store Username', 'wo-banana-crystal' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This is your BananaCrystal store username.', 'wo-banana-crystal' ),

			),
			'subscriptions_enabled' => array(
				'title'		=> __( 'Enable / Disable Subscriptions', 'wo-banana-crystal' ),
				'label'		=> __( 'Enable subscriptions for this payment gateway', 'wo-banana-crystal' ),
				'type'		=> 'checkbox',
				'default'	=> 'no',
			),
			'subscription_key' => array(
				'title'		=> __( 'Subscription Key', 'wo-banana-crystal' ),
				'type'		=> 'password',
				'desc_tip'	=> __( 'This is your BananaCrystal subscription key.', 'wo-banana-crystal' ),

			),
			'help_text_heading_bc' => array(
				'title' => __('<u>BananaCrystal Settings</u>', 'wo-banana-crystal' ),
				'type' => 'title',
				'id'   => 'wo-banana-crystal_help_text'
			),

			'help_text_title' => array(
				'title' => __('1. Go to your Store > <a href="'.$setting_page_url.'" target="_blank">Integrations</a> on BananaCrystal', 'wo-banana-crystal' ),
				'type' => 'title',
				'id'   => 'wo-banana-crystal_help_text'
			),
			'help_text_title_add_integration' => array(
				'title' => __('2. Add a Woocommerce Integration', 'wo-banana-crystal' ),
				'type' => 'title',
				'id'   => 'wo-banana-crystal_help_text'
			),
			'help_text' => array(
				'title' => __('3. Ensure that the BananaCrystal Store Username entered is the same as the one used when you created the Store.<br><br>You can update the store username from you Store > Settings', 'wo-banana-crystal' ),
				'type' => 'title',
				'id'   => 'wo-banana-crystal_help_text'
			),
			'help_text' => array(
				'title' => __('4. Copy and paste the url below to the Order Completion or Thank You Page URL setting<br><br><code>'.$thankyou_page_url.'</code>', 'wo-banana-crystal' ),
				'type' => 'title',
				'id'   => 'wo-banana-crystal_help_text'
			),
			'help_text_order_pay' => array(
				'title' => __('5. Copy and paste the url below to Order Pay URL <br><br><code>'.$pay_page_url.'</code>', 'wo-banana-crystal' ),
				'type' => 'title',
				'id'   => 'wo-banana-crystal_help_order_pay'
			),
			'help_text_ipn' => array(
					'title' => __('6. Copy and paste the url below to Payment Notifications URL <br><br><code>'.$ipn_notification_url.'</code>', 'wo-banana-crystal' ),
					'type' => 'title',
					'id'   => 'wo-banana-crystal_help_ipn'
			),
			'help_text_subscription' => array(
				'title' => __('7. Enable your subscription by clicking enable subscription checkbox', 'wo-banana-crystal' ),
				'type' => 'title',
				'id'   => 'wo-banana-crystal_help_subscription'
			),
			'help_text_subscription_key' => array(
				'title' => __('8. View your integration and copy the API key for the subscription key', 'wo-banana-crystal' ),
				'type' => 'title',
				'id'   => 'wo-banana-crystal_help_subscription_key'
			)
		);


	}

	// Response handled for payment gateway
	public function process_payment( $order_id ) {
        global $woocommerce;
        $order = new WC_Order( $order_id );
    
    	// Mark as pending payment (we're awaiting the confirmation)
		$order->update_status('pending', __( 'Awaiting payment confirmation', 'wo-banana-crystal' ));
    
        // Remove cart
        $woocommerce->cart->empty_cart();

        //get only key from prefix
        $order_key = str_replace('wc_order_', '', $order->order_key);
        
        //append items in notes
        $notes = '';
        // Get and Loop Over Order Items
        foreach ( $order->get_items() as $item_id => $item ) {
           $product_name = $item->get_name();
           $quantity = $item->get_quantity();
           $notes .= $product_name.' x '.$quantity.'\n';
        }
                
        // Get store's currency
    	$currency = get_woocommerce_currency();

        //redirect user to store banana crystal payment page
        $params = '?amount='.$order->order_total.'&note='.$notes.'&order_id='.$order_id.'&currency='.$currency.'&sd='. base64_encode($order_key);
        $store_user_name = $this->get_option( 'store_username' );
        $redirect_url = 'https://app.bananacrystal.com/payme/'.$store_user_name.$params;
    
        // Return thankyou redirect
        return array(
            'result' => 'success',
            'redirect' => $redirect_url
        );
	}
	
	/**
	 * Valudate fields
	 * 
	 * @return (bool)
	 **/
	public function validate_fields() {
		return true;
	}

    /**
     * Show ssl warning if not enabled
     * 
     * @return (void)
     **/
	public function do_ssl_check() {
		if( $this->enabled == "yes" ) {
			if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
				echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";	
			}
		}		
	}
    
    /**
     * Check IPN payload is coming from the banana crystal
     * 
     * @params (array)$data
     * @return (mixed)
     **/
	private function verifyPayload($data) {
        $endpoint = 'https://app.bananacrystal.com/store_integrations/wordpress/notifications/verify';
        $postdata = json_encode($data);

		$request = array(
			'method'      => 'POST',
			'timeout'     => 45,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking'    => true,
			'headers'     => array(
				'Content-Type' => 'application/json; charset=utf-8'
			),
			'data_format' => 'body',
			'body'        => $postdata,
			'cookies'     => array(),
			'sslverify'   => true
		);
		$response = wp_remote_post( $endpoint, $request);

        return $response;
	}


	public function process_subscription() {
		if (isset($_POST['bc_subscription_buy_now'])) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'banana_crystal_subscriptions WHERE deleted_at IS NULL AND subscription_plan_id='.((int)sanitize_text_field($_POST['bc_subscription_id']));
			$result = $wpdb->get_row("SELECT * FROM $table_name");
			if ($result) {
				$user = wp_get_current_user();

				// get store currency
				$currency = get_woocommerce_currency();

				//redirect user to store banana crystal payment page
				$params = '?amount='.$result->subscription_plan_amount.'&note='.$result->subscription_plan_title.'&currency='.$currency.'&subscriber_user_id='.base64_encode($user->ID).'&sd=&subscription_id='.$result->subscription_plan_id.'&subscriber_username='.base64_encode($user->user_login);
				$store_user_name = $this->get_option( 'store_username' );
				$redirect_url = 'https://app.bananacrystal.com/pay_subscriptions/'.$store_user_name.$params;
				wp_redirect( $redirect_url );
				exit;
			}
		}
	}

    public function order_pay_subtitle_oval(){
		$order_id = wc_get_order_id_by_order_key($_GET['key']);
		$order    = wc_get_order( $order_id );
		$store_user_name = $this->get_option( 'store_username' );
		//get only key from prefix
			$order_key = str_replace('wc_order_', '', $order->order_key);
			$order_id = $order->get_id();
			//append items in notes
			$notes = '';
			// Get and Loop Over Order Items
			foreach ( $order->get_items() as $item_id => $item ) {
				$product_name = $item->get_name();
				$quantity = $item->get_quantity();
				$notes .= $product_name.' x '.$quantity.'\n';
			}
			
			//redirect user to store banana crystal payment page
			$params = '?amount='.$order->order_total.'&note='.$notes.'&order_id='.$order_id.'&sd='. base64_encode($order_key);
			echo '<p class="payment-pending-text">Thank you. Your order is pending payment. Please click below to pay for the order <br/> <a href="https://app.bananacrystal.com/payme/'.$store_user_name.$params.'" style="
			display: block;
			width: 230px;
		" class="button wp-element-button">Click here to Pay</a></p><br/><br/>';
	}
}
