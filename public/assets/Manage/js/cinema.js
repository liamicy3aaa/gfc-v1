var Cinema = {

    newScreen: function() {
        
        $.ajax({
            url: "/Manage/ajax/screens/new",
            method: "GET",
            success: function(response){
                
             showModal("New screen", response.html, {"size":"lg", "vcenter":true});
             
             $("#newScreenSubmit").on("click", function(){
                 
                var data = $(this).closest("form").serialize();
                
                $.ajax({
                    url: "/Manage/ajax/screens/new",
                    method: "POST",
                    data: data,
                    success: function(response) {
                        
                        location.reload();
                        
                    },
                    error: function(err, status) {
                        
                        var response = JSON.parse(err.responseText);
                        
                        alert(response.error_desc);
                        console.log(response);
                        
                    }   
                });
                 
             });   
                
            }   
        });
    
    },
    
    newFilm: function() {
        
        $.ajax({
            url: "/Manage/ajax/films/new",
            method: "GET",
            success: function(response){
                
             showModal("Add film<br/><small>Please enter the basic details for the film</small>", response.html, {"size":"lg", "vcenter":true});
             
             $("#newFilmSubmit").on("click", function(){
                 
                var data = $(this).closest("form").serialize();
                
                $.ajax({
                    url: "/Manage/ajax/films/new",
                    method: "POST",
                    data: data,
                    success: function(response) {
                        
                        window.location.href = response.redirect;
                    },
                    error: function(err, status) {
                        
                        var response = JSON.parse(err.responseText);
                        
                        alert(response.error_desc);
                        console.log(response);
                        
                    }   
                });
                 
             });   
                
            }   
        });
    
    },

    deleteFilm: function(id) {

        showModal("Are you sure you want to delete this film?", "<button class='btn btn-block btn-danger deleteBtn'>Confirm Deletion</button>");

        $(".deleteBtn").on("click", function(){

            $.ajax({
                url: "/Manage/ajax/films/delete/" + id,
                method: "GET",
                success: function(response) {

                    closeModal();
                    console.log(response);
                    location.reload();

                },
                error: function(err, res) {

                    let r = JSON.parse(res);

                    alert("ERROR: " + r.error);
                    closeModal();
                }

            });

        });

    },
    
    startScreenManager: function(config) {

        if(!config.screenId) {

            throw new Error("startScreenManager requires a screen id.");

        }
        
        Seatpicker.start({
            ignoreSpaces: false,
            seatOnClick: function(selector, seatId) {
                
                $.ajax({
                    url: "/Manage/ajax/screens/seats/" + seatId,
                    method: "GET",
                    success: function(response) {
                        
                        showModal("Seat Config", response.html, {"size":"sm", "vcenter":true});    
                        
                    },
                    error: function(err, response) {
                        
                        let r = JSON.parse(response.responseText);
                        
                        alert(r);
                        
                    }   
                });
            }
        });

        if($(".screen-row").length <= 2) {

            var html = "<tr class='screen-row firstRowItem'>";
            html += "<td><button class='btn btn-block btn-outline-info' onclick='Cinema.addScreenRows(\"" + config.screenId + "\");'>ADD FIRST ROW</button></td></tr>";

            $(".screen tbody").prepend(html);

        }
        
        //sortable
        
        $(".start-sortable").on('click', function() {
            
            Cinema.startScreenSorting(config.screenId);                
            
        });
        
        //$(".screen").sortable("disable");
        
        
    },
    
    startScreenSorting: function(screenId) {
      
      $(".screen-manage a").hide();
            $(".screen-row:not(.not-sortable) .screen-manage").prepend("<span class='text-secondary sortable-handle'><i style='max-width:30px;' class='fas fa-arrows-alt'></i></span>");
            $(".screen").sortable({
            items: ".screen-row:not(.not-sortable)",
            placeholder: "bg-secondary",
            handle: ".sortable-handle"
            });
        
            $(".screen-container").prepend("<h2 class='text-center'>Reorder screen</h3><p class='small text-center'>Use the icons on the left handside to reorder the rows. Once you are happy, press the save button in the top right of the box.");
            $(".card-header").prepend('<button class="d-inline float-right btn btn-success btn-sm reorderSave"><span class="text">Save</span></button>' +
            '<button onclick="if(confirm(\'Are you sure?\')){location.reload();}" class=" mr-1 d-inline float-right btn btn-danger btn-sm"><span class="text">cancel</span></button>');    
  
  
      $(".reorderSave").on("click", function(){
          
          var rowOrder = $(".screen").sortable("toArray", {attribute:"data-rowid"});
          
          $.ajax({
              url: "/Manage/ajax/screens/" + screenId + "/reorder",
              method: "POST",
              data: {newOrder: rowOrder},
              success: function(result) {
                  
                  alert("SUC: CHECK CONSOLE.");
                  
              },
              error: function(err) {
                  let result = JSON.parse(err.responseText);
                  console.log(result);
                  alert("ERR: CHECK CONSOLE");
              }
          });
          
          
      });
    },

    addScreenRows: function(screenId) {

        $.ajax({
            url: "/Manage/ajax/screens/addRows",
            method: "GET",
            success: function (result) {

                showModal("Add rows", result.html, {"size": "lg", "vcenter": true});

                $("#addRowsSubmit").on("click", function () {

                    if ($("#rows").val().length < 1 || $("#seatsPerRow").val().length < 1) {

                        alert("Please ensure all fields are populated");
                        return;

                    }

                    if ($("#rows").val() > 26) {

                        alert("Number of rows must be less than 26.");

                    }

                    if ($("#seatsPerRow").val() > 30) {

                        alert("Number of seats per row must be less than 30");

                    }

                    $.ajax({
                        url: "/Manage/ajax/screens/" + screenId + "/addRows/",
                        method: "POST",
                        data: {
                            rows: $("#rows").val(),
                            seats: $("#seatsPerRow").val(),
                            seatLabel: $("#seatLabel").val()
                        },
                        success: function (result) {

                            if (result.status == 200) {

                                closeModal();
                                location.reload();

                            }

                        },
                        error: function (error) {  

                            if(error.status == 404) {

                                alert("An error occurred contacting the server.");
                                return;

                            }

                            var result = JSON.parse(error.responseText);

                            switch (result.error) {

                                case "invalid_parameter":
                                    var errorMessage = "Invalid parameter provided";
                                    break;

                                case "params":
                                    console.log(result.data);
                                    var errorMessage = "Params have been outputted to the console.";
                                    break;

                                default:
                                    var errorMessage = "An error has occurred. Please try again later.";
                            }

                            alert(errorMessage);

                        }
                    });


                });

            },
            error: function(result) {
            
                let response = JSON.parse(result.responseText);
                
                if(result.status == 404) {

                    alert("An error occurred contacting the server.");
                    return;

                }

                switch (response.error) {

                    case "invalid_screenId":
                        var errorMessage = response.error_desc;
                        break;

                    case "params":
                        console.log(result.data);
                        var errorMessage = "Params have been outputted to the console.";
                        break;

                        default:
                        var errorMessage = "An error has occurred. Please try again later.";
                }

                            alert("An error has occurred. Please try again later.");
                            console.error(errorMessage);
                    
                
            }
            
        });


    },

    startShowBookingViewer: function(config) {

        Seatpicker.start({

            seatOnClick: function(selector, seatId) {

                var show = selector.closest("td").attr("data-showid");
                //alert("Show: " + show + " | Seat:" + seatId);

                if(selector.closest("td").hasClass("seat-blocked")) {

                    alert("Due to social distancing, this seat is not available for bookings.");
                    return;
                }

                $.ajax({
                    url: "/Manage/ajax/films/" + config.filmId + "/showtimes/" + show + "/" + seatId + "",
                    method: "GET",
                    success: function(response) {

                        if(confirm("Would you like to view this booking?")) {

                            window.location.href = response.booking;

                        }

                    },
                    error: function(err, response) {

                        if(err.status == 400) {

                            let r = JSON.parse(err.responseText);

                            if (r.error == "no_booking") {

                                if(confirm("This seat is currently available. Do you want to create a new booking?")) {

                                    window.location.href = "/Manage/bookings";

                                }

                            } else {

                                alert("An error occurred. Please try again later.");

                            }

                        } else {

                            alert("Unknown error occurred");

                        }

                    }
                });



            }

        });


    },

    getShowPlan: function(film, show) {

        $.ajax({
            url: "/Manage/ajax/films/" + film + "/showtimes/" + show + "/plan",
            method: "GET",
            success: function(response) {

                showModal("Seating Plan", response.html, {"size":"lg", "vcenter":true});

            },
            error: function(err) {

                var response = JSON.parse(err.responseText);

                alert(response);

            }
        });


    },
    
    cancelBooking: function(id, confirmation) {

        var total;

        $.ajax({
            url: "/Manage/ajax/bookings/cancel/" + id,
            method: "GET",
            success: function(response) {

                total = response.total;

                showModal("Confirm cancellation", response.html);

                $("#refundType").on("change", function(){

                    if($(this).val() == "partial") {

                        $("#refundAmountContainer").show();

                    } else {

                        $("#refundAmountContainer").hide();

                    }

                });

                $(".cancelBtn").on("click", function(){

                    if ($("#refundAmount").val() > total) {
                        alert("Refund amount must be less than £" + total);
                        return;
                    }

                    let data = {
                        "refundType": $("#refundType").val(),
                        "refundAmount": parseFloat($("#refundAmount").val())
                    };

                    updateModal("<div class='Bkloader text-center mb-5 mt-5'>\n" +
                        "    <div class=\"spinner-grow text-secondary\" style=\"width: 4rem; height: 4rem;\" role=\"status\">\n" +
                        "        <span class=\"sr-only\">Loading...</span>\n" +
                        "    </div>\n" +
                        "    <h5 class=\"mt-5\">Processing Cancellation...</h5>\n" +
                        "</div>");

                    $.ajax({
                        url: "/Manage/ajax/bookings/cancel/" + id,
                        method: "POST",
                        data: data,
                        success: function(res) {

                            closeModal();
                            console.log(res);
                            location.reload();

                        },
                        error: function(err, res) {

                            alert("An error occurred while trying to cancel this booking. Please try again later.");
                            closeModal();
                        }
                    });

                });


            },
            error: function(err) {

                alert("An error occurred. Please try again later.");

                console.log(JSON.parse(err.responseText));

            }
        });
        
    },

    alterThumbBanner: function(id, cmd) {
        
        switch(cmd) {
            
            case "thumbnail":
                var size = "lg";
                break;
                
            case "banner":
                var size = "xl";    
            
        }

        $.ajax({

            url: ("/Manage/ajax/films/" + id + "/upload/" + cmd),
            method: "GET",
            success: function(response) {

                showModal("Alter " + cmd, response.html, {"size":size, "vcenter":true});

            },
            error: function(err, res) {

                r = JSON.parse(res);

                console.log(r);
                alert("ERROR OCCURRED");

            }

        });

    },
    
    updateFilmStatus: function(filmId, switchId) {
        
        var status;
        
        if($(switchId).is(":checked")) {
            
          status = "1";  
            
        } else {
            
            status = "0";
            
        }
        
        $.ajax({
            url:"/Manage/ajax/films/" + filmId + "/filmStatus",
            method: "POST",
            data: {"status": status},
            success: function(response) {
                
                console.log(response.html);
                
            },
            error: function(err, error) {
                
                var response = JSON.parse(err.responseText);
                
                alert(response.error);
                console.error(response);
            }
        });
        
    },
    
    addShowing: function(film) {
        
        $.ajax({
            url: "/Manage/ajax/films/" + film + "/addShowing",
            method: "GET",
            success: function(response) {
                
                showModal("Add Showing", response.html, {"size":"lg", "vcenter":true});
                
            },
            error: function(err, error){
                var response = JSON.Parse(err.responseText);
                
                alert(response);
                console.log(response);
            }
            
        });
        
    },

    submitAddShowing: function() {

        let data = $("#newShowing").serializeArray();

        console.log(data);


        $.ajax({
            url: $("#newShowing").attr("action"),
            method: "POST",
            data: data,
            success: function(response) {

                closeModal();
                location.reload();

            },
            error: function(err, error) {

                let response = JSON.parse(err.responseText);

                alert(response.error_desc);

            }
        });

    },

    resendConfirmationEmail: function(request, id) {

        switch(request) {

            case "get":

                $.ajax({
                    url: "/Manage/ajax/bookings/" + id + "/resendConfirmation",
                    method: "GET",
                    success: function(response) {

                        showModal("Resend confirmation email", response.html, {"size":"lg", "vcenter":true});

                    },
                    error: function(err, error){
                        var response = JSON.Parse(err.responseText);

                        alert(response);
                        console.log(response);
                    }

                });

                break;

            case "submit":

                if(!confirm("Are you sure this email is correct?")) {

                    return;

                }

                $("#resendConfirmation").hide();
                $(".Bkloader").show();


                $.ajax({
                    url: "/Manage/ajax/bookings/" + id + "/resendConfirmation",
                    method: "POST",
                    data: {"email": $("#inputEmail").val()},
                    success: function(response) {

                        closeModal();
                        location.reload();

                    },
                    error: function(err, error) {

                        let response = JSON.parse(err.responseText);

                        $("#resendConfirmation").show();
                        $(".Bkloader").hide();

                        alert(response.error_desc);

                    }

                });


                break;

            default:
                console.error("Invalid request provided for resendConfirmationEmail()");
                alert("An error occurred. Please try again later");
                break;
        }

    },

    startTicketPOS: function() {

        var allowValidation = false;
        var activeClass = "";

        $("#bookingSearch").on("click", function(){

            if($("#bookingInput").val().length < 1) {

                alert("Please provide a booking reference.");
                return;

            }

            $("#bookingSearch .searchIcon").hide();
            $("#bookingSearch").addClass("disabled");
            $("#bookingSearch").attr("disabled", "disabled");
            $("#bookingSearch .searchSpinner").show();

            resetBookingScreen();

            let ref = $("#bookingInput").val();
            console.log(ref);

            $.ajax({
                url: "/Manage/ajax/tools/ticketPOS/search",
                method: "POST",
                data: {booking_ref: ref},
                success: function(res) {

                    processResponse(res);

                },
                error: function(err) {

                    processResponse(JSON.parse(err.responseText));

                }
            });

        });

        function processResponse(res) {

            $("#bookingSearch .searchIcon").show();
            $("#bookingSearch").removeClass("disabled");
            $("#bookingSearch").removeAttr("disabled", "disabled");
            $("#bookingSearch .searchSpinner").hide();

            $("#bookingHeader").addClass("text-white");

            if(res.status == 200) {

                switch(res.code) {

                    case "valid_booking":

                        $("#bookingInput").attr("disabled", "disabled");
                        $("#bookingInput").addClass("disabled");
                        $("#bookingSearch").addClass("disabled");
                        $("#bookingSearch").attr("disabled", "disabled");
                        $("#bookingHeader").addClass("bg-success");
                        activeClass = "bg-success";
                        $("#bookingHeader span").html("VALID <i class='fas fa-check-circle'></i>");
                        $("#bookingContent").html(res.html);
                        allowValidation = true;
                        $("#bookingOptions").show();
                        break;

                    case "valid_but_used":
                        $("#bookingInput").attr("disabled", "disabled");
                        $("#bookingInput").addClass("disabled");
                        $("#bookingSearch").addClass("disabled");
                        $("#bookingSearch").attr("disabled", "disabled");
                        $("#bookingHeader").addClass("bg-info");
                        activeClass = "bg-info";
                        $("#bookingHeader span").html("VALID <i class='fas fa-info-circle'></i> - <span style='font-size:20px!important;'>[TICKETS ALREADY ISSUED]</span>");
                        $("#bookingContent").html(res.html);
                        $("#bookingContent").prepend("<p style='font-size:20px;'>Please note, this booking is valid but has already been used and should be checked carefully.</p><hr/>");
                        allowValidation = true;
                        $("#bookingOptions").show();
                        break;

                    case "cancelled_booking":
                        $("#bookingHeader").addClass("bg-danger");
                        activeClass = "bg-danger";
                        $("#bookingHeader span").html("BOOKING CANCELLED <i class='fas fa-window-close'></i>");
                        $("#bookingContent").html("<p style='font-size:20px'>Guest's booking has been cancelled. Direct guest to kiosk for help.</p>" + res.html);
                        $("#bookingOptions").show();
                        $("#bookingValidate").hide();
                        break;

                    case "unpaid_booking":

                        $("#bookingHeader").addClass("bg-warning");
                        activeClass = "bg-warning";
                        $("#bookingHeader span").html("UNPAID BOOKING <i class='fas fa-exclamation-circle'></i>");
                        $("#bookingContent").html("<p style='font-size:20px'>Direct guest to kiosk to pay for their tickets.</p>");
                        $("#bookingOptions").show();
                        $("#bookingValidate").hide();
                        break;

                    case "no_booking":
                        $("#bookingHeader").addClass("bg-danger");
                        activeClass = "bg-danger";
                        $("#bookingHeader span").html("No booking found with provided reference.");
                        $("#bookingContent").html("<p style='font-size:20px'>Please try a different reference or direct guest to kiosk for help.</p>");
                        
                        return;
                        break;

                    default:
                        alert("ERROR PROCESSING SUCCESSFUL RESPONSE - SEE CONSOLE");
                        console.log(res);
                        return;
                        break;

                }

            } else {

                switch(res.code) {

                    case "s":

                    default:
                        alert("ERROR PROCESSING FAILED RESPONSE - SEE CONSOLE.");
                        console.log(res);
                        return;
                        break;

                }


            }


        }

        function resetBookingScreen() {

            $("#bookingInput").removeAttr("disabled");
            $("#bookingInput").removeClass("disabled");
            $("#bookingSearch").removeClass("disabled");
            $("#bookingSearch").removeAttr("disabled");
            $("#bookingContent").html("");
            allowValidation = false;
            $("#bookingHeader").removeClass(activeClass);
            $("#bookingHeader span").html("Awaiting command");
            $("#bookingHeader").removeClass("text-white");
            $("#bookingValidate").show();
            $("#bookingOptions").hide();

        }

        $("#bookingClear").on("click", function(){

            $("#bookingInput").val("");
            resetBookingScreen();

        });

        $("#bookingValidate").on("click", function(){

            $.ajax({
                url: "/Manage/ajax/tools/ticketPOS/validate",
                method: "POST",
                data: {booking_ref: $("#bookingInput").val()},
                success: function(res) {

                    alert("Sucessfully marked as collected.");
                    $("#bookingInput").val("");
                    resetBookingScreen();

                },
                error: function(err) {

                    alert("ERROR PROCESSING FAILED RESPONSE - SEE CONSOLE.");
                    console.log(res);
                    return;

                }
            });

        });

    },

    movePerformance: function(bookingId) {

        showModal("Move Performance", "<h3 class='p-5 text-center'>Loading...</h3>", {"size":"lg", "vcenter":true});

        $.ajax({
            url:"/Manage/ajax/bookings/" + bookingId + "/movePerformance",
            method: "GET",
            success: function(result) {

                updateModal(result);
                console.log("success");

                $(".MP-item").on("click", function(){

                    let id = $(this).attr("data-showid");

                    $("#MPstep1").hide();
                    $(".Bkloader").show();

                    $.ajax({
                        url: "/Manage/ajax/bookings/" + bookingId + "/movePerformance/selection",
                        method: "POST",
                        data: {showId:id},
                        success: function(result) {

                            $(".Bkloader").hide();
                            $("#MPstep2 .screen tbody").html(result.html);
                            $("#selectionAllowed").html(result.allowed);
                            $("#originalSeats").html(result.seats);
                            $("#MPstep2").show();

                            Seatpicker.start({
                                ignoreSpaces: true,
                                seatOnClick: function(selector, seatId) {

                                    if($(selector).closest("td").hasClass("seat-taken")) {
                                        return;

                                    }

                                    if($(selector).closest("td").hasClass("seat-blocked")) {
                                        alert("Due to social distancing, this seat is not available.")
                                        return;

                                    }

                                    if(Seatpicker.settings.selectedSeats.includes(seatId)) {

                                        Seatpicker.settings.selectedSeats = Seatpicker.settings.selectedSeats.filter(function(elem){

                                            return elem != seatId;

                                        });

                                        let url = $(selector).attr("src");
                                        let finalUrl = url.replace("RED", "GREEN");

                                        $(selector).attr("src", finalUrl);
                                        $("#selectionCount").html(Seatpicker.selectedSeatCount());

                                    } else {

                                        if(Seatpicker.selectedSeatCount() >= result.allowed) {

                                            alert("Maximum number of seats selected.");
                                            return;

                                        }

                                        Seatpicker.settings.selectedSeats.push(seatId);
                                        let url = $(selector).attr("src");
                                        let finalUrl = url.replace("GREEN", "RED");

                                        $(selector).attr("src", finalUrl);

                                        $("#selectionCount").html(Seatpicker.selectedSeatCount());

                                    }

                                    if(Seatpicker.selectedSeatCount() == result.allowed) {

                                        $("#MPstep2Confirm").removeClass("btn-outline-success");
                                        $("#MPstep2Confirm").addClass("btn-success");

                                    } else {

                                        $("#MPstep2Confirm").removeClass("btn-success");
                                        $("#MPstep2Confirm").addClass("btn-outline-success");

                                    }

                                }
                            });

                            $("#MPstep2Back").unbind("click").on("click", function(){

                                $("#MPstep2").hide();
                                $("#MPstep2 .screen tbody").html("");
                                $("#selectionCount").html("0");
                                $("#MPstep2Confirm").removeClass("btn-success");
                                $("#MPstep2Confirm").addClass("btn-outline-success");

                                while(Seatpicker.settings.selectedSeats.length) { Seatpicker.settings.selectedSeats.pop(); }

                                $("#MPstep1").show();

                            });

                            $("#MPstep2Confirm").unbind("click").on("click", function(){

                                if(Seatpicker.selectedSeatCount() < result.allowed) {

                                    alert("Please finish selecting the remaining seats.");
                                    return;

                                }

                                if(!confirm("Are you happy with the selection? (Clicking yes will apply the change to the booking)")) {

                                    return;

                                }

                                $("#MPstep2").hide();
                                $(".Bkloader").show();
                                $.ajax({
                                    url: "/Manage/ajax/bookings/" + bookingId + "/movePerformance/process",
                                    method: "POST",
                                    data: {seats:Seatpicker.selectedSeats(), showId: id},
                                    success: function(result) {

                                        updateModal(result.html);

                                        $("#MPClose").on("click", function(){

                                            closeModal();
                                            location.reload();

                                        });

                                    },
                                    error: function(err) {

                                        $(".Bkloader").hide();
                                        $("#MPstep2").show();
                                        alert("An error has occurred");

                                    }

                                });

                            });

                        },
                        error: function(err) {

                            $(".Bkloader").hide();
                            $("#MPstep1").show();

                            alert("An error occurred");

                        }
                    });

                });

            },
            error: function(err) {

                alert("An error has occurred. Please try again later.");
                closeModal();

            }
        });

    }


};