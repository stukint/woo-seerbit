jQuery( function( $ ) {
    let seerbit_submit = false;

	$( '#wc-seerbit-form' ).hide();

    wcSeerbitFormHandler();

    jQuery( '#seerbit-payment-button' ).click( function() {
		return wcSeerbitFormHandler();
	} );

    jQuery( '#seerbit_form form#order_review' ).submit( function() {
		return wcSeerbitFormHandler();
	} );

    function wcSeerbitFormHandler() {
        
        $( '#wc-seerbit-form' ).hide();

		if ( seerbit_submit ) {
			seerbit_submit = false;
			return true;
		}

		let $form = $( 'form#payment-form, form#order_review' ),
			seerbit_tranref = $form.find( 'input.seerbit_tranref' ),
			split_code = '';
            tokenize = false;

        seerbit_tranref.val('');
        
        let amount = Number( wc_seerbit_params.amount );

        let seerbit_callback = function(response, closeModal) {
            $form.append( '<input type="hidden" class="seerbit_tranref" name="seerbit_tranref" value="' + response.payments.reference + '"/>' );
            seerbit_submit = true;
    
            $form.submit();
    
            $( 'body' ).block( {
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                },
                css: {
                    cursor: "wait"
                }
            } );
        };

        let seerbit_close_callback = function(){
            $( '#wc-seerbit-form' ).show();
				$( this.el ).unblock();
        }
    
        let paymentData = {
            public_key: wc_seerbit_params.public_key,
            email: wc_seerbit_params.email,
            currency: wc_seerbit_params.currency,
            tranref: wc_seerbit_params.tranref,
            amount: amount,
            description: wc_seerbit_params.description,
            full_name: wc_seerbit_params.full_name,
            customization: {
                confetti: false
            }
        }

        if(wc_seerbit_params.split_code){
            paymentData['splitCode'] = wc_seerbit_params.split_code;
        }

        if(wc_seerbit_params.tokenize){
            paymentData['tokenize'] = wc_seerbit_params.tokenize;
        }

        if(wc_seerbit_params.payment_methods){
            paymentData.customization['payment_method'] = wc_seerbit_params.payment_methods;
        }

        SeerbitPay (paymentData, seerbit_callback, seerbit_close_callback);
    }

});