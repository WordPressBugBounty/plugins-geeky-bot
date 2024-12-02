jQuery(document).ready(function () {
    // Call block for all the #
    jQuery("body").delegate('a[href="#"]', "click", function (event) {
        event.preventDefault();
    });
    // Check boxess multi-selection
    jQuery('#selectall').click(function (event) {
        if (this.checked) {
            jQuery('.geekybot-cb').each(function () {
                this.checked = true;
            });
        } else {
            jQuery('.geekybot-cb').each(function () {
                this.checked = false;
            });
        }``
    });
    //submit form with anchor
    jQuery("a.multioperation").click(function (e) {
        e.preventDefault();
        var total = jQuery('.geekybot-cb:checked').size();
        if (total > 0) {
            var task = jQuery(this).attr('data-for');
            if (task.toLowerCase().indexOf("remove") >= 0) {
                if (confirmdelete(jQuery(this).attr('confirmmessage')) == true) {
                    jQuery("input#task").val(task);
                    jQuery("form#geekybot-list-form").submit();
                }
            } else {
                jQuery("input#task").val(task);
                jQuery("form#geekybot-list-form").submit();
            }
        } else {
            var message = jQuery(this).attr('message');
            alert(message);
        }
    });
});

function confirmdelete(message) {
    if (confirm(message) == true) {
        return true;
    } else {
        return false;
    }
}

function geekybotClosePopup() {
    var popup_div = "";
    var bkpop_div = "";
    popup_div = "div#geekybot-popup";
    bkpop_div = "div#geekybot-popup-background";
    jQuery(popup_div).slideUp();
    jQuery(bkpop_div).hide();
    setTimeout(function () {
        jQuery(popup_div).html(' ');
    }, 350);
}

function geekybotformpopupAdmin(actionname, formid) {
    var formvalid = jQuery('form#' + formid).isValid();
    if (formvalid == false) {
        return;
    }
  
    var userid = jQuery('form#' + formid).find('input.geekybot-form-save-btn').attr('credit_userid');
    var modal = jQuery('#package').val();
    jQuery.post(common.ajaxurl, { action: 'geekybot_ajax_popup', task: actionname, formid: formid, isadmin: 1, userid: userid,module:modal }, function (data) {
        if (data) {
            jQuery("body").append(data);
            jQuery("div#geekybot-popup-background").show().click(function () {
                geekybotClosePopup();
            });
            jQuery("img#popup_cross").click(function () {
                geekybotClosePopup();
            });
            jQuery("div#geekybot-popup").slideDown();
        }
    });
}

function geekybotformpopup(actionname, formid) {
    var formvalid = jQuery('form#' + formid).isValid();
    if (formvalid == false) {
        return;
    }
    // check if terms and conditions is checked(if it exsists on the layout.)
    var termsandcondtions = jQuery("div.geekybot-terms-and-conditions-wrap").attr("data-geekybot-terms-and-conditions");
    if(termsandcondtions == 1){
        if(!jQuery("input[name='termsconditions']").is(":checked")){
            alert(common.terms_conditions);
            return false;
        }
    }
    jQuery.post(common.ajaxurl, { action: 'geekybot_ajax_popup', task: actionname, formid: formid }, function (data) {
        if (data) {
            jQuery("body").append(data);
            jQuery("div#geekybot-popup-background").show().click(function () {
                function validateUploadFile(file_element, allowed_types, allowed_size){
                    var file = file_element.files[0];
                    var fileext = getExtensions(file.name);
                    var filesize = (file.size / 1024);
                    allowed_types = allowed_types.split(',');
                    var replaceflag = 0;
                    var result = true;
                    if(geekybot_checkExtension(allowed_types, fileext) == 'Y'){
                        if(filesize > allowed_size){
                            alert(common.file_size_exceeded);
                            replaceflag = 1;
                            result = false;
                        }
                    }else{
                        alert(common.file_extension_mismatch);
                        replaceflag = 1;
                        result = false;
                    }
                    if(replaceflag){
                        jQuery(file_element).replaceWith(file_element.outerHTML);
                    }
                    return result;
                }

                function  geekybot_checkExtension(f_e_a, fileext) {
                    var match = 'N';
                    for (var i = 0; i < f_e_a.length; i++) {
                        if (f_e_a[i].toLowerCase() === fileext.toLowerCase()) {
                            match = 'Y';
                            break;
                        }
                    }
                    return match;
                }


                function validateUploadFile(file_element, allowed_types, allowed_size){
                    var file = file_element.files[0];
                    var fileext = getExtension(file.name);
                    var filesize = (file.size / 1024);
                    allowed_types = allowed_types.split(',');
                    var replaceflag = 0;
                    var result = true;
                    if(geekybot_checkExtension(allowed_types, fileext) == 'Y'){
                        if(filesize > allowed_size){
                            alert(common.file_size_exceeded);
                            replaceflag = 1;
                            result = false;
                        }
                    }else{
                        alert(common.file_extension_mismatch);
                        replaceflag = 1;
                        result = false;
                    }
                    if(replaceflag){
                        jQuery(file_element).replaceWith(file_element.outerHTML);
                    }
                    return result;
                }

                function  geekybot_checkExtension(f_e_a, fileext) {
                    var match = 'N';
                    for (var i = 0; i < f_e_a.length; i++) {
                        if (f_e_a[i].toLowerCase() === fileext.toLowerCase()) {
                            match = 'Y';
                            break;
                        }
                    }
                    return match;
                }

                function getExtensions(filename) {
                    return filename.split('.').pop().toLowerCase();
                }
                function getExtension(filename) {
                    return filename.split('.').pop().toLowerCase();
                }        geekybotClosePopup();
            });
            jQuery("img#popup_cross").click(function () {
                geekybotClosePopup();
            });
            jQuery("div#geekybot-popup").slideDown();
        }
    });
}

function submitPostInstallatinForm(step){
    if (step == 1) {
        if (!validateSelect2Field()) {
            alert('Please select a template.');
            e.preventDefault(); // Prevent form submission if validation fails
        }
    }
    geekybotShowLoading();
    jQuery('#geekybot-form-ins').submit();
}

function geekybotShowLoading(){
    jQuery('div#geekybotadmin_black_wrapper_built_loading').show();
    jQuery('div#geekybotadmin_built_loading').show();
}

function geekybotHideLoading(){
    jQuery('div#geekybotadmin_black_wrapper_built_loading').hide();
    jQuery('div#geekybotadmin_built_loading').hide();
}

function emailverify(email) {
    var emailParts = email.toLowerCase().split('@');
    if (emailParts.length == 2) {
        regex = /^[a-zA-Z0-9.!#$%&‚Äô*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/;
        return regex.test(email);
    }
    return false;
}

function draw() {
    var objects = document.getElementsByClassName('goldjob');
    for (var i = 0; i < objects.length; i++) {
        var canvas = objects[i];
        if (canvas.getContext) {
            var ctx = canvas.getContext('2d');
            ctx.fillStyle = "#FFFFFF";
            ctx.beginPath();
            ctx.moveTo(0, 0);
            ctx.lineTo(10, 10);
            ctx.lineTo(0, 20);
            ctx.fill();
        }
    }
}

window.onload = function () {
    draw();
};

function fillSpaces(string) {
    string = string.replace(" ", "%20");
    return string;
}

function geekybot_DecodeHTML(html) {
    var txt = document.createElement('textarea');
    txt.innerHTML = html;
    return txt.value;
}
