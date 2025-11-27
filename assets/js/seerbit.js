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
            payment_methods = [];

        seerbit_tranref.val('');
        
        let amount = Number( wc_seerbit_params.amount );

        if(wc_seerbit_params.split_code){
            split_code = wc_seerbit_params.split_code;
        }

        if(wc_seerbit_params.tokenize){
            tokenize = true;
        }

        if(wc_seerbit_params.payment_methods){
            payment_methods = wc_seerbit_params.payment_methods;
        }

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
            country: wc_seerbit_params.country,
            tranref: wc_seerbit_params.tranref,
            amount: amount,
            description: wc_seerbit_params.description,
            full_name: wc_seerbit_params.full_name,
            tokenize: tokenize,
            splitCode: split_code,
            customization: {
                confetti: false,
                payment_method: payment_methods
            }
        }
        console.log(paymentData);
        SeerbitPay (paymentData, seerbit_callback, seerbit_close_callback);
    }

});