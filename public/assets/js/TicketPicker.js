var TicketPicker = {
    
    settings: {
        ticketConfig: {},
        ticketOption: ".ticket-option",
        ticketsSelected: 0,
        ticketsCost:0,
        ticketOnChange: function(trigger) {

            let ticketType = $(trigger).attr("data-tickettype");
            let ticket = TicketPicker.settings.ticketConfig[ticketType];
            let numOfTickets = $(trigger).val();

            if (numOfTickets >= 1) {

                ticket.count = parseInt(numOfTickets);

                let cost = (numOfTickets * ticket.cost);
                let box = TicketPicker.settings.ticketOption + "-" + ticketType;

                $(box).html("&pound;" + cost + ".00");

            } else {

                $(TicketPicker.settings.ticketOption + "-" + ticketType).html("");

            }

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

        $(TicketPicker.settings.ticketOption).each(function(){

            let ticketType = $(this).attr("data-tickettype");
            let ticket = TicketPicker.settings.ticketConfig[ticketType];

            dataArray[ticketType] =  parseInt($(this).val());

        });

        let data = {
            "show": TicketPicker.settings.show,
            "tickets": dataArray
        };

        return data;

    }

}