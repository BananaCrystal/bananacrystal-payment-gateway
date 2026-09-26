<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.bananacrystal.com/
 * @since             1.0.0
 * @package           Banana_Crystal_Payment_Gateway
 *
 * @wordpress-plugin
 * Plugin Name:       BananaCrystal Payment Gateway
 * Description:       Fast secure, low-cost, borderless, local and international payments in USD powered by blockchain/crypto payment rails. Send and receive secure peer to peer payments to anyone instantly at no cost to you.
 * Version:           1.3.0
 * Author:            Banana Crystal
 * Author URI:        https://www.bananacrystal.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       banana-crystal-payment-gateway
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'WOOCOMMERCE_GATEWAY_BANANA_CRYSTAL_VERSION', '1.3.0' );

/**
 * Shared constants for the AI Agent mode (the new Stores / agent-wallet flow).
 * The gateway id stays 'wo_banana_crystal' so this is one plugin, one listing,
 * with a mode switch — not a separate plugin.
 */
define( 'BC_PAY_GATEWAY_ID', 'wo_banana_crystal' );
define( 'BC_PAY_PATH', plugin_dir_path( __FILE__ ) );
define( 'BC_PAY_URL', plugin_dir_url( __FILE__ ) );
define( 'BC_PAY_VERSION', WOOCOMMERCE_GATEWAY_BANANA_CRYSTAL_VERSION );

/**
 * Base URL for the pay-widget API (lp-api) used by AI Agent mode. Filterable so
 * staging/local can point elsewhere. Paths are under /api/v1/widget/v1/*.
 *   prod    = https://agentic.bananacrystal.com
 *   staging = https://agentic.stg.bananacrystal.com
 */
if ( ! defined( 'BC_PAY_API_BASE' ) ) {
	define( 'BC_PAY_API_BASE', 'https://agentic.bananacrystal.com' );
}

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS)
 * and the Cart/Checkout Blocks, so the gateway isn't flagged incompatible and
 * shows on block checkout (which the classic-only v1 never did).
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			return;
		}
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
);

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-woocommerce-gateway-banana-crystal-activator.php
 */
function activate_woocommerce_gateway_banana_crystal() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-woocommerce-gateway-banana-crystal-activator.php';
	Woocommerce_Gateway_Banana_Crystal_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-woocommerce-gateway-banana-crystal-deactivator.php
 */
function deactivate_woocommerce_gateway_banana_crystal() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-woocommerce-gateway-banana-crystal-deactivator.php';
	Woocommerce_Gateway_Banana_Crystal_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_woocommerce_gateway_banana_crystal' );
register_deactivation_hook( __FILE__, 'deactivate_woocommerce_gateway_banana_crystal' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-woocommerce-gateway-banana-crystal.php';

/**
 * The core helper function file to assist in code
 * 
 */
require plugin_dir_path( __FILE__ ) . 'includes/helpers_functions.php';

/**
 * Load plugin files
 */
add_action( 'plugins_loaded', 'wo_banana_crystal_init', 0 );
function wo_banana_crystal_init() {
    //if condition use to do nothin while WooCommerce is not installed
  if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
  require_once( plugin_dir_path( __FILE__ ) . 'includes/class-banana-crystal-woocommerce.php' );
  // class add it too WooCommerce
  add_filter( 'woocommerce_payment_gateways', 'wo_add_banana_crystal_gateway' );
  function wo_add_banana_crystal_gateway( $methods ) {
    $methods[] = 'Woocommerce_Banana_Crystal';
    return $methods;
  }

  //subscriptions
  require_once( plugin_dir_path( __FILE__ ) . 'includes/class-banana-crystal-subscription.php' );
  $banana_crystal_subscription = new Banana_Crystal_Subscription();

  // AI Agent mode: the Stores / agent-wallet flow. These classes are inert
  // unless the gateway's mode is set to 'agent' (each one checks).
  require_once( plugin_dir_path( __FILE__ ) . 'includes/class-bc-api-client.php' );
  require_once( plugin_dir_path( __FILE__ ) . 'includes/class-bc-order.php' );
  require_once( plugin_dir_path( __FILE__ ) . 'includes/class-bc-webhook.php' );
  require_once( plugin_dir_path( __FILE__ ) . 'includes/class-bc-poller.php' );
  ( new BC_Webhook() )->register();
  ( new BC_Poller() )->register();
}

// Register the gateway on the Cart/Checkout Blocks (both modes redirect, so the
// block method works for legacy and agent alike).
add_action(
  'woocommerce_blocks_loaded',
  static function () {
    if ( ! class_exists( \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType::class ) ) {
      return;
    }
    require_once( plugin_dir_path( __FILE__ ) . 'includes/class-bc-blocks.php' );
    add_action(
      'woocommerce_blocks_payment_method_type_registration',
      static function ( $registry ) {
        $registry->register( new BC_Blocks_Support() );
      }
    );
  }
);

// Stop the agent-mode reconciliation poll when the plugin is deactivated.
register_deactivation_hook(
  __FILE__,
  static function () {
    require_once( plugin_dir_path( __FILE__ ) . 'includes/class-bc-poller.php' );
    BC_Poller::unschedule();
  }
);

// Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wo_banana_crystal_action_links' );
function wo_banana_crystal_action_links( $links ) {
  $plugin_links = array(
    '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'wo-banana-crystal' ) . '</a>',
  );
  return array_merge( $plugin_links, $links );
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_woocommerce_gateway_banana_crystal() {

	$plugin = new Woocommerce_Gateway_Banana_Crystal();
	$plugin->run();

}
run_woocommerce_gateway_banana_crystal();
