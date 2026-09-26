/**
 * Registers BananaCrystal as a payment method on the WooCommerce Checkout Block.
 *
 * It is a redirect gateway: no card fields are collected in the checkout, so the
 * content is just the merchant's description. On "Place order", process_payment()
 * (server side) returns the hosted checkout URL and WooCommerce redirects there.
 */
( function () {
	const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
	const { createElement } = window.wp.element;
	const { decodeEntities } = window.wp.htmlEntities;

	const settings = window.wc.wcSettings.getSetting( 'wo_banana_crystal_data', {} );
	const title = decodeEntities( settings.title || 'Pay with BananaCrystal' );
	const description = decodeEntities( settings.description || '' );

	const Content = () => createElement( 'p', null, description );

	const Label = () => {
		if ( settings.icon ) {
			return createElement(
				'span',
				{ style: { display: 'flex', alignItems: 'center', gap: '8px' } },
				createElement( 'img', { src: settings.icon, alt: title, style: { height: '20px' } } ),
				createElement( 'span', null, title )
			);
		}
		return createElement( 'span', null, title );
	};

	registerPaymentMethod( {
		name: 'wo_banana_crystal',
		label: createElement( Label, null ),
		content: createElement( Content, null ),
		edit: createElement( Content, null ),
		canMakePayment: () => true,
		ariaLabel: title,
		supports: {
			features: ( settings.supports || [ 'products' ] ),
		},
	} );
} )();
