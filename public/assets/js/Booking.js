var Booking = {

    settings: {},

    startCinemaBooking: function(show, ticketConfig, initialHtml) {

        Booking.settings.show = show;
        Booking.settings.ticketConfig = ticketConfig;
        Booking.settings.initialHtml = initialHtml;
        Booking.settings.navWarn = true;
        Booking.preventUnload();

        stepper = new Stepper({
            'customization': {
                'loader': {
                    'label': 'Processing...'
                }
            },
            'steps': [
                {
                    'step_name': 'Select tickets',
                    'onLoad': function(data){

                        stepper.updateStepContents(data.item, Booking.settings.initialHtml);
                        TicketPicker.start({
                            "show": Booking.settings.show,
                            "ticketConfig": Booking.settings.ticketConfig
                    });

                        //Cinema.startTicketSelection();
                    },
                    'onNext': function(){

                        if(TicketPicker.selectedTicketsCount() < 1) {

                            $("#step1Error").html("Please select at least one ticket.");
                            $("#step1Error").show();
                            return false;

                        }

                        let data = TicketPicker.finish();

                        stepper.showLoader(true);
                        stepper.nextStepData = data;
                        return true;

                    },
                    'onBack': function(){}
                },
                {
                    'step_name': 'Select Seats',
                    'onNext': function(){

                            if(Booking.settings.selectedSeats < Booking.settings.required) {

                                $("#step2Error").html("Please select remaining seats.");
                                $("#step2Error").show();

                                return false;

                            } else {

                                stepper.showLoader(true);
                                stepper.nextStepData = {
                                    "seats": Booking.settings.selectedSeats
                                }

                                return true;

                            }

                    },
                    'onBack': function(){},
                    'onLoad': function(data){

                        $.ajax({
                            url: "/booking/ajax/v2/new/" + data.data.show,
                            method: "POST",
                            data: data.data,
                            success: function(result) {

                                stepper.updateStepContents(data.item, result.seating.html);
                                stepper.showLoader(false);
                                $("#step1Error").hide();

                                Booking.startSeatingSelection(result);

                            },
                            error: function(err){

                                if(err.readyState == 0) {

                                    $("#step1Error").html("Error communicating with server. Please try again later.");
                                    $("#step1Error").show();

                                } else {

                                    let error = JSON.parse(err.responseText);

                                    $("#step1Error").html(error.error_desc);
                                    $("#step1Error").show();

                                }


                                stepper.stepErrorOccurred();

                            }
                        });

                    }
                },
                {
                    'step_name': 'Provide Details',
                    'onNext': function(){

                        Booking.validateDetailsForm();

                        if(!Booking.settings.continueProcess) {

                            $("#step3Error").html("One or more fields not valid.");
                            $("#step3Error").show();
                            return false;

                        }

                        let data = {
                            "name": $("#name").val(),
                            "phone": $("#phone").val(),
                            "email": $("#email").val()
                        };

                        stepper.nextStepData = data;
                        stepper.showLoader(true);
                        return true;

                    },
                    'onBack': function(){

                        stepper.showLoader(true);

                        $.ajax({
                            url: "/booking/ajax/v2/cancel/" + Booking.settings.bookingId,
                            method: "POST",
                            success: function(response) {

                                console.log("reservation cancelled");
                                Booking.settings.bookingId = "";
                                stepper.showLoader(false);

                            },
                            error: function(err) {

                                Booking.settings.bookingId = "";
                                console.warn("Failed to cancel temporary reservation.");

                            }

                        });

                        return true;


                    },
                    'onLoad': function(data){

                        $.ajax({
                            url: "/booking/ajax/v2/seating/" + Booking.settings.show,
                            method: "POST",
                            dataType: "JSON",
                            data: data.data,
                            success: function(result) {

                                stepper.updateStepContents(data.item, result.details);
                                stepper.showLoader(false);
                                $("#step2Error").hide();

                                Booking.settings.bookingId = result.bookingId;
                                Booking.startDetailsSection();

                                console.log("Temporary reservation saved");

                            },
                            error: function(err, status) {

                                if(err.readyState == 0) {

                                    $("#step2Error").html("Error communicating with server. Please try again later.");
                                    $("#step2Error").show();

                                } else {

                                    let error = JSON.parse(err.responseText);

                                    $("#step2Error").html(error.error_desc);
                                    $("#step2Error").show();

                                }


                                stepper.stepErrorOccurred();
                            }


                        });

                    }
                },
                {
                    'step_name': 'Payment',
                    'onNext': function(){
                        if(confirm('are you sure?')) {

                            return true;
                        } else {

                            return false;

                        }
                    },
                    'onBack': function(){},
                    'onLoad': function(data){

                        $.ajax({
                            url: "/booking/ajax/v2/details/" + Booking.settings.bookingId,
                            method: "POST",
                            dataType: "JSON",
                            data: data.data,
                            success: function(response) {

                                Booking.startPayment();
                                Booking.settings.bookingConfirmation = response.confirmation;
                                stepper.updateStepContents(data.item, response.html);

                            },
                            error: function(err) {

                                if(err.readyState == 0) {

                                    $("#step3Error").html("Error communicating with server. Please try again later.");
                                    $("#step3Error").show();

                                } else {

                                    let error = JSON.parse(err.responseText);

                                    $("#step3Error").html(error.error_desc);
                                    $("#step3Error").show();

                                }


                                stepper.stepErrorOccurred();
                            }
                        });

                    }
                }
            ]
        });

        stepper.load();

    },

    startSeatingSelection: function(result) {

        var preSelectedSeats;

        if(result.seating.selected == "NONE") {

            preSelectedSeats = "NONE";

        } else {

            preSelectedSeats = result.seating.selected.seats;
        }

        // Start seat selection
        Booking.settings.selectedSeats = [];
        Booking.settings.required = result.seating.required;

        if(preSelectedSeats !== "NONE") {

            if(preSelectedSeats.length >= 1) {

                for(x = 0; x < preSelectedSeats.length; x++) {

                    Booking.settings.selectedSeats.push(preSelectedSeats[x]);

                }

            }
        }

        Booking.updateCinemaBookingCount();


        Seatpicker.start({
            ignoreSpaces: true,
            seatOnClick: function(selector, seatId) {

                if(selector.closest("td").hasClass("seat-taken")) {
                    return;

                }

                if(selector.closest("td").hasClass("seat-blocked")) {

                    alert("Due to social distancing, this seat is not available.");
                    return;
                }

                let current = selector.closest("td").hasClass("seat-selected");

                if(current) {

                    var seatCheck = selector.closest("td").attr("data-seatid");

                    Booking.settings.selectedSeats = Booking.settings.selectedSeats.filter(function(elem){

                        return elem != seatCheck;

                    });

                    Booking.updateCinemaBookingCount();

                    selector.closest("td").removeClass("seat-selected");
                    var img = selector.closest("img").attr("src");
                    var imgUrl = img.replace("RED","GREEN");
                    selector.closest("img").attr("src", imgUrl);

                } else {

                    if(Booking.settings.selectedSeats.length == Booking.settings.required) {

                        alert("Maximum number of seats selected for your ticket selection");
                        return;

                    }

                    Booking.settings.selectedSeats.push(selector.closest("td").attr("data-seatid"));

                    Booking.updateCinemaBookingCount();

                    var img = selector.closest("img").attr("src");
                    var imgUrl = img.replace("GREEN","RED");

                    selector.closest("td").addClass("seat-selected");
                    selector.closest("img").attr("src", imgUrl);

                }
            }
        });


    },

    startDetailsSection: function() {

        Booking.settings.continueProcess = false;

        $('#detailsScreen').on('click', function() {

            Booking.validateDetailsForm();
        });

        $('#detailsScreen input').on('blur', function() {

            Booking.validateDetailsForm();

        });

        $(document).on( 'keyup', function( e ) {
            if( e.which == 9 ) {
                Booking.validateDetailsForm();
            }
        } );

    },

    startPayment: function(id) {

        $.ajax({
            url: "/payments/new/" + Booking.settings.bookingId,
            method: "GET",
            success: function(response) {

                $("#paymentWindow").html(response.html);
                stepper.showLoader(false);
                Payments.start(response.public_key, response.transaction, {
                    haultUI: function(status) {

                        if(status) {

                            stepper.showControls(false);

                        } else {

                            stepper.showControls(true);

                        }

                    },
                    onComplete: function(){

                        Booking.settings.navWarn = false;
                        $("#stepperContainer").html(Booking.settings.bookingConfirmation);
                        console.log("Booking complete.");

                    }
                });

            }
        });

    },

    preventUnload: function() {

        window.onbeforeunload = function() {
            if (Booking.settings.navWarn) {
                return "You have an unfinished booking. If you navigate away from this page you will lose your booking.";
            }
        }

    },

    validateDetailsForm: function() {

        // Validation configuration
        conf = {
            onElementValidate : function(valid, $el, $form, errorMess) {
                if( !valid ) {
                    // gather up the failed validations
                    errors.push({el: $el, error: errorMess});
                } else {

                    success.push($el);

                }
            }
        }

        lang = {};

        // Manually load the modules used in this form
        $.formUtils.loadModules('security');

        function processVal(errors) {

            for(x = 0; x < errors.length; x++) {

                $(errors[x].el).addClass("is-invalid");

            }

            for(x = 0; x < success.length; x++) {

                $(success[x]).addClass("is-valid");
                $(success[x]).removeClass("is-invalid");

            }

            }

            // reset error array
        // Array used as error collection
            var errors = [];
            var success = [];
            var items = ["email", "name", "reEmail", "phone"];

            if( !$("#detailsScreen").isValid(lang, conf, false) ) {
                Booking.settings.continueProcess = false;
                processVal( errors );
            } else {
                // The form is valid
                Booking.settings.continueProcess = true;

                for(x = 0; x < items.length; x++) {

                    $(items[x]).removeClass("is-invalid");
                    $(items[x]).addClass("is-valid");

                }

            }


    },

    updateCinemaBookingCount: function() {

        let seats = Booking.settings.selectedSeats.length;
        let required = Booking.settings.required;

        $(".seats-remaining").html((required - seats));

    }



};