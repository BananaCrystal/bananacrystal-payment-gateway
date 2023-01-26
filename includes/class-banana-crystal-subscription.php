<?php

class Banana_Crystal_Subscription {

	function __construct() {
		add_action( 'wp_loaded', array( $this, 'load_process' ) );
		add_action( 'init', array( $this, 'add_endpoint' ) );

		
		add_filter( 'query_vars', array( $this, 'bc_subscription_query_vars'), 0 );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'bc_subscription_link_my_account') );
		add_action( 'woocommerce_account_bc-subscription_endpoint', array( $this, 'bc_subscription_content') );
	} // Here is the  End __construct()

	/**
	 * Load process for cancel or new subscription on site
	 * 
	 * @return void
	 */
	public function load_process() {
		$this->process_subscription();
		$this->cancel_subscription();
	}
	
	/**
	 * Process and redirect user to BananaCrystal first time payment page
	 * 
	 * @return void
	 */
	public function process_subscription() {
		if (isset($_POST['bc_subscription_buy_now'])) {

			//redirect user to login if not already loggedin
		   if (!is_user_logged_in()) {
		       wp_redirect( site_url().'/my-account/' );
				exit;
		   }
			global $wpdb;
			$table_name = $wpdb->prefix . 'banana_crystal_subscription_plans';
			$result = $wpdb->get_row("SELECT * FROM $table_name  WHERE deleted_at IS NULL AND subscription_plan_id=".((int)sanitize_text_field($_POST['bc_subscription_id'])));
			if ($result) {
				$user = wp_get_current_user();
				$banana_crystal_settings = WC()->payment_gateways->payment_gateways()['wo_banana_crystal']->settings;

				//redirect urser to store banana crystal payment page
				$params = '?amount='.$result->subscription_plan_amount.'&note='.$result->subscription_plan_title.'&subscriber_user_id='. base64_encode($user->ID).'&sd=&subscription_id='.$result->subscription_plan_id.'&subscriber_username='. base64_encode($user->user_login);
				$store_user_name = $banana_crystal_settings['store_username'];
				$redirect_url = 'https://app.bananacrystal.com/pay_subscriptions/'.$store_user_name.$params;
				wp_redirect( $redirect_url );
				exit;
			}
		}
	}

	/**
	 * Cancel a subscription 
	 * 
	 * @return void
	 */
	public function cancel_subscription() {
		if (isset($_POST['bc_subscription_cancel_btn'])) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'banana_crystal_subscriptions';
			$subscriptionID = (int)sanitize_text_field($_POST['bc_subscription_id']);
			$wpdb->update($table_name, ['subscription_status' => 'CANCELLED'], ['subscription_id' => $subscriptionID]);
		}
	}

	/**
	 * Hook for adding my account subscription endpoint
	 * 
	 * @return void
	 */
	function add_endpoint() {
		add_rewrite_endpoint( 'bc-subscription', EP_ROOT | EP_PAGES );
	}

	/**
	 * Hook for adding query var for subscription endpoint in my account
	 * 
	 * @param (array) $vars
	 * @return void
	 */
	function bc_subscription_query_vars( $vars ) {
		$vars[] = 'bc-subscription';
		return $vars;
	}	

	/**
	 * Hook for adding menu in my account for subscription
	 * 
	 * @param (array) $items
	 * @return (array)
	 */
	function bc_subscription_link_my_account( $items ) {
		if ($this->is_subscription_enabled()) {
			$items['bc-subscription'] = 'Banana Crystal Subscription';
		}
		return $items;
	}

	/**
	 * Hook to show content of current subscription in my account
	 * 
	 * @return (mixed)
	 */
	public function bc_subscription_content() {
		echo '<h4 style="text-align: center;">Current Subscription Plan</h4>';
		echo do_shortcode( '[banana-crystal-current-subscription]' );
	}

	/**
	 * Check if subscription is enabled in payment gateway settings
	 * 
	 * @return boolean
	 */
	function is_subscription_enabled() {
		$banana_crystal_settings = WC()->payment_gateways->payment_gateways()['wo_banana_crystal']->settings;
		return ($banana_crystal_settings['subscriptions_enabled'] == 'yes' ? true : false); 
	}
}
