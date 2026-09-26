<?php
/**
 * Background safety net: reconcile orders whose buyer paid but never returned
 * (closed the tab before the redirect). Runs every ~5 minutes, polls the order
 * status for pending orders on our gateway, and completes the ones that settled.
 *
 * This is the fallback until the signed settlement webhook (lp-api §3/§4) ships;
 * once it does, the webhook is the primary path and this stays as a backstop.
 * Uses Action Scheduler (bundled with WooCommerce) when available, else WP-Cron.
 */

defined( 'ABSPATH' ) || exit;

class BC_Poller {

	private const HOOK     = 'bc_pay_poll_orders';
	private const INTERVAL = 300;       // 5 minutes
	private const MAX_PER_RUN = 50;     // stay well under the 300/min key limit
	private const WINDOW   = DAY_IN_SECONDS; // only chase the last 24h

	public function register(): void {
		require_once BC_PAY_PATH . 'includes/class-bc-order.php';
		add_action( self::HOOK, array( $this, 'run' ) );
		add_filter( 'cron_schedules', array( $this, 'add_schedule' ) );
		add_action( 'init', array( $this, 'ensure_scheduled' ) );
	}

	public function add_schedule( $schedules ) {
		$schedules['bc_pay_five_minutes'] = array(
			'interval' => self::INTERVAL,
			'display'  => __( 'Every 5 minutes (BananaCrystal)', 'wo-banana-crystal' ),
		);
		return $schedules;
	}

	public function ensure_scheduled(): void {
		if ( function_exists( 'as_schedule_recurring_action' ) && function_exists( 'as_has_scheduled_action' ) ) {
			if ( ! as_has_scheduled_action( self::HOOK ) ) {
				as_schedule_recurring_action( time() + self::INTERVAL, self::INTERVAL, self::HOOK, array(), 'bananacrystal' );
			}
			return;
		}
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + self::INTERVAL, 'bc_pay_five_minutes', self::HOOK );
		}
	}

	/** Stop the recurring job (plugin deactivation). */
	public static function unschedule(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK );
		}
		$ts = wp_next_scheduled( self::HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::HOOK );
		}
	}

	public function run(): void {
		if ( ! function_exists( 'wc_get_orders' ) || ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}
		$gateways = WC()->payment_gateways()->payment_gateways();
		$gw       = $gateways[ BC_PAY_GATEWAY_ID ] ?? null;
		// AI Agent flow only: the legacy site confirms via its own IPN, so there
		// is nothing for the poller to reconcile there.
		if ( ! $gw instanceof WC_Payment_Gateway || 'yes' !== $gw->enabled || 'agent' !== $gw->get_option( 'mode' ) ) {
			return;
		}
		$pk = trim( (string) $gw->get_option( 'publishable_key' ) );
		if ( ! $pk ) {
			return;
		}

		$orders = wc_get_orders(
			array(
				'status'         => array( 'pending', 'on-hold' ),
				'payment_method' => BC_PAY_GATEWAY_ID,
				'date_created'   => '>' . ( time() - self::WINDOW ),
				'limit'          => self::MAX_PER_RUN,
				'orderby'        => 'date',
				'order'          => 'ASC',
			)
		);
		if ( empty( $orders ) ) {
			return;
		}

		$client = new BC_API_Client( $pk );
		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order || $order->is_paid() ) {
				continue;
			}
			$res  = $client->get_order_status( (string) $order->get_id() );
			$code = (int) ( $res['code'] ?? 0 );

			if ( 401 === $code ) {
				// Bad or revoked key — nothing in this run will work. Stop and log.
				error_log( '[BananaCrystal] Poller stopped: 401 from order-status. Check the publishable key.' );
				break;
			}
			if ( empty( $res['ok'] ) ) {
				// 404 (no session for this order yet) or a transient error — skip.
				continue;
			}

			$status = (string) ( $res['status'] ?? '' );
			if ( BC_API_Client::is_paid( $status ) ) {
				if ( $this->matches( $order, $res ) ) {
					BC_Order::mark_paid( $order, (string) ( $res['transaction_id'] ?? '' ), 'poller' );
				} else {
					$order->add_order_note(
						__( 'BananaCrystal poller: settled amount/currency did not match the order. Left for manual review.', 'wo-banana-crystal' )
					);
				}
			}
			// expired / failed: leave pending on purpose. The buyer can retry the
			// same reference, which reopens the session; the 24h window ages it
			// out on its own, matching the return handler's caution.

			usleep( 150000 ); // ~150ms between calls — comfortably under 300/min.
		}
	}

	/** Amount comes back normalized (e.g. "25"), so compare numerically. */
	private function matches( WC_Order $order, array $res ): bool {
		$amt = isset( $res['amount'] ) && '' !== $res['amount'] ? (float) $res['amount'] : null;
		$cur = strtoupper( (string) ( $res['currency'] ?? '' ) );
		$ok_amount   = null === $amt || abs( $amt - (float) $order->get_total() ) <= 0.01;
		$ok_currency = '' === $cur || $cur === strtoupper( $order->get_currency() );
		return $ok_amount && $ok_currency;
	}
}
