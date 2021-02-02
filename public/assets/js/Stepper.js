function mergeDeep(...objects) {
    const isObject = obj => obj && typeof obj === 'object';

    return objects.reduce((prev, obj) => {
        Object.keys(obj).forEach(key => {
            const pVal = prev[key];
            const oVal = obj[key];

            if (Array.isArray(pVal) && Array.isArray(oVal)) {
                prev[key] = pVal.concat(...oVal);
            }
            else if (isObject(pVal) && isObject(oVal)) {
                prev[key] = mergeDeep(pVal, oVal);
            }
            else {
                prev[key] = oVal;
            }
        });

        return prev;
    }, {});
};

class Stepper {

    defaultSettings() {

        return {
            "container": "stepperContainer",
            "customization": {
                "container": {
                    "classes": "container container-fluid my-4"
                },
                "steps": {
                    "id": "stepsContainer",
                    "classes": "my-2",
                    "step": {
                        "id": "stepperStep",
                        "classes": "container border-1"
                    }
                },
                "stepper": {
                    "id": "stepperStepper",
                    "show": true
                },
                "controls": {
                    "id": "stepperControls",
                    "classes": "btn-group d-flex mb-3",
                    "nextBtn": {
                        "id": "stepperNext",
                        "classes": "btn text-center w-100 btn-outline-secondary",
                        "label": "Next",
                        "icon": "<i class='fas fa-arrow-alt-circle-right'></i>"
                    },
                    "backBtn": {
                        "id": "stepperBack",
                        "classes": "btn text-center w-100 btn-outline-secondary",
                        "label": "Back",
                        "icon": "<i class='fas fa-arrow-alt-circle-left'></i>"
                    }
                },
                "loader": {
                    "label": "Processing..."
                }
            },
            "data": {},
            "steps": [
            ]
        }
    };

    constructor(data) {
        this.settings = mergeDeep(this.defaultSettings(), data);
        this.controls = "";
        this.stepper = "";
        this.steps = "";
        this.currentStep = 0;
        this.totalSteps = (this.settings.steps).length;
        this.isLoaderActive = false;
        this.nextStepData = false;

        this.checkSteps(this.settings.steps);

        // Building layout
        this.buildSteps();
        this.buildControls();
        this.buildStepper();

    };

    checkSteps(steps) {
        let message = "<h4 class='my-3 p-2 text-center'>An error has occurred. Please try again later.</h4>";

        for(var i = 0; i < steps.length; i++) {

            var a = steps[i];

            if(!a.hasOwnProperty("onNext") || typeof a.onNext !== "function") {

                $("#" + this.settings.container).append(message);

                throw new Error("Step " + (i + 1) + " is missing the required onNext function or isn't a function.");
                return;

            }

            if(!a.hasOwnProperty("onBack") || typeof a.onBack !== "function") {

                $("#" + this.settings.container).append(message);


                throw new Error("Step " + (i + 1) + " is missing the required onBack function or isn't a function.");
                return;

            }

            if(!a.hasOwnProperty("onLoad") || typeof a.onLoad !== "function") {

                $("#" + this.settings.container).append(message);


                throw new Error("Step " + (i + 1) + " is missing the required onLoad function or isn't a function.");
                return;

            }

        }

    };

    buildControls() {

        // start Container
        this.controls += "<div id='" + this.settings.customization.controls.id + "' class='" + this.settings.customization.controls.classes + "'>";

        //Insert back button
        this.controls += "<button id='" + this.settings.customization.controls.backBtn.id + "' class='" + this.settings.customization.controls.backBtn.classes + " disabled' disabled='disabled'>" +
            this.settings.customization.controls.backBtn.label + "<br/>" + this.settings.customization.controls.backBtn.icon + "</button>";

        //Insert next button
        this.controls += "<button id='" + this.settings.customization.controls.nextBtn.id + "' class='" + this.settings.customization.controls.nextBtn.classes + "'>" +
            this.settings.customization.controls.nextBtn.label + "<br/>" + this.settings.customization.controls.nextBtn.icon + "</button>";

        // Close container
        this.controls += "</div>";

    };

