jQuery( function( $ ) {
    'use strict';

	/**
	 * Object to handle Seerbit admin functions.
	 */
	var wc_seerbit_admin = {
		/**
		 * Initialize.
		 */
		init: function() {
			// Toggle api key settings.
			$( document.body ).on( 'change', '#woocommerce_seerbit_testmode', function() {
				var test_secret_key = $( '#woocommerce_seerbit_test_secret_key' ).parents( 'tr' ).eq( 0 ),
					test_public_key = $( '#woocommerce_seerbit_test_public_key' ).parents( 'tr' ).eq( 0 ),
					live_secret_key = $( '#woocommerce_seerbit_live_secret_key' ).parents( 'tr' ).eq( 0 ),
					live_public_key = $( '#woocommerce_seerbit_live_public_key' ).parents( 'tr' ).eq( 0 );

				if ( $( this ).is( ':checked' ) ) {
					test_secret_key.show();
					test_public_key.show();
					live_secret_key.hide();
					live_public_key.hide();
				} else {
					test_secret_key.hide();
					test_public_key.hide();
					live_secret_key.show();
					live_public_key.show();
				}
			});

			$( '#woocommerce_seerbit_testmode' ).change();

			$( document.body ).on( 'change', '.woocommerce_seerbit_split_payment', function() {
				var split_code = $( '.woocommerce_seerbit_split_code' ).parents( 'tr' );

				if ( $( this ).is( ':checked' ) ) {
					split_code.show();
				}else{
					split_code.hide();
				}
			});

			$( '#woocommerce_seerbit_split_payment' ).change();

			$( document.body ).on( 'change', '.woocommerce_seerbit_tokenize_cards', function() {
				var saved_cards = $( '.woocommerce_seerbit_saved_cards' ).parents( 'tr' ).eq( 0 );

				if ( $( this ).is( ':checked' ) ) {
					saved_cards.show();
				}else{
					saved_cards.hide();
				}
			});

			$( '#woocommerce_seerbit_tokenize_cards' ).change();

			$( '#woocommerce_seerbit_test_secret_key, #woocommerce_seerbit_live_secret_key' ).after(
				'<button class="wc-seerbit-toggle-secret" style="height: 30px; margin-left: 2px; cursor: pointer"><span class="dashicons dashicons-visibility"></span></button>'
			);

			$( '.wc-seerbit-toggle-secret' ).on( 'click', function( event ) {
				event.preventDefault();

				let $dashicon = $( this ).closest( 'button' ).find( '.dashicons' );
				let $input = $( this ).closest( 'tr' ).find( '.input-text' );
				let inputType = $input.attr( 'type' );

				if ( 'text' == inputType ) {
					$input.attr( 'type', 'password' );
					$dashicon.removeClass( 'dashicons-hidden' );
					$dashicon.addClass( 'dashicons-visibility' );
				} else {
					$input.attr( 'type', 'text' );
					$dashicon.removeClass( 'dashicons-visibility' );
					$dashicon.addClass( 'dashicons-hidden' );
				}
			});
		}
	}

    wc_seerbit_admin.init();
});