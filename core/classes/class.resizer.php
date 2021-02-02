<?php

/**
 * Class resizer
 *
 * @author Liam McClelland
 * @property array $cropSize An array containing the width and height of what you wish the image to be cropped to Eg. [1920,1080]
 * @property string $uploadUrl The web address which the cropped file should be uploaded to.
 * @property string $successMessage The message you wish to display on successful completion of the crop and upload.
 * @property bool $centered Controls whether the resizer is displayed in the center of the container.
 */
class resizer {
    
    protected $cropSize;
    protected $uploadUrl;
    protected $successMessage;
    protected $centered;

    /**
     * resizer constructor.
     */

    public function __construct() {
    
    $this->centered = false;    
                
    }

    /**
     * Center Resizer
     *
     * Allows you to align the resizer client to the center of the container.
     *
     * @param string $cmd Optional
     */

    public function centerResizer($cmd = "1") {
        
       $this->centered = true;
        
    }

    /**
     * Set Crop Size
     *
     * Allows you to set the size of the image you wish to receive.
     *
     * @param int $width Number of pixels wide the crop size should be.
     * @param int $height Number of pixels high the crop size should be.
     */

    public function setCropSize($width, $height) {
        
         $this->cropSize = array($width, $height);
        
    }

    /**
     * Set Upload URL
     *
     * Allows you to set the endpoint for where you wish the cropped image to be uploaded to.
     *
     * @param string $url The web address you want the cropped image to be uploaded to.
     */

    public function setUploadUrl($url) {
        
        $this->uploadUrl = $url;
        
    }

    /**
     * Set Success Message
     *
     * Allows you to set a custom success message which will be displayed after the successful completion of the crop and upload.
     *
     * @param string $message The message you wish to be displayed after successful completion of the crop and upload.
     */

    public function setSuccessMessage($message) {
        
        $this->successMessage = $message;
        
    }

    /**
     * Build Resizer Client
     *
     * Generates the html for the resizer client.
     *
     * @return string Html for the resizer front-end client.
     */

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