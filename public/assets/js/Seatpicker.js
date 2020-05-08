var Seatpicker = {
    
    settings: {
        selectedSeats: [],
        ignoreSpaces: true,
        seatOnClick: function(selector, seatId) {
            
            if($(selector).closest("td").hasClass("seat-taken")) {
                
                alert("SEAT TAKEN");
                return;
                
            }
            
            if(Seatpicker.settings.selectedSeats.includes(seatId)) {
                
                Seatpicker.settings.selectedSeats = Seatpicker.settings.selectedSeats.filter(function(elem){
                    
                    return elem != seatId;
                    
                });

                let url = $(selector).attr("src");
                let finalUrl = url.replace("RED", "GREEN");
                
                $(selector).attr("src", finalUrl);
            
            } else {
                
                Seatpicker.settings.selectedSeats.push(seatId);
                let url = $(selector).attr("src");
                let finalUrl = url.replace("GREEN", "RED");

                $(selector).attr("src", finalUrl);
                
            }
            
        }
    },
    
    start: function(config) {

        while (Seatpicker.settings.selectedSeats.length) { Seatpicker.settings.selectedSeats.pop(); }
        
        $.extend(Seatpicker.settings, config);
        
        $(".screen-seat img").unbind("click").on("click", function(){

            if($(this).closest("td").attr("data-seattype") == "space" && Seatpicker.settings.ignoreSpaces === true) {
                return;
            }
            
            var id = $(this).closest("td").attr("data-seatid");
            
            Seatpicker.settings.seatOnClick($(this), id);
            //seatpicker.selectedSeatCount();
        });
        
    },
    
    seatCount: function() {
        
       return $(".screen-seat").length;
        
    },
    
    selectedSeatCount: function(){
        
        return Seatpicker.settings.selectedSeats.length;
        
    },
    
    selectedSeats: function(){
        
        return Seatpicker.settings.selectedSeats;
        
    }

}