(function($){

    $(document).ready(function() {

        var cardRegex = /^(?:4[0-9]{12}(?:[0-9]{3})?|4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|6[3-5][0-9]{14}|3[47][0-9]{13}|3(?:0[0-5]|[68][0-9])[0-9]{11}|6(?:011|5[0-9]{2})[0-9]{12}(?:2131|1800|35\d{3})\d{11})$/;
        var cvcRegex = /^[0-9]{3,4}$/;
        var expiryDateRegex = /^(0[1-9]|1[0-2])\/?([0-9]{4}|[0-9]{2})$/;
        var emailRegex = /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;

        function traceId() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
              var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
              return v.toString(16);
            });
        }

        function isState(state, stateIsRequired) {
            if (stateIsRequired && state.length < 1) {
                return false;
            }
            return true;
        }

        function isGbPostcode(post_code) {

            var pcexp = [];

            pcexp[0] = /^([abcdefghijklmnoprstuwyz]{1}[abcdefghklmnopqrstuvwxy]{0,1}[0-9]{1,2})([0-9]{1}[abdefghjlnpqrstuwxyz]{2})$/;
            pcexp[1] = /^([abcdefghijklmnoprstuwyz]{1}[0-9]{1}[abcdefghjkpstuw]{1})([0-9]{1}[abdefghjlnpqrstuwxyz]{2})$/;
            pcexp[2] = /^([abcdefghijklmnoprstuwyz]{1}[abcdefghklmnopqrstuvwxy][0-9]{1}[abehmnprvwxy])([0-9]{1}[abdefghjlnpqrstuwxyz]{2})$/;
            pcexp[3] = /^(gir)(0aa)$/;
            pcexp[4] = /^(bfpo)([0-9]{1,4})$/;
            pcexp[5] = /^(bfpo)(c\/o[0-9]{1,3})$/;

            var postcode = post_code.toLowerCase();
            postcode = postcode.replace(' ', '');

            for (var i = 0; i < pcexp.length; i++) {
                if (pcexp[i].test(postcode)) {
                  return true;
                }
            }

            return false;
        }

        function normalizePostcode(post_code) {
            return post_code.replace(/[\s\-]/, '').toUpperCase().trim();
        }

        function isPostcode(post_code, country) {
            switch(country) {
                case 'AT':
                    return /^([0-9]{4})$/.test(post_code);
                case 'BA':
                    return /^([7-8]{1})([0-9]{4})$/.test(post_code);
                case 'BE':
                        return /^([0-9]{4})$/i.test(post_code);
                case 'BR':
                        return /^([0-9]{5})([-])?([0-9]{3})$/.test(post_code);
                case 'CH':
                    return /^([0-9]{4})$/i.test(post_code);
                case 'DE':
                    return /^([0]{1}[1-9]{1}|[1-9]{1}[0-9]{1})[0-9]{3}$/.test(post_code);
                case 'ES':
                case 'FR':
                case 'IT':
                    return /^([0-9]{5})$/i.test(post_code);
                case 'GB':
                    return isGbPostcode(post_code);
                case 'HU':
                    return /^([0-9]{4})$/i.test(post_code);
                case 'IE':
                    return /([AC-FHKNPRTV-Y]\d{2}|D6W)[0-9AC-FHKNPRTV-Y]{4}/.test(normalizePostcode(post_code));
                case 'IN':
                    return /^[1-9]{1}[0-9]{2}\s{0,1}[0-9]{3}$/.test(post_code);
                case 'JP':
                    return /^([0-9]{3})([-]?)([0-9]{4})$/.test(post_code);
                case 'PT':
                    return /^([0-9]{4})([-])([0-9]{3})$/.test(post_code);
                case 'PR':
                case 'US':
                    return /^([0-9]{5})(-[0-9]{4})?$/i.test(post_code);
                case 'CA':
                    return /^([ABCEGHJKLMNPRSTVXY]\d[ABCEGHJKLMNPRSTVWXYZ])([\ ])?(\d[ABCEGHJKLMNPRSTVWXYZ]\d)$/i.test(post_code);
                case 'PL':
                    return /^([0-9]{2})([-])([0-9]{3})$/.test(post_code);
                case 'CZ':
                case 'SK':
                    return /^([0-9]{3})(\s?)([0-9]{2})$/.test(post_code);
                case 'NL':
                    return /^([1-9][0-9]{3})(\s?)(?!SA|SD|SS)[A-Z]{2}$/i.test(post_code);
                case 'SI':
                    return /^([1-9][0-9]{3})$/.test(post_code);
                case 'LI':
                    return /^(94[8-9][0-9])$/.test(post_code);
                default:
                    return true;
            }
        }

        function isPhone(phone) {

            var phone_number = phone.replace(/[\s\#0-9_\-\+\/\(\)\.]/g, '').trim();

            if (phone_number.length > 0) {
                return false;
            }
    
            return true;
        }

        function isCity(city) {
            return city.length > 0 && city.length < 31;
        }

        function isName(name) {
            if (/[0-9|\\~¡¢∞§¶•ªº–≠‘“«…≥÷≤∑œ´®™†¥¨^π¬˚∆˙©<>{}!#€$£₺₴₹%&()*+,./:;=?@"[\]^`]+/.test(name)) {
                return false;
            }
            return true;
        }

        function formValidation(card_number, cvc, exp_date, first_name, last_name, country, state, stateIsRequired, address, city, post_code, phone, email) {

            return cardRegex.test(card_number) &&
                    cvcRegex.test(cvc) &&
                    expiryDateRegex.test(exp_date) &&
                    isName(first_name) &&
                    isName(last_name) &&
                    country.length >= 2 &&
                    isState(state, stateIsRequired) &&
                    address.length > 0 &&
                    isCity(city) &&
                    isPostcode(post_code, country) &&
                    isPhone(phone) &&
                    emailRegex.test(email);
        }

        function createHiddenIFrame() {
            const frame = document.createElement('iframe');
            frame.setAttribute('name', 'hidden-frame');
            frame.setAttribute('id', 'hiddenFrame');
            frame.setAttribute('width', '0');
            frame.setAttribute('height', '0');
            frame.setAttribute('border', '0');
            document.body.appendChild(frame);
        }

        function createStForm() {
            const form = document.createElement('form');
            form.setAttribute('method', 'post');
            form.setAttribute('action', 'https://www.example.com');
            form.setAttribute('id', 'st-form');
            form.setAttribute('target', 'hidden-frame');
            document.body.appendChild(form);
        }

        var checkout_form = $( 'form.checkout' );
        checkout_form.on( 'checkout_place_order', function(event) {
            var isMifinityPaymentChecked = $('#payment_method_mifinity_payment').is(':checked');
            if (isMifinityPaymentChecked) {
                if (window.threeDsResp) {
                    window.threeDsResp = undefined;
                    return true;
                } else {
                    var card_number = $('#mifinity_payment-card-number').val().replace(/ /g,'');
                    var cvc = $('#mifinity_payment-card-cvc').val();
                    var exp_date = $('#mifinity_payment-card-expiry').val().replace(/ /g,'');

                    var first_name = $('#billing_first_name').val();
                    var last_name = $('#billing_last_name').val();
                    var country = $('#billing_country').val();
                    var state = $('#billing_state').val();
                    var stateIsRequired = $('#billing_state_field').hasClass('validate-required');
                    var address = $('#billing_address_1').val();
                    var city = $('#billing_city').val();
                    var post_code = $('#billing_postcode').val();
                    var phone = $('#billing_phone').val();
                    var email = $('#billing_email').val();

                    if (formValidation(card_number, cvc, exp_date, first_name, last_name, country, state, stateIsRequired, address, city, post_code, phone, email)) {

                        var billing_name = $('#billing_first_name').val() + ' ' + $('#billing_last_name').val();
                        var simulation_url = scriptParams.endpoint_url + '/simulate';
        
                        var body = {
                            money: {
                                amount: scriptParams.amount,
                                currency: scriptParams.currency
                            },
                            cardNumber: card_number,
                            billingName: billing_name,
                            expiryDate: exp_date,
                            traceId: traceId(),
                            cvc: cvc,
                            descriptor: scriptParams.descriptor
                        };

                        // place loader on checkout screen
                        var a = '.site-content';
                        $(a).block({
                            message: null,
                            overlayCSS: {
                                background: "#fff",
                                opacity: .6
                            }
                        });

                        // remove 3ds2 object if exists
                        if ($('#three-ds-resp').length !== 0) {
                            $('#three-ds-resp').remove();
                        }

                        // create hidden iFrame for ST
                        const hiddenFrame = document.getElementById('hiddenFrame');
                        if (!hiddenFrame) {
                            createHiddenIFrame();
                        }

                        // create ST form
                        const stForm = document.getElementById('st-form');
                        if (!stForm) {
                            createStForm();
                        }
        
                        // call for 3ds2 jwt token
                        $.ajax({
                            url: simulation_url,
                            method: 'POST',
                            contentType: 'application/json',
                            dataType: 'json',
                            data: JSON.stringify(body),
                            headers: {
                                'Content-Type': 'application/json',
                                'api-version': 1,
                                'key': scriptParams.key
                            },
                            success: function(result) {
                                var jwt = result.payload[0]['jwt'];
                                function afterPayment(data) {
                                    window.threeDsResp = true;

                                    // remove loader from checkout screen
                                    $(a).unblock();
        
                                    // create 3ds2 object
                                    var threeDS2 = {
                                        threeDResponse: data.threedresponse,
                                        parentTransactionReference: data.transactionreference,
                                        enrolled: data.enrolled,
                                        errorCode: data.errorcode,
                                        errorMessage: data.errormessage,
                                        settleStatus: data.settlestatus
                                    };
                                    var dataString = JSON.stringify(threeDS2);
        
                                    // place 3ds2 object in a hidden field
                                    if ($('#three-ds-resp').length == 0) {
                                        checkout_form.append("<input type='hidden' id='three-ds-resp' name='three-ds-resp' value='" + dataString + "'>");
                                    }
                                    $('#place_order').trigger('click');
                                }
                                var st = SecureTrading({livestatus: parseInt(scriptParams.live_status), jwt: jwt, submitOnError: false, submitCallback: afterPayment});
                                st.Components({startOnLoad: true, requestTypes:['THREEDQUERY']});
                            },
                            error: function(error) {
                                var errorMessage = '';
                                if (error.responseJSON && error.responseJSON.errors) {
                                    errorMessage = error.responseJSON.errors[0].message;
                                }

                                // remove loader from checkout screen and place error message
                                $(a).unblock();
                                $('form.checkout').prepend("<div class='woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout'><ul class='woocommerce-error' role='alert'><li>" + errorMessage + "</li</ul></div>");
                                $('html, body').animate({ scrollTop: 0 }, "slow");
                            }
                        });
                        return false;
                    } else {
                        return true;
                    }
                }
            } else {
                return true;
            }
        });
    });

})(jQuery);