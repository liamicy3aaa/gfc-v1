var Cinema = {

    settings: {},
    
    Init: function(settings) {
        
        $.extend(Cinema.settings, settings);
        
        Cinema.settings.navWarn = true;
        Cinema.preventUnload();
    },
    
    preventUnload: function() {
        
        window.onbeforeunload = function() { 
          if (Cinema.settings.navWarn) {
            return "You have an unfinished booking. If you navigate away from this page you will lose your booking.";
          }
        }
        
    },

    getLoader: function() {

        return "<div class=\"text-center\">\n" +
            "  <div class=\"spinner-border\" role=\"status\">\n" +
            "    <span class=\"sr-only\">Loading...</span>\n" +
            "  </div>\n" +
            "</div>";

    },

    resetPassword: function() {

        showModal("Reset password", Cinema.getLoader(),{"size":"lg", "vcenter":true, "bodyColor":"#f8f8f8"});

        $.ajax({
            url: "/auth/ajax/reset-password",
            method: "GET",
            success: function(response){

                updateModal(response.html);

                $("#reset-form").on("submit", function(e){
                    e.preventDefault();

                    if (this.checkValidity() === false) {
                        $("#reset-form").addClass('was-validated');
                        return;
                    }

                    $("#resetSubmit").addClass("disabled").attr("disabled", "true");
                    $("#resetSubmit").html(Cinema.getLoader());

                    var data = $("#reset-form").serialize();

                    $.ajax({
                        url: "/auth/ajax/reset-password",
                        method: "POST",
                        data: data,
                        success: function(response) {

                            updateModal("<h2>Check your email</h2><hr/><p>We have sent you a reset password link to your email.</p>");

                        },
                        error: function(err, status) {

                            if(err.status !== 500) {

                                var response = JSON.parse(err.responseText);
                                alert(response.error_desc);
                                console.log(response);

                            } else {
                                closeModal();
                                alert("An error occurred. Please try again later.");

                            }

                            $("#resetSubmit").removeClass("disabled").removeAttr("disabled");
                            $("#resetSubmit").html("Send Link");

                        }
                    });

                });

            }
        });

    },

    startValidatePasswordHandler: function(requirements){

        var settings = Object.keys(requirements);
        var valid = {};
        var number = ["minlen", "maxlen", "number", "lowercase"];

        settings.forEach(function(item, index){
            valid[item] = false;
            $("#pwdCheck" + item).removeClass("d-none");
        });

        $("#resetPwd, #resetPwd2").on("keyup", function() {

            var inputValue = $("#resetPwd").val();

            for(var i = 0; i < settings.length; i++) {

                switch (settings[i]) {
                    case "minlen":
                        if (inputValue.length > requirements[settings[i]]) {
                            valid[settings[i]] = true;
                        } else {
                            valid[settings[i]] = false;
                        }
                        break;

                    case "maxlen":
                        if (inputValue.length > requirements[settings[i]] || inputValue.length < 1) {
                            valid[settings[i]] = false;
                        } else {
                            valid[settings[i]] = true;
                        }
                        break;

                    case "capitalchar":
                        var upperCaseLetters = /[A-Z]/g;
                        if (inputValue.match(upperCaseLetters)) {
                            valid[settings[i]] = true;
                        } else {
                            valid[settings[i]] = false;
                        }
                        break;

                    case "specialchar":
                        var specialLetters = /\W|_/g;
                        if (inputValue.match(specialLetters)) {
                            valid[settings[i]] = true;
                        } else {
                            valid[settings[i]] = false;
                        }
                        break;

                    case "lowerchar":
                        var lowerCaseLetters = /[a-z]/g;
                        if (inputValue.match(lowerCaseLetters)) {
                            valid[settings[i]] = true;
                        } else {
                            valid[settings[i]] = false;
                        }
                        break;

                    case "number":
                        var numbers = /[0-9]/g;
                        if (inputValue.match(numbers)) {
                            valid[settings[i]] = true;
                        } else {
                            valid[settings[i]] = false;
                        }
                        break;

                    default:
                        console.warn("Issue validating input - " + settings[i]);
                        break;
                }

            }

            var validLength = Object.keys(valid).length;
            validCount = 0;

            for(var i = 0; i < Object.keys(valid).length; i++) {

                let item = valid[settings[i]];

                if(item) {
                    validCount++;
                    $("#pwdCheck" + settings[i]).removeClass("text-danger").addClass("text-success");
                } else {
                    $("#pwdCheck" + settings[i]).removeClass("text-success").addClass("text-danger");
                }

            };

            let passwordsMatch = (($("#resetPwd").val().length > 1 && $("#resetPwd").val() == $("#resetPwd2").val()) ? true : false);

            if(!passwordsMatch) {
                $("#pwdCheckMatch").removeClass("text-success").addClass("text-danger");
            } else {
                $("#pwdCheckMatch").removeClass("text-danger").addClass("text-success");
            }

            if(validCount >= validLength && passwordsMatch) {
                $("#resetSubmit").removeClass("disabled").removeAttr("disabled");
            } else {
                $("#resetSubmit").addClass("disabled").attr("disabled", "disabled");
            }

        });

        $("#resetSubmit").on("click", function(){

            $("#resetSubmit").addClass("disabled").attr("disabled", "true");
            $("#resetSubmit").html(Cinema.getLoader());

            var data = $("#reset-form").serialize();

            $.ajax({
                url: "/auth/reset-password",
                method: "POST",
                data: data,
                success: function(response) {

                    window.location.replace("/");
                    console.log(response);

                },
                error: function(err, status) {

                    if(err.status !== 500) {

                        var response = JSON.parse(err.responseText);
                        alert(response.error_desc);
                        console.log(response);

                    } else {
                        console.log(err);
                        alert("An error occurred. Please try again later.");

                    }

                    $("#resetSubmit").removeClass("disabled").removeAttr("disabled");
                    $("#resetSubmit").html("Update Password");

                }
            });

        });


    },
    
    startTicketSelection: function() {
        
        $("#selectedTicketsTotal").closest("strong").hide();

        $(".ticket-option").on("change", function(){
            
            let ticketType = $(this).attr("data-tickettype");
            let ticket = Cinema.settings.tickets[ticketType];
            let numOfTickets = $(this).val();
            
            if(numOfTickets >= 1) {
                
                ticket.count = parseInt(numOfTickets);
            
            let cost = (numOfTickets * ticket.cost);
            let box = ".ticket-option-" + ticketType;

            $(box).html("&pound;" + cost + ".00");
            
            } else {
                
                $(".ticket-option-" + ticketType).html("");
                
            }
                Cinema.settings.selectedCount = 0;
                Cinema.settings.selectedCost = 0;
            $(".ticket-option").each(function(){
                
                let ticketType = $(this).attr("data-tickettype");
                let ticket = Cinema.settings.tickets[ticketType];
                let numOfTickets = $(this).val();
            
                let cost = (numOfTickets * ticket.cost);
                Cinema.settings.selectedCost = (cost + Cinema.settings.selectedCost);
                Cinema.settings.selectedCount = (Number(numOfTickets) + (Cinema.settings.selectedCount));
                
            });
            
            if(Cinema.settings.selectedCount >= 1) {
                
               $("#selectedTicketsCost").html(Cinema.settings.selectedCost + ".00");
               $("#selectedTicketsTotal").html(Cinema.settings.selectedCount); 
               $("#selectedTicketsTotal").closest("strong").show();   
            } else {
                
                $("#selectedTicketsTotal").closest("strong").hide();
                
            }
            
                
            
        });
        
        $("#navigationNext").unbind("click").on("click", function(){
            
            $(this).attr("disabled", "disabled");
            $(this).addClass("disabled");
            
            var dataArray = {};
            
            $(".ticket-option").each(function(){
                
                let ticketType = $(this).attr("data-tickettype");
                let ticket = Cinema.settings.tickets[ticketType];
                
                dataArray[ticketType] =  parseInt($(this).val());
                
            });

            let data = {
                "show": Cinema.settings.show,
                "tickets": dataArray
            };
            
            $.ajax({
                url: "/booking/ajax/new/" + data.show,
                method: "POST",
                dataType: "JSON",
                data: data,
                success: function(response) {
                    
                    $(".screen tbody").html(response.seating.html);
                    $("#bookingStep1").addClass("d-none");
                    $("#bookingStep2").removeClass("d-none");
                    
                    if(response.seating.selected == "NONE") {
                        
                        $preSelected = "NONE";
                        
                    } else {
                        
                        $preSelected = response.seating.selected.seats;
                    }
                    
                    Cinema.settings.requiredSelection = response.seating.required;

                    Cinema.startSeatSelection(response.seating.required, $preSelected);
                    
                    $("#navigationBack").removeAttr("disabled");
                    $("#navigationBack").removeClass("disabled");
                    
                    $("#navigationBack").unbind("click").on("click", function(){
                        
                        $("#bookingStep1").removeClass("d-none");
                        $("#bookingStep2").addClass("d-none");
                        $(".screen tbody").html("");
                        $(this).attr("disabled", "disabled");
                        $(this).addClass("disabled");
                        Cinema.startTicketSelection(); 
                        
                    });
                    
                    $("#navigationNext").removeAttr("disabled");
                    $("#navigationNext").removeClass("disabled");
                    
                },
                error: function(err, status) {
                    
                    console.log(err.responseText);
                    
                    let response = JSON.parse(err.responseText);
                    
                    switch(response.error) {
                        
                        case "invalidTicketTotal":
                            alert("At least one ticket must be selected");
                            break;

                        case "notEnoughSeats":
                            alert(response.error_desc + ". Please choose a smaller amount of seats.");
                            break;

                        default:
                            alert("Unknown error occurred");
                            break;    
                        
                    }
                    
                    $("#navigationNext").removeAttr("disabled");
                    $("#navigationNext").removeClass("disabled");
                    
                }
                
            });
            
        });
        
        
    },
    
    startNavigationHandler() {
        
        let steps = ["tickets", "seating", "details", "review"];
        let buttonStatus = {
            "tickets": [0, 1],
            "seating": [1, 1],
            "details": [1, 1],
            "review": [1, 0]
        };
        
        Cinema.settings.currentStep = steps[0];
        
        $("#navigationNext").on("click", function() {
            
            if($(this).hasClass("disabled")) {
                
                return;
                
            }
            
            let currentStepIndex = steps.findIndex(Cinema.settings.currentStep);
            
            if(Cinema.settings.currentStep == "review") {
                
                alert("end of steps");
                
            } else {
                
            let nextStep = currentStepIndex + 1;
            let nextStepText = steps[nextStep];
            
            Cinema.goToStep(nextStepText);
            
            if(buttonStatus[nextStepText][0] === 1) {
                
                $("#navigationBack").removeClass("disabled");
                $("#navigationBack").removeAttr("disabled");
                
            } else {
                
                $("#navigationBack").addClass("disabled");
                $("#navigationBack").attr("disabled", "disabled");
                
            }
            
            if(buttonStatus[nextStepText][1] === 1) {
                
                $("#navigationNext").removeClass("disabled");
                $("#navigationNext").removeAttr("disabled");
                
            } else {
                
                $("#navigationNext").addClass("disabled");
                $("#navigationNext").attr("disabled", "disabled");
                
            } 
            
        }
        });
        
        
    },
    
    goToStep: function(step) {
        
             switch(step) {
                
                case "tickets":
                    Cinema.step_tickets();
                    break;
                    
                case "seating":
                    Cinema.step_seating();
                    break;
                    
                case "details":
                    Cinema.step_details();
                    break;
                    
                case "review":
                    Cinema.step_review();
                    break;
                    
                default: 
                    console.error("Invalid step provided");
                    break;                
                
            }
        
        
    },
    
    step_tickets: function(prev) {
        
        $(prev).addClass("d-none");
        $("#bookingStep1").removeClass("d-none");
        
        
    },
    
    step_seating: function(prev) {
        
        $(prev).addClass("d-none");
        $("#bookingStep2").removeClass("d-none");
        
        $.ajax({
            url: "/api/booking/seating/" + Cinema.settings.show,
            method: "GET",
            success: function(response) {
                
                $("#bookingStep2").html(response.html);
                
            },
            fail: function(err, response) {
                
                $("#bookingStep2").html(response.responseText);
                
            }
            
            
        });
        
        
    },
    
    updateSeatSelectionCount: function(required) {
     
        let seats = Cinema.settings.selectedSeats.length;
        
        $(".seats-remaining").html((required - seats));
     
     
         if((required - seats) < 1) {
         
             $("#seatSelectionAlert").html("All yours seats have been selected.");
             
         } else {
             
              $("#seatSelectionAlert").html("Please select " + (required - seats) + " more seat/s");
             
         }
   
        $("#seatSelectionAlert").removeClass("d-none");
    },
    
    startSeatSelection(required, preSelectedSeats) {
        
        Cinema.settings.selectedSeats = [];
        
        if(preSelectedSeats !== "NONE") {
        if(preSelectedSeats.length >= 1) {
            
            for(x = 0; x < preSelectedSeats.length; x++) {
            
            Cinema.settings.selectedSeats.push(preSelectedSeats[x]);
            
            }
            
        }
        }

        console.log(Cinema.settings.selectedSeats);
        
        Cinema.updateSeatSelectionCount(required);

        //$(".screen-seat img").unbind("click").on("click", function(){

            Seatpicker.start({
                ignoreSpaces: true,
                seatOnClick: function(selector, seatId) {
                
                    if(selector.closest("td").hasClass("seat-taken")) {
                    return;
                    
                    }
                
                    let current = selector.closest("td").hasClass("seat-selected");
                
                    if(current) {
                    
                        var seatCheck = selector.closest("td").attr("data-seatid");
                    
                        Cinema.settings.selectedSeats = Cinema.settings.selectedSeats.filter(function(elem){
                        
                        return elem != seatCheck; 
                        
                        });
                    
                        Cinema.updateSeatSelectionCount(required);
                    
                        selector.closest("td").removeClass("seat-selected");
                        var img = selector.closest("img").attr("src");
                        var imgUrl = img.replace("RED","GREEN");
                        selector.closest("img").attr("src", imgUrl);
                    
                    } else {
                    
                        if(Cinema.settings.selectedSeats.length == required) {
                        
                            alert("Maximum number of seats selected for your ticket selection");
                            return;
                                                                      
                        }
                    
                        Cinema.settings.selectedSeats.push(selector.closest("td").attr("data-seatid"));
                    
                        Cinema.updateSeatSelectionCount(required);
                    
                        var img = selector.closest("img").attr("src");
                        var imgUrl = img.replace("GREEN","RED");
                    
                    selector.closest("td").addClass("seat-selected");
                    selector.closest("img").attr("src", imgUrl);  
                    
                    }
                }
            });
            
            
            
            
       // });

        $("#navigationNext").unbind("click").on("click", function() {

            if(Cinema.settings.selectedSeats.length !== required) {

                alert("Please select your remaining seats");
                return;

            }

            let data = {
                "seats": Cinema.settings.selectedSeats
            };

            $.ajax({
                url: "/booking/ajax/seating/" + Cinema.settings.show,
                method: "POST",
                dataType: "JSON",
                data: data,
                success: function(response) {

                    $(".booking-details-screen").html(response.details);
                    Cinema.settings.bookingId = response.bookingId;
                    $("#bookingStep2").addClass("d-none");
                    $("#bookingStep3").removeClass("d-none");
                    $("#navigationNext span").html("Confirm Booking");
                    $("#navigationNext i").removeClass("fa-arrow-alt-circle-right");
                    $("#navigationNext i").addClass("fa-calendar-check");

                    console.log("Temporary reservation saved");

                    Cinema.startDetailsSection();

                },
                error: function(err, status) {

                    let response = JSON.parse(err.responseText);

                    alert("ERROR: " + response.error_desc);
                    console.log(response);
                }


            });


        });
        
    },
    
    startDetailsSection: function() {

        $("#navigationBack").unbind("click").on("click", function(){

            $("#bookingStep2").removeClass("d-none");
            $("#bookingStep3").addClass("d-none");
            $("#navigationNext span").html("Next");
            $("#navigationNext i").removeClass("fa-calendar-check");
            $("#navigationNext i").addClass("fa-arrow-alt-circle-right");


            $.ajax({
                url: "/booking/ajax/cancel/" + Cinema.settings.bookingId,
                method: "POST",
                success: function(response) {

                    console.log("reservation cancelled");
                    Cinema.settings.bookingId = "";

                },
                error: function(err) {

                    alert("Cancellation failed");

                }

            });

            Cinema.startSeatSelection(Cinema.settings.requiredSelection, Cinema.settings.selectedSeats);

            $("#navigationBack").unbind("click").on("click", function(){

                $("#bookingStep1").removeClass("d-none");
                $("#bookingStep2").addClass("d-none");
                $("#navigationBack").addClass("disabled");
                $("#navigationBack").attr("disabled", "disabled");
                Cinema.startTicketSelection();

            });

        });
        
        var continueProcess = false;
        
        function processVal(errors) {
            
            for(x = 0; x < errors.length; x++) {

                $(errors[x].el).addClass("is-invalid");
                
            }
            
            for(x = 0; x < success.length; x++) {
                
                 $(success[x]).addClass("is-valid");
                 $(success[x]).removeClass("is-invalid");
                
            }
            
        }
        
        // Array used as error collection
        var errors = [];
        var success = [];
        var items = ["email", "name", "reEmail", "phone"];
        
        function validateForm() {
            
             // reset error array
           errors = [];
           success = [];
           if( !$("#detailsScreen").isValid(lang, conf, false) ) {
               continueProcess = false;
               processVal( errors );
           } else {
           // The form is valid  
           continueProcess = true;
            
            for(x = 0; x < items.length; x++) {
                
                $(items[x]).removeClass("is-invalid");
                $(items[x]).addClass("is-valid");
                
            }
           
           }
            
        }
        
        

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

        $("#navigationNext").unbind("click");
        
        $('#detailsScreen').on('click', function() {
          
            validateForm();
            
        });
        
        $('#detailsScreen input').on('blur', function() {
          
            validateForm();
            
        });
        
        $(document).on( 'keyup', function( e ) {
            if( e.which == 9 ) {
                validateForm();
            }
        } );
        
        $("#navigationNext").unbind("click").on("click", function(){
            
            validateForm();
            
            if(!continueProcess) {
                
                alert("One or more fields not valid.");
                return;
                
            } 
            
            data = {
                "name": $("#name").val(),
                "phone": $("#phone").val(),
                "email": $("#email").val()
            };
            
            console.log(data);

            $(".Bkloader").show();
            $("#bookingStep3").addClass("d-none");
            
            $.ajax({
                url: "/booking/ajax/details/" + Cinema.settings.bookingId,
                method: "POST",
                dataType: "JSON",
                data: data,
                success: function(response) {

                    Cinema.startPayment();
                    $("#bookingStep5 .card").html(response.confirmation);
                    $("#navigationNext").attr("disabled", "disabled");
                    $("#navigationNext").addClass("disabled");
                    
                    //Cinema.settings.navWarn = false;
                    
                },
                error: function(err) {
                    
                    let response = JSON.parse(err.responseText);

                    $("#bookingStep3").removeClass("d-none");
                    $(".Bkloader").hide();
                    $("#bookingNavigation").removeClass("d-none");
                    $("#bookingNavigation").addClass("d-flex");
                    
                    alert(response.error_desc);
                    console.log(response);
                }
            });
            
        });

    },
    startPayment: function() {

        $("#navigationBack").unbind("click").on("click", function(){

            $("#bookingStep4").addClass("d-none");
            $("#bookingStep3").removeClass("d-none");
            $("#navigationNext").removeAttr("disabled");
            $("#navigationNext").removeClass("disabled");
            Cinema.startDetailsSection();

        });

        $.ajax({
            url: "/payments/new/" + Cinema.settings.bookingId,
            method: "GET",
            success: function(response) {

                $("#bookingStep4 .card").html(response.html);
                $(".Bkloader").hide();
                $("#bookingStep4").removeClass("d-none");
                Payments.start(response.public_key, response.transaction, {
                    onComplete: function(){

                        $("#bookingStep4").addClass("d-none");
                        $("#bookingStep5").removeClass("d-none");
                        $("#bookingNavigation").removeClass("d-flex");
                        $("#bookingNavigation").addClass("d-none");
                        Cinema.settings.navWarn = false;

                    }
                });

            }
        });

    },
    openTrailer: function(url) {
        
        var code = '<div class="embed-responsive embed-responsive-16by9">';
            code += '<iframe src="' + url + '?autoplay=1" frameborder="0" allow="autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
        code += '</div>';
        
        showModal("Trailer", code, {"size":"lg", "vcenter":true});
        
        
    },

    startCountdown: function (id, targetDate, current) {

        var now = current;
        Cinema._countdownProcess(now, targetDate);
        // Update the count down every 1 second
        var x = setInterval(function () {

            now += 1000;
            let distance = Cinema._countdownProcess(now, targetDate);

            if(distance < 1) {
                // Simulate an HTTP redirect:
                clearInterval(x);
                location.reload();

            }
        }, 1000);
    },

    _countdownProcess: function(current, target) {

        // Set the date we're counting down to
        var countDownDate = target;

        // Find the distance between now and the count down date
        var distance = countDownDate - current;

        // Time calculations for days, hours, minutes and seconds
        var days = Math.floor(distance / (1000 * 60 * 60 * 24));
        var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        var seconds = Math.floor((distance % (1000 * 60)) / 1000);

        // Display the result

        if(days <= 1) {
            $("#cDlabel").html("Day");
        }

        $("#cD").html(days);
        $("#cH").html(hours);
        $("#cM").html(minutes);
        $("#cS").html(seconds);

        return distance;
    }







};