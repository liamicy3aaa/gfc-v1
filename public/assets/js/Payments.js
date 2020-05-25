var Payments = {

    settings: {
        publicKey: "",
        transactionId: "",
        serverUrl: "/payments/process/",
        form: "#payment-form",
        error: "#payment-errors",
        haultUI: function(status) {},
        inputs: {
            cardNumber: "#card-number",
            cardExpiry: "#card-expiry",
            cardCvc: "#card-cvc",
            submitBtn: {
                text: "#form-BtnTxt",
                loader: "#form-loader"
            }
        },
        onComplete: function() {

            $(Payments.settings.error).html("Payment complete.");
            $(Payments.settings.error).removeClass("alert-danger");
            $(Payments.settings.error).addClass("alert-success");
            $(Payments.settings.error).show();

        },
        onError: function(error) {
            var mess;

            if(typeof error == "string"){

                mess = error;

            } else {

                mess = error.message;

            }

            $(Payments.settings.error).html(mess).show();

        }
    },

    start: function(publicKey, transaction, settings) {

        this.settings.publicKey = publicKey;
        this.settings.transactionId = transaction;
        this.settings.Stripe = Stripe(this.settings.publicKey);

        $.extend(this.settings, settings);

        this.startPaymentHandler();

    },

    startPaymentHandler: function() {

        var elements = Payments.settings.Stripe.elements();

        var classes = {
            base: "form-control",
            invalid: "is-invalid",
            complete: "is-valid",
            webkitAutofill: "text-info"
        };

        // Creating stripe elements
        var cardNumber = elements.create('cardNumber', {classes: classes});
        var cardExpiry = elements.create('cardExpiry', {classes: classes});
        var cardCvc = elements.create('cardCvc', {classes: classes});

        // Mounting them to the containers
        cardNumber.mount(Payments.settings.inputs.cardNumber);
        cardExpiry.mount(Payments.settings.inputs.cardExpiry);
        cardCvc.mount(Payments.settings.inputs.cardCvc);

        // Displaying any errors for card number
        cardNumber.addEventListener('change', ({error}) => {
            let errorItem = Payments.settings.inputs.cardNumber + "-errors";
            const displayError = $(errorItem);

            if (error) {
                displayError.html(error.message);
            } else {
                displayError.html('');
            }
        });

        cardExpiry.addEventListener('change', ({error}) => {
            let errorItem = Payments.settings.inputs.cardExpiry + "-errors";
            const displayError = $(errorItem);

            if (error) {
                displayError.html(error.message);
            } else {
                displayError.html('');
            }
        });

        cardCvc.addEventListener('change', ({error}) => {
            let errorItem = Payments.settings.inputs.cardCvc + "-errors";
            const displayError = $(errorItem);

            if (error) {
                displayError.html(error.message);
            } else {
                displayError.html('');
            }
        });

        // On form submit, prevent normal submission and process through Stripe
        var form = $(Payments.settings.form);

        form.on('submit', function (event) {
            // We don't want to let default form submission happen here,
            // which would refresh the page.
            event.preventDefault();
            Payments.settings.haultUI(true);

            $(Payments.settings.inputs.submitBtn.text).hide();
            $(Payments.settings.inputs.submitBtn.loader).show();
            $(Payments.settings.error).hide();
            $(Payments.settings.inputs.submitBtn.text).closest("button").attr("disabled", "disabled");
            $(Payments.settings.inputs.submitBtn.text).closest("button").addClass("disabled");

        Payments.settings.Stripe.createPaymentMethod({
                type: 'card',
                card: cardNumber
            }).then(Payments.stripeHandler);
        });

    },

    stripeHandler: function (result) {
        if (result.error) {

            $(Payments.settings.inputs.submitBtn.text).show();
            $(Payments.settings.inputs.submitBtn.loader).hide();
            $(Payments.settings.inputs.submitBtn.text).closest("button").removeAttr("disabled");
            $(Payments.settings.inputs.submitBtn.text).closest("button").removeClass("disabled");
            Payments.settings.haultUI(false);

            // Show error in payment form
            Payments.settings.onError(result.error);

        } else {
            //Otherwise send paymentMethod.id to your server (see Step 4)
            fetch((Payments.settings.serverUrl + Payments.settings.transactionId), {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    payment_method_id: result.paymentMethod.id,
                })
            }).then(function (result) {
                // Handle server response (see Step 4)
                result.json().then(function (json) {
                    Payments.stripeHandleServerResponse(json);
                })
            });


            console.log("SUCCESS:" + result.paymentMethod.id);

        }
    },

    stripeHandleServerResponse: function (response) {
            if (response.error) {

                Payments.settings.onError(response.error);

                $(Payments.settings.inputs.submitBtn.text).show();
                $(Payments.settings.inputs.submitBtn.loader).hide();
                $(Payments.settings.inputs.submitBtn.text).closest("button").removeAttr("disabled");
                $(Payments.settings.inputs.submitBtn.text).closest("button").removeClass("disabled");
                Payments.settings.haultUI(false);

            } else if (response.requires_action) {
                // Use Stripe.js to handle required card action
            Payments.settings.Stripe.handleCardAction(
                    response.payment_intent_client_secret
                ).then(Payments.stripeHandleResult);
            } else {

                Payments.settings.onComplete();
                $(Payments.settings.inputs.submitBtn.text).closest("button").html("complete");
            }
        },

    stripeHandleResult: function (result) {
        if (result.error) {

            Payments.settings.onError(result.error);

            $(Payments.settings.inputs.submitBtn.text).show();
            $(Payments.settings.inputs.submitBtn.loader).hide();
            $(Payments.settings.inputs.submitBtn.text).closest("button").removeAttr("disabled");
            $(Payments.settings.inputs.submitBtn.text).closest("button").removeClass("disabled");
            Payments.settings.haultUI(false);

        } else {
            // The card action has been handled
            // The PaymentIntent can be confirmed again on the server
            fetch((Payments.settings.serverUrl + Payments.settings.transactionId), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ payment_intent_id: result.paymentIntent.id })
            }).then(function(confirmResult) {
                return confirmResult.json();
            }).then(Payments.stripeHandleServerResponse);
        }
    }
};