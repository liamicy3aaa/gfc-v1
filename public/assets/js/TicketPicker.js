var TicketPicker = {
    
    settings: {
        ticketConfig: {},
        ticketOption: ".ticket-option",
        ticketsSelected: 0,
        ticketsCost:0,
        cea_card: false,
        cea_full: [],
        cea_validation: false,
        ticketOnChange: function(trigger) {

            let ticketType = $(trigger).attr("data-tickettype");
            let ticket = TicketPicker.settings.ticketConfig[ticketType];
            let numOfTickets = $(trigger).val();

            if (numOfTickets >= 1) {

                ticket.count = parseInt(numOfTickets);

                let cost = (numOfTickets * ticket.cost);
                let box = TicketPicker.settings.ticketOption + "-" + ticketType;

                $(box).html("&pound;" + cost + ".00");

                if(ticket.cea_free == 1) {
                    TicketPicker.settings.cea_card = true;
                }

            } else {

                $(TicketPicker.settings.ticketOption + "-" + ticketType).html("");

                if(ticket.cea_free == 1) {
                    TicketPicker.settings.cea_card = false;
                }

            }

            console.log("status", TicketPicker.settings.cea_card);

            TicketPicker.settings.ticketsSelected = 0;
            TicketPicker.settings.ticketsCost = 0;
            $(TicketPicker.settings.ticketOption).each(function () {

                let ticketType = $(this).attr("data-tickettype");
                let ticket = TicketPicker.settings.ticketConfig[ticketType];
                let numOfTickets = $(this).val();

                let cost = (numOfTickets * ticket.cost);
                TicketPicker.settings.ticketsCost = (cost + TicketPicker.settings.ticketsCost);
                TicketPicker.settings.ticketsSelected = (Number(numOfTickets) + (TicketPicker.settings.ticketsSelected));

            });

            if (TicketPicker.settings.ticketsSelected >= 1) {

                $("#selectedTicketsCost").html(TicketPicker.settings.ticketsCost + ".00");
                $("#selectedTicketsTotal").html(TicketPicker.settings.ticketsSelected);
                $("#selectedTicketsTotal").closest(".card-body").show();
            } else {

                $("#selectedTicketsTotal").closest(".card-body").hide();

            }
        }

        },
    
    start: function(config) {

            if(typeof config.show === "undefined") {

                throw Error("Show id missing from config");
                return;
            }
        
        $.extend(TicketPicker.settings, config);

            // Checking for CEA card item
        $.each(TicketPicker.settings.ticketConfig, function(k, v) {

            if(v.cea_full == 1) {
                TicketPicker.settings.cea_full.push(k);

            }

        });


        $("#selectedTicketsTotal").closest(".card-body").hide();

        $(TicketPicker.settings.ticketOption).on("change", function() {

            TicketPicker.settings.ticketOnChange(this);

        });
        
    },

    selectedTicketsCount: function() {

            return TicketPicker.settings.ticketsSelected;
    },

    selectedTicketsCost: function() {

            return TicketPicker.settings.ticketsCost;

    },

    finish: function() {

        var dataArray = {};
        var allowContinue = true;

        $(TicketPicker.settings.ticketOption).each(function () {

            let ticketType = $(this).attr("data-tickettype");
            let ticket = TicketPicker.settings.ticketConfig[ticketType];

            dataArray[ticketType] = parseInt($(this).val());

        });

        var data = {
            "show": TicketPicker.settings.show,
            "tickets": dataArray,
            "continue": allowContinue,
            "CEA_VALIDATE": (TicketPicker.settings.CEA_VALIDATE !== "" ? TicketPicker.settings.CEA_VALIDATE : false)
        };

        // CEA Card checks
        if (TicketPicker.settings.cea_card == true && TicketPicker.settings.cea_validation == false) {

            var check = false;
            $.each(TicketPicker.settings.cea_full, function (k, v) {

                // Checking that at least one full price ticket is selected
                if (dataArray[v] >= 1) {

                    check = true;
                    return false;
                }

            });

            if (!check) {

                alert("Please select a full price ticket in order to get a free CEA Card ticket.");
                data.continue = false;

            } else {

                data.continue = false;
                TicketPicker.startCEACheck();

            }

        }

        return data;
    },

    startCEACheck: function() {

        showModal("Provide CEA Card number", "<input id='cardCheckInput' class='form-control' type='text' oninput='TicketPicker._checkCEAInput()' maxlength='10'/><br/><button id='cardCheck' class='btn btn-info btn-block disabled' disabled>Validate</button>", {"vcenter": true, size:"md"});

        $("#cardCheck").on("click", function(){

            let card = $("#cardCheckInput").val();
            let validCard = false;
            var error;

            if(card.length < 9) {
                alert("Please provide a valid CEA Card number.");
                return false;
            }

            $.ajax({
                url: "/booking/ajax/cea/validate",
                method: "POST",
                data: {card: card},
                success: function(r) {

                    let result = (r.status !== undefined ? r.status : "");
                    console.log("orginal", r);
                    console.log("result", result);

                    switch(result){
                        case "ACTIVE":
                            validCard = true;
                            break;
                        case "EXPIRED":
                            error = "Your card has expired. Please provide a valid CEA card.";
                            break;
                        case "SUSPENDED":
                            error = "Your card has been suspended. Please contact CEA for more information.";
                            break;
                        default:
                            error = "An error occurred. Please try again later.";
                            break;
                    }

                    if(!validCard) {
                        alert(error);
                    } else {

                        closeModal();
                        TicketPicker.settings.cea_validation = true;
                        TicketPicker.settings.CEA_VALIDATE = r.token;
                        $("#" + stepper.settings.customization.controls.nextBtn.id).trigger("click");
                        return true;

                    }
                },
                error: function(err) {

                    alert("An error occurred. Please try again later.");

                }
            });

        });

    },

    _checkCEAInput: function() {

        if($("#cardCheckInput").val().length >= 9) {

            $("#cardCheck").removeClass("disabled").removeAttr("disabled");

        } else {

            $("#cardCheck").addClass("disabled").attr("disabled", "disabled");

        }

    }


};