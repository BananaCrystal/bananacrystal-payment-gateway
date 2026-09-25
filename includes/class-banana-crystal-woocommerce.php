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
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		} else {
		    add_action( 'woocommerce_api_'.$this->id, array( $this, 'process_ipn_response' ) );
			add_filter( 'woocommerce_gateway_title', array( $this,'change_payment_gateway_title'), 25, 2);
			add_action( 'wp_loaded', array( $this, 'process_subscription' ) );
			add_action( 'before_woocommerce_pay', array( $this, 'order_pay_subtitle_oval') );
		}
		// AI Agent mode return handler (distinct wc-api endpoint from the legacy IPN).
		add_action( 'woocommerce_api_' . BC_PAY_GATEWAY_ID . '_return', array( $this, 'handle_agent_return' ) );
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

		// Instructions live in the field descriptions (not "title" fields), because
		// a WooCommerce "title" field breaks the settings table and can't be shown
		// or hidden per mode. The admin script toggles the field rows by mode.
		$agent_help = '<strong>Set up &mdash; AI Agent site</strong><br>'
			. '1. In BananaCrystal, go to Stores &rarr; your store &rarr; Integrations, and add a WooCommerce integration.<br>'
			. '2. Copy the publishable key (starts with <code>pk_live_</code>) and paste it in Publishable key above.<br>'
			. '3. If a secret key (<code>sk_live_</code>) is shown, paste it in Secret key below. Leave it blank if there is not one yet.<br>'
			. '4. Save. Shoppers pay on a BananaCrystal page and return here, and the order is marked paid automatically.';

		$legacy_help = '<strong>Set up &mdash; Legacy site</strong><br>'
			. '1. Enter your BananaCrystal Store Username above.<br>'
			. '2. In BananaCrystal, paste these into your store settings:<br>'
			. '&nbsp;&nbsp;&bull; Order Completion / Thank You URL: <code>' . esc_html( $thankyou_page_url ) . '</code><br>'
			. '&nbsp;&nbsp;&bull; Order Pay URL: <code>' . esc_html( $pay_page_url ) . '</code><br>'
			. '&nbsp;&nbsp;&bull; Payment Notification (IPN) URL: <code>' . esc_html( $ipn_notification_url ) . '</code><br>'
			. '3. Save.';

		$this->form_fields = array(
			'help_text_signup' => array(
				'title' => __( '<a href="' . $sign_up_url . '" target="_blank">Sign up</a> to start accepting payments with BananaCrystal', 'wo-banana-crystal' ),
				'type'  => 'title',
			),
			'enabled' => array(
				'title'   => __( 'Enable / Disable', 'wo-banana-crystal' ),
				'label'   => __( 'Enable this payment gateway', 'wo-banana-crystal' ),
				'type'    => 'checkbox',
				'default' => 'no',
			),
			'mode' => array(
				'title'       => __( 'Mode', 'wo-banana-crystal' ),
				'type'        => 'select',
				'options'     => array(
					'agent'  => __( 'AI Agent site', 'wo-banana-crystal' ),
					'legacy' => __( 'Legacy site', 'wo-banana-crystal' ),
				),
				'default'     => 'agent',
				'description' => __( 'Choose AI Agent if you use the new BananaCrystal at agents.bananacrystal.com. Choose Legacy if you use the older app.bananacrystal.com.', 'wo-banana-crystal' ),
			),
			'title' => array(
				'title'    => __( 'Title', 'wo-banana-crystal' ),
				'type'     => 'text',
				'desc_tip' => __( 'The title shoppers see at checkout.', 'wo-banana-crystal' ),
				'default'  => __( 'Pay with BananaCrystal', 'wo-banana-crystal' ),
			),
			'description' => array(
				'title'    => __( 'Description', 'wo-banana-crystal' ),
				'type'     => 'textarea',
				'desc_tip' => __( 'The description shoppers see at checkout.', 'wo-banana-crystal' ),
				'default'  => __( 'Pay securely with BananaCrystal. You will be redirected to complete your payment.', 'wo-banana-crystal' ),
				'css'      => 'max-width:450px;',
			),

			// --- AI Agent site fields (toggled by the mode dropdown) ---
			'publishable_key' => array(
				'title'       => __( 'Publishable key', 'wo-banana-crystal' ),
				'type'        => 'text',
				'placeholder' => 'pk_live_…',
				'description' => $agent_help,
			),
			'secret_key' => array(
				'title'       => __( 'Secret key', 'wo-banana-crystal' ),
				'type'        => 'password',
				'placeholder' => 'sk_live_…',
				'description' => __( 'Used to verify payment notifications. Leave blank if BananaCrystal has not shown one.', 'wo-banana-crystal' ),
			),

			// --- Legacy site fields (toggled by the mode dropdown) ---
			'store_username' => array(
				'title'       => __( 'BananaCrystal Store Username', 'wo-banana-crystal' ),
				'type'        => 'text',
				'description' => $legacy_help,
			),
			'subscriptions_enabled' => array(
				'title'   => __( 'Enable / Disable Subscriptions', 'wo-banana-crystal' ),
				'label'   => __( 'Enable subscriptions for this payment gateway', 'wo-banana-crystal' ),
				'type'    => 'checkbox',
				'default' => 'no',
			),
			'subscription_key' => array(
				'title'    => __( 'Subscription Key', 'wo-banana-crystal' ),
				'type'     => 'password',
				'desc_tip' => __( 'Your BananaCrystal subscription key.', 'wo-banana-crystal' ),
			),
		);
	}

	/** Which flow this store uses: 'agent' (Stores) or 'legacy' (old app). */
	public function get_mode() {
		$mode = $this->get_option( 'mode', 'agent' );
		return in_array( $mode, array( 'agent', 'legacy' ), true ) ? $mode : 'agent';
	}

	public function get_publishable_key() {
		return trim( (string) $this->get_option( 'publishable_key' ) );
	}

	public function get_secret_key() {
		return trim( (string) $this->get_option( 'secret_key' ) );
	}

	// Response handled for payment gateway
	public function process_payment( $order_id ) {
        // AI Agent mode uses the hosted Stores checkout; legacy keeps the flow below.
        if ( 'agent' === $this->get_mode() ) {
            return $this->process_payment_agent( $order_id );
        }
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
	 * AI Agent flow: create a hosted Stores checkout session and redirect the
	 * buyer to it. The order stays pending until confirmed on return (or, later,
	 * by the settlement webhook / the poller).
	 */
	private function process_payment_agent( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return array( 'result' => 'failure' );
		}
		$pk = $this->get_publishable_key();
		if ( ! $pk ) {
			wc_add_notice( __( 'BananaCrystal is not configured. Please contact the store.', 'wo-banana-crystal' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$client  = new BC_API_Client( $pk );
		$session = $client->create_session(
			array(
				'amount'      => (string) $order->get_total(),
				'currency'    => $order->get_currency(),
				'order_id'    => (string) $order->get_id(),
				'description' => sprintf(
					/* translators: 1: order number, 2: store name */
					__( 'Order #%1$s — %2$s', 'wo-banana-crystal' ),
					$order->get_order_number(),
					get_bloginfo( 'name' )
				),
				'return_url'  => $this->agent_return_url( $order ),
				'reference'   => 'wc_' . $order->get_id(),
				'origin'      => home_url( '/' ),
			)
		);

		if ( empty( $session['ok'] ) ) {
			$order->add_order_note( 'BananaCrystal session failed: ' . ( $session['error'] ?? 'unknown' ) );
			wc_add_notice( __( 'Could not start the BananaCrystal payment. Please try again.', 'wo-banana-crystal' ), 'error' );
			return array( 'result' => 'failure' );
		}

		if ( ! empty( $session['session_id'] ) ) {
			$order->update_meta_data( '_bc_session_id', $session['session_id'] );
		}
		$order->update_status( 'pending', __( 'Awaiting BananaCrystal payment.', 'wo-banana-crystal' ) );
		$order->save();

		return array( 'result' => 'success', 'redirect' => $session['checkout_url'] );
	}

	/** Where the buyer is sent back to after the hosted checkout (agent mode). */
	private function agent_return_url( $order ) {
		return add_query_arg(
			array(
				'wc-api'   => BC_PAY_GATEWAY_ID . '_return',
				'order_id' => $order->get_id(),
				'key'      => $order->get_order_key(),
			),
			home_url( '/' )
		);
	}

	/**
	 * Buyer is back from the hosted checkout. Confirm once by polling status and
	 * complete the order if it settled; otherwise leave it pending (the poller
	 * finishes it) and send them to the order-received page either way.
	 */
	public function handle_agent_return() {
		$order_id = isset( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0;
		$key      = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! $order || ! hash_equals( $order->get_order_key(), $key ) ) {
			wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
			exit;
		}

		if ( ! $order->is_paid() ) {
			$client     = new BC_API_Client( $this->get_publishable_key() );
			$session_id = (string) $order->get_meta( '_bc_session_id' );
			$status     = $session_id
				? $client->get_session_status( $session_id )
				: $client->get_order_status( (string) $order->get_id() );
			$s = (string) ( $status['status'] ?? '' );
			if ( ! empty( $status['ok'] ) && BC_API_Client::is_paid( $s ) ) {
				BC_Order::mark_paid( $order, (string) ( $status['transaction_id'] ?? '' ), 'return' );
			} elseif ( ! empty( $status['ok'] ) && BC_API_Client::is_failed( $s ) ) {
				$order->update_status( 'failed', __( 'BananaCrystal payment failed.', 'wo-banana-crystal' ) );
			}
		}

		wp_safe_redirect( $this->get_return_url( $order ) );
		exit;
	}

	/** Enqueue the admin script that shows the fields for the selected mode. */
	public function enqueue_admin_assets( $hook ) {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}
		wp_enqueue_script(
			'bc-admin-mode',
			BC_PAY_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BC_PAY_VERSION,
			true
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
