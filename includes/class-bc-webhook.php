<?php
/**
 * Receives settlement webhooks from lp-api and marks the matching WooCommerce
 * order paid. This is the reliable, server-to-server confirmation path (the
 * return handler is only a fallback).
 *
 * Signature (spec §3, Stripe-style, replay-safe):
 *   header  BananaCrystal-Signature: t=<unix>,v1=<hex hmac-sha256>
 *   v1 = HMAC_SHA256(secret_key, "{t}.{raw_body}")
 * Reject if the HMAC doesn't match or `t` is older than the tolerance.
 *
 * TODO(BE §4): outbound webhook + HMAC do not exist yet. This handler is ready
 * to consume them; until then it simply never fires.
 */

defined( 'ABSPATH' ) || exit;

class BC_Webhook {

	private const TOLERANCE_SECONDS = 300;

	public function register(): void {
		require_once BC_PAY_PATH . 'includes/class-bc-order.php';
		add_action( 'woocommerce_api_' . BC_PAY_GATEWAY_ID . '_webhook', array( $this, 'handle' ) );
	}

	private function gateway(): ?WC_Payment_Gateway {
		$gateways = WC()->payment_gateways()->payment_gateways();
		$gw       = $gateways[ BC_PAY_GATEWAY_ID ] ?? null;
		return $gw instanceof WC_Payment_Gateway ? $gw : null;
	}

	public function handle(): void {
		$gw = $this->gateway();
		// Webhooks belong to the AI Agent flow only; the legacy site uses its own
		// IPN endpoint. Ignore quietly in any other mode.
		if ( ! $gw || 'agent' !== $gw->get_option( 'mode' ) ) {
			status_header( 200 );
			exit;
		}

		$raw    = file_get_contents( 'php://input' );
		$sig    = isset( $_SERVER['HTTP_BANANACRYSTAL_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_BANANACRYSTAL_SIGNATURE'] ) ) : '';
		$secret = (string) $gw->get_option( 'secret_key' );

		if ( ! $secret || ! $this->verify( $raw, $sig, $secret ) ) {
			status_header( 401 );
			echo wp_json_encode( array( 'error' => 'invalid signature' ) );
			exit;
		}

		$event = json_decode( (string) $raw, true );
		if ( ! is_array( $event ) ) {
			status_header( 400 );
			exit;
		}

		$order = $this->resolve_order( $event );
		if ( ! $order ) {
			// 200 so lp-api stops retrying an event we can't map (e.g. test ping).
			status_header( 200 );
			exit;
		}

		$status = (string) ( $event['status'] ?? $event['event'] ?? '' );
		if ( BC_API_Client::is_paid( $status ) || 'payment.settled' === $status ) {
			$this->assert_amount( $order, $event );
			BC_Order::mark_paid( $order, (string) ( $event['transaction_id'] ?? '' ), 'webhook' );
		} elseif ( BC_API_Client::is_failed( $status ) || 'payment.failed' === $status ) {
			if ( ! $order->is_paid() ) {
				$order->update_status( 'failed', __( 'BananaCrystal reported the payment failed.', 'wo-banana-crystal' ) );
			}
		}

		status_header( 200 );
		echo wp_json_encode( array( 'received' => true ) );
		exit;
	}

	private function verify( string $raw, string $sig, string $secret ): bool {
		$parts = array();
		foreach ( explode( ',', $sig ) as $p ) {
			$kv = explode( '=', $p, 2 );
			if ( 2 === count( $kv ) ) {
				$parts[ trim( $kv[0] ) ] = trim( $kv[1] );
			}
		}
		$t  = isset( $parts['t'] ) ? (int) $parts['t'] : 0;
		$v1 = $parts['v1'] ?? '';
		if ( ! $t || ! $v1 ) {
			return false;
		}
		if ( abs( time() - $t ) > self::TOLERANCE_SECONDS ) {
			return false; // replay window
		}
		$expected = hash_hmac( 'sha256', $t . '.' . $raw, $secret );
		return hash_equals( $expected, $v1 );
	}

	private function resolve_order( array $event ): ?WC_Order {
		$order_id = isset( $event['order_id'] ) ? absint( preg_replace( '/\D/', '', (string) $event['order_id'] ) ) : 0;
		if ( ! $order_id && ! empty( $event['reference'] ) ) {
			$order_id = absint( preg_replace( '/\D/', '', (string) $event['reference'] ) ); // "wc_1042" → 1042
		}
		$order = $order_id ? wc_get_order( $order_id ) : false;
		return $order instanceof WC_Order ? $order : null;
	}

	/** Log, don't block, if the settled amount disagrees with the order total. */
	private function assert_amount( WC_Order $order, array $event ): void {
		$paid = isset( $event['amount'] ) ? (float) $event['amount'] : null;
		if ( null !== $paid && abs( $paid - (float) $order->get_total() ) > 0.01 ) {
			$order->add_order_note(
				sprintf(
					/* translators: 1: settled amount, 2: order total */
					__( 'BananaCrystal settled %1$s but the order total is %2$s — please review.', 'wo-banana-crystal' ),
					$paid,
					$order->get_total()
				)
			);
		}
	}
}
