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
                
                $(selector).attr("src", "/assets/images/seats/1-seat_GREEN.png");
            
            } else {
                
                Seatpicker.settings.selectedSeats.push(seatId);
                $(selector).attr("src", "/assets/images/seats/1-seat_RED.png");
                
            }
            
        }
    },
    
    start: function(config) {
        
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