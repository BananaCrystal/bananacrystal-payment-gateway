<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://www.bananacrystal.com/
 * @since      1.0.0
 *
 * @package    Woocommerce_Gateway_Banana_Crystal
 * @subpackage Woocommerce_Gateway_Banana_Crystal/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Woocommerce_Gateway_Banana_Crystal
 * @subpackage Woocommerce_Gateway_Banana_Crystal/includes
 * @author     Esther Villars < esthervillars@gmail.com>
 */
class Woocommerce_Gateway_Banana_Crystal_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'woocommerce-gateway-banana-crystal',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
