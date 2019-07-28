var Cinema = {

    settings: {},
    
    Init: function(settings) {
        
        $.extend(Cinema.settings, settings);
        
        Cinema.settings.navWarn = true;
        Cinema.preventUnload();
        
        Cinema.settings.tickets = {
            
            "Adult": {
                "id": 1,
                "cost": 8.00,
                "count": 0
            },
            "Concession/OAP": {
                "id": 2,
                "cost": 7.00,
                "count": 0
            },
            "Child (16 & under)": {
                "id": 3,
                "cost": 6.00,
                "count": 0
            },
            "Family (2 adults + 2 children)": {
                "id": 4,
                "cost": 14.00,
                "count": 0
            }
            
        };
        
    },
    
    preventUnload: function() {
        
        window.onbeforeunload = function() { 
          if (Cinema.settings.navWarn) {
            return "You have an unfinished booking. If you navigate away from this page you will lose your booking.";
          }
        }
        
    },
    
    startTicketSelection: function() {
        
        $(".ticket-option").on("change", function(){
            
            let ticketType = $(this).attr("data-tickettype");
            let ticket = Cinema.settings.tickets[ticketType];
            let numOfTickets = $(this).val();
            
            if(numOfTickets >= 1) {
                
                ticket.count = parseInt(numOfTickets);
                console.log(ticket);
            
            let cost = (numOfTickets * ticket.cost);
            let box = ".ticket-option-" + ticket.id;
            $(box).html("&pound;" + cost + ".00");
            
            } else {
                
                $(".ticket-option-" + ticket.id).html("");
                
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
                
                dataArray[ticket.id] =  parseInt($(this).val());
                
            });
            
            console.log(dataArray);
            
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
        
        Cinema.updateSeatSelectionCount(required);
        
        console.log(Cinema.settings.selectedSeats);
        
        $(".screen-seat img").unbind("click").on("click", function(){

            
            if($(this).closest("td").hasClass("seat-taken")) {
                
                alert("Seat taken");
                return;
            }
            
            let current = $(this).closest("td").hasClass("seat-selected");
            
            if(current) {
                
                var seatCheck = $(this).closest("td").attr("data-seatid");
                
                Cinema.settings.selectedSeats = Cinema.settings.selectedSeats.filter(function(elem){
                    
                    return elem != seatCheck; 
                    
                });
                
                Cinema.updateSeatSelectionCount(required);
                
                $(this).closest("td").removeClass("seat-selected");
                $(this).closest("img").attr("src", "/assets/images/seats/1-seat_GREEN.png");
                
            } else {
                
                if(Cinema.settings.selectedSeats.length == required) {
                    
                    alert("Maximum number of seats selected for your ticket selection");
                    return;
                                                                  
                }
                
                Cinema.settings.selectedSeats.push($(this).closest("td").attr("data-seatid"));
                
                Cinema.updateSeatSelectionCount(required);
                
              $(this).closest("td").addClass("seat-selected");
              $(this).closest("img").attr("src", "/assets/images/seats/1-seat_RED.png");  
                
            }
            
            
        });

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

                    Cinema.startDetailsSection();

                    $("#navigationBack").unbind("click").on("click", function(){

                        $("#bookingStep2").removeClass("d-none");
                        $("#bookingStep3").addClass("d-none");
                        
                        $.ajax({
                            url: "/booking/ajax/cancel/" + response.bookingId,
                            method: "POST",
                            success: function(response) {
                                
                                alert("cancellation successful");
                                
                            },
                            error: function(err) {
                                
                                alert("Cancellation failed");
                                
                            }
                            
                        });
                        
                        Cinema.startSeatSelection(Cinema.settings.selectedCount, Cinema.settings.selectedSeats);
                        
                        $("#navigationBack").unbind("click").on("click", function(){
                            
                            $("#bookingStep1").removeClass("d-none");
                            $("#bookingStep2").addClass("d-none");
                            $("#navigationBack").addClass("disabled");
                            $("#navigationBack").attr("disabled", "disabled");
                            Cinema.startTicketSelection();
                            
                        });

                    });

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
        
        var continueProcess = false;
        
        function processVal(errors) {
            
            for(x = 0; x < errors.length; x++) {
                
                console.log(errors[x].el);
                
                //console.log(errors[x].el + " - " + errors[x].error);
                
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
            
            $.ajax({
                url: "/booking/ajax/details/" + Cinema.settings.bookingId,
                method: "POST",
                dataType: "JSON",
                data: data,
                success: function(response) {
                    
                    $("#bookingStep4").removeClass("d-none");
                    $("#bookingStep3").addClass("d-none");
                    $("#bookingNavigation").removeClass("d-flex");
                    $("#bookingNavigation").addClass("d-none");
                    
                    Cinema.settings.navWarn = false;
                    
                },
                error: function(err) {
                    
                    let response = JSON.parse(err.responseText);
                    
                    alert(response.error_desc);
                    console.log(response);
                }
            });
            
        });
           
        /*$("#navigationNext").unbind("click").on("click", function(){
            
            let con = true;
            
            if($("#name").val().length <= 1) {
                
                $("#name").addClass("is-invalid");
                con = false;
                
            }
            
            if($("#phone").val().length <= 8) {
                
                $("#phone").addClass("is-invalid");
                con = false;
                
            }
            
            if($("#email").val().length <= 1) {
                
                $("#email").addClass("is-invalid");
                con = false;
                
            }
            
            if($("#reEmail").val().length < 1 || $("#reEmail").val() !== $("#email").val()) {
                
                $("#reEmail").addClass("is-invalid");
                con = false;
                
            }
            
            if(con === false) {
                
                alert("errors in form");
                return;
                
            }
            
        }); */
        
        
        
    }






};