<?php
/**
 * One place that flips a WooCommerce order to paid, so the webhook and the
 * return handler behave identically and never double-complete an order.
 */

defined( 'ABSPATH' ) || exit;

class BC_Order {

	/**
	 * Mark an order paid exactly once. `payment_complete()` reduces stock,
	 * empties the cart and moves the order to processing/completed.
	 *
	 * @param WC_Order $order
	 * @param string   $transaction_id lp-api transaction id, if known.
	 * @param string   $via            'webhook' | 'return' — for the order note.
	 */
	public static function mark_paid( WC_Order $order, string $transaction_id = '', string $via = 'webhook' ): void {
		if ( $order->is_paid() || $order->has_status( array( 'processing', 'completed' ) ) ) {
			return; // idempotent: a webhook and a return can both arrive.
		}

		$order->add_order_note(
			sprintf(
				/* translators: 1: source (webhook/return), 2: transaction id */
				__( 'BananaCrystal payment confirmed via %1$s. Transaction: %2$s', 'wo-banana-crystal' ),
				$via,
				$transaction_id ? $transaction_id : '—'
			)
		);

		$order->payment_complete( $transaction_id );
	}
}