    buildStepper() {

        if(!this.settings.customization.stepper.show) {

            return;

        }

        // start container
        this.stepper = "<div id='stepperProgress' class='card card-secondary hidden-print mb-3'>";
        this.stepper += "<div class='card-body'>";
        this.stepper += "<div class='row'></div>";
        this.stepper += "<div class='row bs-wizard hidden-xs' style='border-bottom:0;'>";

        //Build an item for each step
        let colWidth = (12 / (Math.ceil(this.settings.steps.length)));

        for(var i = 0; i < this.settings.steps.length; i++) {

            var active;

            if(i < 1) { active = " active"} else { active = "disabled"}

            this.stepper += "<div id='stepperProgress-step" + (i + 1) + "' class='col col-xs-" + colWidth + " bs-wizard-step " + active + "'>";
            this.stepper += "<div class='text-center bs-wizard-stepnum'>Step " + (i + 1) + "</div>";
            this.stepper += "<div class='progress'>";
                this.stepper += "<div class='progress-bar'></div>";
            this.stepper += "</div>";
            this.stepper += "<div class='bs-wizard-dot'></div>";
            this.stepper += "<div class='bs-wizard-info text-center'>" + this.settings.steps[i].step_name + "</div>";
            this.stepper += "</div>";

            }

        // End container
        this.stepper += "</div></div></div>";

    };

    buildSteps() {

        this.steps = "<div id='" + this.settings.customization.steps.id + "' class='" + this.settings.customization.steps.classes + "'>";

        for(var i = 0; i < (this.settings.steps).length; i++) {

            var display;

            if(i >= 1) { display = "style='display:none;'";} else { display = "";}
            this.steps += "<div id='" + this.settings.customization.steps.step.id + (i + 1) + "' class='" + this.settings.customization.steps.step.classes + "' " + display + ">" + this.settings.steps[ i].step_name + "</div>";
        }

        // Add the loader block
        this.steps += "<div id='" + this.settings.customization.steps.step.id + "-loader' style='display:none;' class='text-center my-5'>";

        this.steps += "<div class='spinner-grow text-secondary' style='width: 4rem; height: 4rem;' role='status'>";
        this.steps += "<span class='sr-only'>" + this.settings.customization.loader.label + "</span>";
        this.steps += "</div><h5 class='mt-5'>" + this.settings.customization.loader.label + "</h5>";

        this.steps += "</div></div>";
    };

    load() {

        let p = "#" + this.settings.container;

        if(this.settings.customization.stepper.show) {

            $(p).prepend(this.stepper);

        }

        $(p).append(this.steps);
        $(p).append(this.controls);
        this.startNavigationHandler();

        let data = {
            "item": "#" + stepper.settings.customization.steps.step.id + (stepper.currentStep + 1),
            "id": stepper.currentStep
        };

        this.settings.steps[this.currentStep].onLoad(data);
    };

    startNavigationHandler() {


        // Next
        $("#" + this.settings.customization.controls.nextBtn.id).on("click", function(){

            var a = stepper.settings.steps[stepper.currentStep].onNext();

            if(a === false) {

                stepper.scrollToElement("#stepsContainer");

                return;

            } else {
                //console.log("nextStep", stepper.settings.steps[stepper.currentStep]);

                let data = {
                    "item": "#" + stepper.settings.customization.steps.step.id + (stepper.currentStep + 2),
                    "id": (stepper.currentStep + 1)
                };

                if(stepper.nextStepData !== false) {

                    data.data = stepper.nextStepData;
                    stepper.nextStepData = false;

                }

                let current = stepper.currentStep;

                    stepper.showStep((stepper.currentStep + 1));
                    stepper.settings.steps[stepper.currentStep].onLoad(data);
                    stepper.updateNavigationStatus();


            }

        });

        // Back
        $("#" + this.settings.customization.controls.backBtn.id).on("click", function(){

            if(stepper.settings.steps[stepper.currentStep].onBack() === false) {

                return;

            } else {

                stepper.showStep((stepper.currentStep - 1));
                stepper.updateNavigationStatus();


            }

        });

    };

