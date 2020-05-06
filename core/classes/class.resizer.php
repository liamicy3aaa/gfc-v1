<?php

class resizer {
    
    protected $cropSize;
    protected $uploadUrl;
    protected $successMessage;
    protected $centered;
    
    
    public function __construct() {
    
    $this->centered = false;    
                
    }
    
    public function centerResizer($cmd = "1") {
        
       $this->centered = true;
        
    }
    
    public function setCropSize($width, $height) {
        
         $this->cropSize = array($width, $height);
        
    }
    
    public function setUploadUrl($url) {
        
        $this->uploadUrl = $url;
        
    }
    
    public function setSuccessMessage($message) {
        
        $this->successMessage = $message;
        
    }
    
    public function build() {
        
        $html = "<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.3/croppie.min.css'>";
        
            $html .= "<div class='resizer-content'><div class='custom-file w-50'>";
                
                $html .= "<input id='imageUpload' type='file' class='custom-file-input' id='customFile' name='prof-upload' accept='image/x-png,image/gif,image/jpeg'>";
    
                $html .= "<label class='custom-file-label' for='customFile'>Choose image</label>";
            
            $html .= "</div>&nbsp;<p class='size-rec small mt-3'>Recommended size: " . $this->cropSize[0] . "px by " . $this->cropSize[1] . "px</p>";
            
            $html .= "<div id='imagePreview' class='" . (($this->centered === true) ? 'mx-auto' : '') . "' style='width:350px; margin-top:30px'></div>";
            
            $html .= "<button id='imageSaveClick' class='btn btn-small btn-block btn-success'>Save Image</button></div>";
            
            $html .= "<div class='Bkloader text-center mb-5 mt-5' style='display:none;'>";
        
                $html .= "<div class='spinner-grow text-secondary' style='width: 4rem; height: 4rem;' role='status'>";
                
                $html .= "<span class='sr-only'>Loading...</span>";
        
            $html .= "</div>";
        
            $html .= "<h5 class='mt-5'>Processing image...</h5>";
    
        $html .= "</div>";
            
            $html .= "<script src='https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.3/croppie.min.js'></script>";
            
            $html .= "<script>";

            $html .= "$(\"#imageSaveClick\").attr(\"disabled\", \"true\");";
            $html .= "$(\"#imageSaveClick\").addClass(\"disabled\");";

            $html .= "$('#imageUpload').on('change', function(){";
            
            $html .= "let file = document.getElementById('imageUpload').files[0];";
            
            $html .= "pattern = /image-*/;";
            
            $html .= "if (!file.type.match(pattern)) {";
            
                $html .= "alert('Invalid file provided. Please provide an image file.');";
                $html .= "return;";
                
            $html .= "}";            

                $html .= "$('.custom-file').hide();";
                $html .= "$('.size-rec').hide();";
                $html .= "$(\"#imageSaveClick\").removeAttr(\"disabled\");";
                $html .= "$(\"#imageSaveClick\").removeClass(\"disabled\");";

                $html .= "var reader = new FileReader();";
                $html .= " reader.onload = function (event) {";
        
                    $html .= "$.image_crop = $('#imagePreview').croppie({";
                        $html .= "url: event.target.result,";
                        $html .= "enableExif: true,";
                        $html .= "viewport: {";       // Viewport - Visible part of the image
                        $html .= "width:" . $this->cropSize[0] . ",";  // For a film thumbnail, 341px
                        $html .= "height:" . $this->cropSize[1] . ""; // For a film thumbnail, 512px
                    $html .= "},";
                $html .= "boundary:{";  // Boundary - The container for the croppie of the image.  
                    $html .= "width:" . ($this->cropSize[0] + 60) . ",";
                    $html .= "height:" . ($this->cropSize[1] + 60) . "";
                $html .= "}";
            $html .= "}).then(function(){";
                $html .= "console.log('jQuery bind complete');";
            $html .= "});";
        $html .= "};";
        $html .= " reader.readAsDataURL(this.files[0]);";
    $html .= "});";

    $html .= "$('#imageSaveClick').click(function(event){";

        $html .= "if(confirm(\"The image is correct?\") === false) {";

            $html .= "return false;";

        $html .= "}";

        $html .= "$('#imagePreview').croppie('result', {";
            $html .= "type: 'canvas',";
            $html .= "size: 'viewport'";
        $html .= "}).then(function(response){";
        
            $html .= "$(\".resizer-content\").hide();";
        $html .= "$(\".Bkloader\").show();";

            $html .= "$.ajax({";
                $html .= "url:\"" . $this->uploadUrl . "\",";
                $html .= "type: \"POST\",";
                $html .= "enctype: 'multipart/form-data',";
                $html .= "data:{\"image\": response},";
                $html .= "success:function(data)";
                $html .= "{";
                    $html .= "closeModal(); location.reload(true);";
                $html .= "},";
                $html .= "error: function(data){";

                    $html .= "updateModal(\"<h3 style='color:red;'>An error occurred</h3><br/><hr/><pre>\" + data.error + \"</pre>\");";
                    $html .= "console.log(data);";

                $html .= "}";
            $html .= "});";
        $html .= "})";
    $html .= "});";


    $html .= "</script>";
     
    return $html; 
        
    }
    
}