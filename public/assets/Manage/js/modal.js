
function showModal(heading, content, settings) {

    var showFooter;
    
    var config = {
        "size": "sm",
        "vcenter": false,
        "bodyColor": false
    };

    
    $.extend(config, settings);

    var mSize = "modal-" + config.size;
    var mCentered = (config.vcenter ? "modal-dialog-centered" : "");
    var modalColor = (config.bodyColor ? config.bodyColor : "#ffffff");
     
    html =  '<div id="dynamicModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="confirm-modal" aria-hidden="true">';
    
    html += '<div class="modal-dialog ' + mCentered + ' ' + mSize +'">';
    
    html += '<div class="modal-content">';
    html += '<div class="modal-header">';
    html += '<h5 class="modal-title">'+heading+'</h4>';
    html += '<button type="button" class="close" data-dismiss="modal" aria-label="Close">';
    html += '<span aria-hidden="true">&times;</span>';
    html += '</button>';
    html += '</div>';
    html += '<div class="modal-body" style="background-color:' + modalColor + '">';
    html +=  content;
    html += '</div>';
    html += '</div>';  // dialog
    html += '</div>';  // footer
    html += '</div>';  // modalWindow
    $('body').append(html);
    $("#dynamicModal").modal({backdrop:'static', keyboard:false});
    $("#dynamicModal").modal('show');

    $('#dynamicModal').on('hidden.bs.modal', function (e) {
        $(this).remove();
    });

}


function updateModal(content) {

    if($("#dynamicModal").length !== 0) {
	console.log("TESTING WORKING");
        $("#dynamicModal .modal-body").html(content);

    } else {
        alert('modal doesn\'t exist');
    }


}

function ModalOnClose(func) {

    $("#dynamicModal").on("hidden.bs.modal", function(){
        $("#dynamicModal").unbind("hidden.bs.modal");
        func();
    });

}

function closeModal() {
    
    $("#dynamicModal").modal('hide');
    
    
}