    updateNavigationStatus() {

        // Nav next btn
        if((stepper.currentStep + 1) >= stepper.totalSteps){

            stepper.btnStatus("navNext", false);

        } else {

            stepper.btnStatus("navNext", true);


        }

        // Nav back btn
        if(stepper.currentStep == 0){

            stepper.btnStatus("navBack", false);

        } else {

            stepper.btnStatus("navBack", true);


        }

        this.updateStepper(stepper.currentStep);

    }

    updateStepper(currentStep) {

        if(!this.settings.customization.stepper.show){

            return;

        }

        for(var i = 0; i < this.settings.steps.length; i++) {
            this.removeStepperStatus((i + 1));

            if(i < currentStep) {

                $("#stepperProgress-step" + (i + 1)).addClass("complete");

            } else if(i == currentStep) {

                $("#stepperProgress-step" + (i + 1)).addClass("active");

            } else if(i > currentStep) {

                $("#stepperProgress-step" + (i + 1)).addClass("disabled");

            } else {


            }

        }

    }

    removeStepperStatus(step) {

        if($("#stepperProgress-step" + step).hasClass("active")) {

            $("#stepperProgress-step" + step).removeClass("active");

        } else if($("#stepperProgress-step" + step).hasClass("complete")) {

            $("#stepperProgress-step" + step).removeClass("complete");

        } else if($("#stepperProgress-step" + step).hasClass("disabled")) {

            $("#stepperProgress-step" + step).removeClass("disabled");

        }

    }

    updateStepContents(id, html) {

        $(id).html(html);

    }

    btnStatus(btn, status) {

        switch(btn) {

            case "navBack":
                if(status === false) {
                    $("#" + this.settings.customization.controls.backBtn.id).addClass("disabled").attr("disabled", "disabled");
                } else {
                    $("#" + this.settings.customization.controls.backBtn.id).removeClass("disabled").removeAttr("disabled");
                }
                break;

            case "navNext":
                if(status === false) {
                    $("#" + this.settings.customization.controls.nextBtn.id).addClass("disabled").attr("disabled", "disabled");
                } else {
                    $("#" + this.settings.customization.controls.nextBtn.id).removeClass("disabled").removeAttr("disabled");
                }

                break;

            default:
                return false;
                break;

        }

        return true;

    }

    showControls(status) {

        if(!status) {

            $("#" + this.settings.customization.controls.id).removeClass("d-flex").addClass("d-none");
            $("#" + this.settings.customization.steps.step.id + (this.currentStep + 1)).addClass("mb-5");

        } else {

            $("#" + this.settings.customization.controls.id).removeClass("d-none").addClass("d-flex");
            $("#" + this.settings.customization.steps.step.id + (this.currentStep + 1)).removeClass("mb-5");

        }

    }

    showStep(step) {

        if(this.settings.steps[step] === false) {

            alert("An error occurred.");

        } else {

            if(this.isLoaderActive === false) {

                $("#" + this.settings.customization.steps.step.id + (this.currentStep + 1)).hide();
                $("#" + this.settings.customization.steps.step.id + (step + 1)).show();

            } else if(this.isLoaderActive == "off") {

                $("#" + this.settings.customization.steps.step.id + (step + 1)).show();
                this.isLoaderActive = false;

            }

            this.currentStep = step;

        }

    }

    showLoader(status) {

        if(status) {

            this.isLoaderActive = "active";
            $("#" + this.settings.customization.steps.step.id + (this.currentStep + 1)).hide();

            $("#" + this.settings.customization.steps.step.id + "-loader").show();
            this.btnStatus("navBack", false);
            this.btnStatus("navNext", false);

        } else {

            this.isLoaderActive = "off";
            $("#" + this.settings.customization.steps.step.id + "-loader").hide();
            this.showStep(this.currentStep);
            this.updateNavigationStatus();

        }

    }

    stepErrorOccurred() {

        if(this.isLoaderActive !== false) {

            this.currentStep  = this.currentStep - 1;
            this.showLoader(false);

        } else {

            this.showStep((this.currentStep - 1));

        }

    }

    test() {

        this.buildSteps();

        return this.steps;

    };

    scrollToElement(element) {

        $('html, body').animate({
            scrollTop: $(element).offset().top
        }, 1000);

    };

}