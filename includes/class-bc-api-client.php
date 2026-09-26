<?php
/**
 * Thin client for the BananaCrystal pay-widget / Stores API.
 *
 * Every payment routes through a hosted checkout session, the same rails the
 * Agentic Pay Button uses. This class owns the two calls the gateway needs:
 * create a session (redirect the shopper) and read its status (confirm the
 * order). Auth is the store's publishable key.
 *
 * lp-api status values: created | otp_sent | settled | failed.
 * The gateway treats `settled` as paid and `failed` as failed; the rest are
 * still pending.
 */

defined( 'ABSPATH' ) || exit;

class BC_API_Client {

	/** @var string pk_live_… — resolves the store + agent wallet server-side. */
	private $publishable_key;

	public function __construct( string $publishable_key ) {
		$this->publishable_key = $publishable_key;
	}

	private function base(): string {
		return rtrim( (string) apply_filters( 'bc_pay_api_base', BC_PAY_API_BASE ), '/' );
	}

	/**
	 * Create a hosted checkout session for a WooCommerce order.
	 *
	 * TODO(BE §1): the session must accept order_id, order_description,
	 * return_url and reference (idempotent on (pk, reference)) and return a
	 * checkout_url. Until it does, `checkout_url` will be absent and
	 * process_payment() surfaces a clear error instead of a broken redirect.
	 *
	 * @return array{ok:bool, checkout_url?:string, session_id?:string, error?:string}
	 */
	public function create_session( array $args ): array {
		$body = array(
			'pk'               => $this->publishable_key,
			'amount'           => $args['amount'],            // string, e.g. "19.99"
			'display_currency' => $args['currency'],
			'order_id'         => $args['order_id'],
			'order_description' => $args['description'],
			'return_url'       => $args['return_url'],
			'reference'        => $args['reference'],         // idempotency key
			'origin'           => $args['origin'],
		);

		$res = wp_remote_post(
			$this->base() . '/api/v1/widget/v1/session',
			array(
				'timeout' => 20,
				'headers' => array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $res ) ) {
			return array( 'ok' => false, 'error' => $res->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );

		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			$msg = is_array( $data ) && isset( $data['message'] ) ? (string) $data['message'] : sprintf( 'Unexpected response (%d).', $code );
			return array( 'ok' => false, 'error' => $msg );
		}

		$checkout_url = $data['checkout_url'] ?? '';
		if ( ! $checkout_url ) {
			return array( 'ok' => false, 'error' => 'No checkout_url returned (backend session support is not live yet).' );
		}

		return array(
			'ok'           => true,
			'checkout_url' => (string) $checkout_url,
			'session_id'   => (string) ( $data['session_id'] ?? '' ),
		);
	}

	/**
	 * Read a session's settlement status. Used on the return page and to
	 * reconcile a missed webhook.
	 *
	 * TODO(BE §2/§4): a pk-gated /session/:id/status carrying order_id +
	 * transaction_id; ideally also lookup by order_id.
	 *
	 * @return array{ok:bool, status?:string, amount?:string, currency?:string, transaction_id?:string, error?:string}
	 */
	public function get_session_status( string $session_id ): array {
		$res = wp_remote_get(
			$this->base() . '/api/v1/widget/v1/session/' . rawurlencode( $session_id ) . '/status',
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'        => 'application/json',
					'Authorization' => 'Bearer ' . $this->publishable_key,
				),
			)
		);

		if ( is_wp_error( $res ) ) {
			return array( 'ok' => false, 'error' => $res->get_error_message() );
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $data ) ) {
			return array( 'ok' => false, 'error' => 'Bad status response.' );
		}

		return array(
			'ok'             => true,
			'status'         => (string) ( $data['status'] ?? '' ),
			'amount'         => (string) ( $data['amount'] ?? '' ),
			'currency'       => (string) ( $data['currency'] ?? '' ),
			'transaction_id' => (string) ( $data['transaction_id'] ?? '' ),
		);
	}

	/**
	 * Look a WooCommerce order up by its id (the one sent at session creation).
	 * Used by the background poller as a fallback when the buyer never returned.
	 *
	 * Carries the HTTP code so the caller can tell apart: 404 (no session yet →
	 * skip), 401 (bad/revoked key → stop the run), and a real result.
	 *
	 * @return array{ok:bool, code:int, status?:string, amount?:string, currency?:string, transaction_id?:string, error?:string}
	 */
	public function get_order_status( string $order_id ): array {
		$res = wp_remote_get(
			$this->base() . '/api/v1/widget/v1/orders/' . rawurlencode( $order_id ) . '/status',
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'        => 'application/json',
					'Authorization' => 'Bearer ' . $this->publishable_key,
				),
			)
		);

		if ( is_wp_error( $res ) ) {
			return array( 'ok' => false, 'code' => 0, 'error' => $res->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );

		if ( 200 !== $code || ! is_array( $data ) ) {
			return array( 'ok' => false, 'code' => $code );
		}

		return array(
			'ok'             => true,
			'code'           => $code,
			'status'         => (string) ( $data['status'] ?? '' ),
			'amount'         => (string) ( $data['amount'] ?? '' ),
			'currency'       => (string) ( $data['currency'] ?? '' ),
			'transaction_id' => (string) ( $data['transaction_id'] ?? '' ),
		);
	}

	/** Whether a lp-api status means the money has settled. */
	public static function is_paid( string $status ): bool {
		return 'settled' === $status;
	}

	/** Whether a lp-api status is terminal-failed. */
	public static function is_failed( string $status ): bool {
		return in_array( $status, array( 'failed', 'expired', 'cancelled' ), true );
	}
}
