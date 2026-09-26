/**
 * Shows only the settings for the selected Mode on the BananaCrystal gateway
 * page: AI Agent fields (publishable/secret key) or Legacy fields (store
 * username, subscriptions). Runs only where the Mode dropdown is present.
 */
( function ( $ ) {
	var PREFIX = '#woocommerce_wo_banana_crystal_';
	var AGENT = [ 'publishable_key' ];
	var LEGACY = [ 'store_username', 'subscriptions_enabled', 'subscription_key' ];

	function rowsFor( keys ) {
		return keys.map( function ( key ) {
			return $( PREFIX + key ).closest( 'tr' );
		} );
	}

	function apply() {
		var mode = $( PREFIX + 'mode' ).val();
		rowsFor( AGENT ).forEach( function ( $row ) {
			$row.toggle( mode === 'agent' );
		} );
		rowsFor( LEGACY ).forEach( function ( $row ) {
			$row.toggle( mode === 'legacy' );
		} );
	}

	$( function () {
		var $mode = $( PREFIX + 'mode' );
		if ( ! $mode.length ) {
			return;
		}
		apply();
		$mode.on( 'change', apply );
	} );
} )( jQuery );
