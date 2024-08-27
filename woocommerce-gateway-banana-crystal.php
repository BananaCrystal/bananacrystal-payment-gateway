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
 * Version:           1.2.6
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
define( 'WOOCOMMERCE_GATEWAY_BANANA_CRYSTAL_VERSION', '1.2.6' );

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
}

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
