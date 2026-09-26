<?php
/**
 * Registers BananaCrystal on the WooCommerce Cart/Checkout Blocks. Without this
 * the gateway is invisible on block-based checkouts (the modern default). Both
 * modes redirect, so the block method works for legacy and agent alike.
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class BC_Blocks_Support extends AbstractPaymentMethodType {

	protected $name = BC_PAY_GATEWAY_ID;

	/** @var WC_Payment_Gateway|null */
	private $gateway;

	public function initialize() {
		$this->settings = get_option( 'woocommerce_' . BC_PAY_GATEWAY_ID . '_settings', array() );
		$gateways       = WC()->payment_gateways()->payment_gateways();
		$this->gateway  = $gateways[ BC_PAY_GATEWAY_ID ] ?? null;
	}

	public function is_active() {
		return $this->gateway ? 'yes' === $this->gateway->enabled : false;
	}

	public function get_payment_method_script_handles() {
		$handle = 'bc-pay-blocks';
		wp_register_script(
			$handle,
			BC_PAY_URL . 'assets/js/blocks.js',
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
			BC_PAY_VERSION,
			true
		);
		return array( $handle );
	}

	public function get_payment_method_data() {
		return array(
			'title'       => $this->get_setting( 'title', __( 'Pay with BananaCrystal', 'wo-banana-crystal' ) ),
			'description' => $this->get_setting( 'description', '' ),
			'supports'    => $this->gateway ? array_values( array_intersect( array( 'products' ), $this->gateway->supports ) ) : array( 'products' ),
			'icon'        => apply_filters( 'bc_pay_icon', BC_PAY_URL . 'public/img/bananacrystal-logo-square.png' ),
		);
	}
}
