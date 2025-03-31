<?php
if (!defined('ABSPATH'))
    die('Restricted Access');
wp_enqueue_script('jquery');
// Enqueue jQuery UI
wp_enqueue_script('jquery-ui-draggable');
if (isset(geekybot::$_data[0]['story'])) {
    $story_id = geekybot::$_data[0]['story']->id;
    // $story_id = 60;
    $slotList = "recheck";
    $typelist = array(
        (object) array('id' => '', 'text' => __('Select Type', 'geeky-bot')),
        (object) array('id' => 'PersonName', 'text' => __('PersonName', 'geeky-bot')),
        (object) array('id' => 'Facility', 'text' => __('Facility', 'geeky-bot')),
        (object) array('id' => 'Organization', 'text' => __('Organization', 'geeky-bot')),
        (object) array('id' => 'Location', 'text' => __('Location', 'geeky-bot')),
        (object) array('id' => 'Location_NON_GPEON_GPE', 'text' => __('Location_NON_GPE', 'geeky-bot')),
        (object) array('id' => 'Product', 'text' => __('Product', 'geeky-bot')),
        (object) array('id' => 'Event', 'text' => __('Event', 'geeky-bot')),
        (object) array('id' => 'ArtWork', 'text' => __('ArtWork', 'geeky-bot')),
        (object) array('id' => 'LawDocument', 'text' => __('LawDocument', 'geeky-bot')),
        (object) array('id' => 'Language', 'text' => __('Language', 'geeky-bot')),
        (object) array('id' => 'Date', 'text' => __('Date', 'geeky-bot')),
        (object) array('id' => 'Time', 'text' => __('Time', 'geeky-bot')),
        (object) array('id' => 'Percentage', 'text' => __('Percentage', 'geeky-bot')),
        (object) array('id' => 'Money', 'text' => __('Money', 'geeky-bot')),
        (object) array('id' => 'Quantity', 'text' => __('Quantity', 'geeky-bot')),
        (object) array('id' => 'Ordinal', 'text' => __('Ordinal', 'geeky-bot')),
        (object) array('id' => 'Cardinal', 'text' => __('Cardinal', 'geeky-bot')),
    );
    $paramlist = array(
        (object) array('id' => '', 'text' => __('Select Type', 'geeky-bot')),
        (object) array('id' => 'integer', 'text' => __('Integer', 'geeky-bot')),
        (object) array('id' => 'float', 'text' => __('Float', 'geeky-bot')),
        (object) array('id' => 'boolean', 'text' => __('Boolean', 'geeky-bot')),
        (object) array('id' => 'string', 'text' => __('String', 'geeky-bot'))
    );
    $startPointMsg = __('Start Point', 'geeky-bot');
    $fallbackMsg = __('Default Fallback', 'geeky-bot');
    $geekybot_js ="
    jQuery(document).ready(function() {
        jQuery(document).on('keypress', '#searchInput', function(event) {
            // Check if the Enter key (key code 13) is pressed
            if (event.which === 13) {
                event.preventDefault();  // Prevent the default action (form submission or other behavior)
                // Trigger the search button click
                jQuery('#searchBtn').click();
            }
        });
        jQuery(document).on('click', '#searchBtn', function(event) {
            var storyid = jQuery('input#storyid').val();
            var searchQuery = jQuery('#searchInput').val().trim();
            var searchType = jQuery('#searchType').val().trim();
            jQuery('#geekybotSearchResultsWrp').html('');

            if (searchQuery === '') {
                alert('" . __('Please enter a search term.', 'geeky-bot') . "');
                return;
            }
            // get search results
            var ajaxurl = '" . esc_url(admin_url("admin-ajax.php")) . "';
            jQuery.post(ajaxurl, {
                action: 'geekybot_ajax',
                geekybotme: 'stories',
                task: 'getSearchResults',
                storyid: storyid,
                query: searchQuery,
                searchType: searchType,
                '_wpnonce': '" . esc_attr(wp_create_nonce("story-search-results-".$story_id)) . "'
            }, function(response) {
                geekybotHideLoading();
                jQuery('#geekybotSearchResultsWrp').show();
                if (response) {
                    let data = JSON.parse(response);
                    let html = '';
                    // Display user inputs
                    if (data.userinputs.length > 0) {
                        data.userinputs.forEach(record => {
                            let userInput = record.user_messages_text.split(' ').slice(0, 3).join(' '); // Get only first 3 words
                            html += `<div id='` + record.group_id + `' title=\"` + record.user_messages_text + `\" class='user-input-search-result'>` + userInput + `</div>`;
                        });
                    }

                    // Display bot responses (text & function names)
                    if (data.responses.length > 0) {
                        data.responses.forEach(record => {
                            let responseText = record.bot_response || 'Unknown Response';
                            let botResponse = record.bot_response.split(' ').slice(0, 3).join(' '); // Get only first 3 words
                            if (record.response_type == 1) {
                                html += `<div id='` + record.id + `' title=\"` + record.bot_response + `\" class='response-search-result'>` + botResponse + `</div>`;
                            } else if (record.response_type == 4) {
                                html += `<div id='` + record.id + `' title=\"` + record.bot_response + `\" class='function-search-result'>` + botResponse + `</div>`;
                            }
                        });
                    }

                    if (html === '') {
                        jQuery('#geekybotSearchResultsWrp').html(`<span>" . __('No matching results found.', 'geeky-bot') . "</span>`).css('display', 'flex');
                    } else {
                        let mainhtml = `<span>" . __('Results:', 'geeky-bot') . " </span>`;
                        mainhtml += `<div id='geekybot-story-result-left-arrow' class='geekybot-left-arrow-result-wrp'> <span class='geekybot-left-arrow-result'><img id=geekybot-left-arrow-icon src=".GEEKYBOT_PLUGIN_URL ."includes/images/story/story-left.png /></span> </div>`;
                        mainhtml += `<div id='searchResults' class='geekybot-story-searchresult-wrp'>`;
                        mainhtml += html;
                        mainhtml += `</div>`;
                        mainhtml += `<div id='geekybot-story-result-right-arrow' class='geekybot-right-arrow-result-wrp'> <span class='geekybot-right-arrow-result'><img id=geekybot-right-arrow-icon src=".GEEKYBOT_PLUGIN_URL ."includes/images/story/story-right.png /></span> </div>`;
                        jQuery('#geekybotSearchResultsWrp').append(mainhtml).css('display', 'flex');
                    }
                } else {
                    jQuery('#searchResults').html('" . __('No matching results found.', 'geeky-bot') . "');
                }
            });
            jQuery(document).ready(function () {
                jQuery('#geekybot-story-result-left-arrow').click(function () {
                    jQuery('#searchResults').animate({ scrollLeft: '-=200' }, 300);
                });
            
                jQuery('#geekybot-story-result-right-arrow').click(function () {
                    jQuery('#searchResults').animate({ scrollLeft: '+=200' }, 300);
                });
            });
            
        });
        jQuery(document).on('click', '#searchReset', function(event) {
            jQuery('#searchInput').val('');
            jQuery('#searchType').val('2');
            jQuery('#geekybotSearchResultsWrp').hide();
            jQuery('#geekybotSearchResultsWrp').html('');
        });
        jQuery(document).on('click', '.user-input-search-result', function(event) {
            // open user input popup and close all other tags
            jQuery('div.geekybot_story_right_popup_inner_wrp').slideUp('slow');
            jQuery('div#userinput-popup').slideDown('slow');
            jQuery('div.geekybot-avlble-varpopup').slideUp('slow');
            jQuery('div#response-text-popup').slideUp('slow');
            jQuery('div#response-function-popup').slideUp('slow');
            jQuery('div#response-action-popup').slideUp('slow');
            jQuery('div#default-fallback-popup').slideUp('slow');
            jQuery('div#default-intent-fallback-popup').slideUp('slow');
            jQuery('div#response-form-popup').slideUp('slow');
            
            // Find the input field within the parentDiv
            var user_search = 'intentid_'+jQuery(this).attr('id');
            var inputField = jQuery('input[type=\"hidden\"]').filter(function() {
                return jQuery(this).val() === user_search;
            });
            // Access and manipulate the input field (if it exists)
            if (inputField.length > 0) {
                // Do something with the input field
                jQuery('input[type=\"hidden\"]').removeClass('active_node');
                jQuery('div.geekybot_story_leftaction_wrp').removeClass('active_node_parent');
                inputField.addClass('active_node'); // Example: Set a new value
                inputField.closest('.geekybot_story_leftaction_wrp').addClass('active_node_parent');
                // bind data in case of update
                var input_value = inputField.val();
                if (input_value !== undefined || input_value !== \"\") {
                    var idNumber = parseInt(input_value.split('_')[1]);
                    jQuery('div#userInputFormBody').html('');
                    jQuery('div#responseTextFormBody').html('');
                    jQuery('div#responseFunctionFormBody').html('');
                    // get values using ajax
                    var ajaxurl = '". esc_url(admin_url("admin-ajax.php")) ."';
                    jQuery('div#userInputFormBody').append('<img id=\"geekybot-loading-icon\" src=\"".GEEKYBOT_PLUGIN_URL ."includes/images/story/story_load.gif\" />');
                    jQuery.post(ajaxurl, {
                        action: 'geekybot_ajax',
                        geekybotme: 'stories',
                        task: 'getUserInputFormBodyHTMLAjax',
                        id: idNumber,
                        '_wpnonce':'". esc_attr(wp_create_nonce("get-form-html")) ."'
                    }, function(data) {
                        jQuery('div#userInputFormBody').find('img#geekybot-loading-icon').remove();
                        if (data) {
                            jQuery('div#userInputFormBody').html(geekybot_DecodeHTML(data));
                        } else {
                            jQuery('div#userInputFormBody').html('<span class=\"geekybot_error_msg\">". esc_attr(__('Something went wrong try again later!', 'geeky-bot')) ."</span>');
                        }
                    });
                }
                // move controll to the specific node
                var nid = jQuery('.possible_node.active_node').attr('data-nodeid');
                var node = jQuery('#' + nid); // Select the node using its ID
                if (node.length) {
                    var nodePosition = node.position().left; // Get the left position of the node
                    nodePosition = nodePosition - 250;
                    var canvasContainer = document.getElementById('canvas_container');

                    // Scroll the canvas container to the node's position smoothly
                    canvasContainer.scrollTo({left: nodePosition, behavior: 'smooth'});
                    // canvasContainer.animate({ scrollLeft: nodePosition }, 800);
                }
            } else {
                jQuery('input[type=\"hidden\"]').removeClass('active_node');
                jQuery('div.geekybot_story_leftaction_wrp').removeClass('active_node_parent');
            }
        });
        jQuery(document).on('click', '.response-search-result', function(event) {
            // open response popup and close all other tags
            jQuery('div.geekybot_story_right_popup_inner_wrp').slideUp('slow');
            jQuery('div#response-text-popup').slideDown('slow');
            jQuery('div.geekybot-avlble-varpopup').slideUp('slow');
            jQuery('div#userinput-popup').slideUp('slow');
            jQuery('div#response-function-popup').slideUp('slow');
            jQuery('div#response-action-popup').slideUp('slow');
            jQuery('div#default-fallback-popup').slideUp('slow');
            jQuery('div#default-intent-fallback-popup').slideUp('slow');
            jQuery('div#response-form-popup').slideUp('slow');
            // Find the input field within the parentDiv
            var user_search = 'responseid_'+jQuery(this).attr('id');
            var inputField = jQuery('input[type=\"hidden\"]').filter(function() {
                return jQuery(this).val() === user_search;
            });
            // Access and manipulate the input field (if it exists)
            if (inputField.length > 0) {
                // Do something with the input field
                jQuery('input[type=\"hidden\"]').removeClass('active_node');
                jQuery('div.geekybot_story_leftaction_wrp').removeClass('active_node_parent');
                inputField.addClass('active_node'); // Example: Set a new value
                inputField.closest('.geekybot_story_leftaction_wrp').addClass('active_node_parent');
                // bind data in case of update
                var input_value = inputField.val();
                if (input_value !== undefined || input_value !== \"\") {
                    var idNumber = parseInt(input_value.split('_')[1]);
                    jQuery('div#userInputFormBody').html('');
                    jQuery('div#responseTextFormBody').html('');
                    jQuery('div#responseFunctionFormBody').html('');
                    // get values using ajax
                    var ajaxurl = '". esc_url(admin_url("admin-ajax.php")) ."';
                    jQuery('div#responseTextFormBody').append('<img id=\"geekybot-loading-icon\" src=\"".GEEKYBOT_PLUGIN_URL ."includes/images/story/story_load.gif\" />');
                    jQuery.post(ajaxurl, {
                        action: 'geekybot_ajax',
                        geekybotme: 'stories',
                        task: 'getResponseTextFormBodyHTMLAjax',
                        id: idNumber,
                        '_wpnonce':'". esc_attr(wp_create_nonce("get-form-html")) ."'
                    }, function(data) {
                        jQuery('div#responseTextFormBody').find('img#geekybot-loading-icon').remove();
                        if (data) {
                            jQuery('div#responseTextFormBody').html(geekybot_DecodeHTML(data));
                        } else {
                            jQuery('div#responseTextFormBody').html('<span class=\"geekybot_error_msg\">". esc_attr(__('Something went wrong try again later!', 'geeky-bot')) ."</span>');
                        }
                    });
                }
                // move the controll to the specific node
                var nid = jQuery('.possible_node.active_node').attr('data-nodeid');
                var node = jQuery('#' + nid); // Select the node using its ID
                if (node.length) {
                    var nodePosition = node.position().left; // Get the left position of the node
                    nodePosition = nodePosition - 250;
                    var canvasContainer = document.getElementById('canvas_container');

                    // Scroll the canvas container to the node's position smoothly
                    canvasContainer.scrollTo({left: nodePosition, behavior: 'smooth'});
                    // canvasContainer.animate({ scrollLeft: nodePosition }, 800);
                }
            } else {
                jQuery('input[type=\"hidden\"]').removeClass('active_node');
                jQuery('div.geekybot_story_leftaction_wrp').removeClass('active_node_parent');
            }
        });
        jQuery(document).on('click', '.function-search-result', function(event) {
            // open response type function popup and close all other tags
            jQuery('div#response-function-popup').slideDown('slow');
            jQuery('div.geekybot_story_right_popup_inner_wrp').slideUp('slow');
            jQuery('div.geekybot-avlble-varpopup').slideUp('slow');
            jQuery('div#userinput-popup').slideUp('slow');
            jQuery('div#response-text-popup').slideUp('slow');
            jQuery('div#response-action-popup').slideUp('slow');
            jQuery('div#default-fallback-popup').slideUp('slow');
            jQuery('div#default-intent-fallback-popup').slideUp('slow');
            jQuery('div#response-form-popup').slideUp('slow');
            // Find the input field within the parentDiv
            var user_search = 'responseid_'+jQuery(this).attr('id');
            var inputField = jQuery('input[type=\"hidden\"]').filter(function() {
                return jQuery(this).val() === user_search;
            });
            // Access and manipulate the input field (if it exists)
            if (inputField.length > 0) {
                // Do something with the input field
                jQuery('input[type=\"hidden\"]').removeClass('active_node');
                jQuery('div.geekybot_story_leftaction_wrp').removeClass('active_node_parent');
                inputField.addClass('active_node'); // Example: Set a new value
                inputField.closest('.geekybot_story_leftaction_wrp').addClass('active_node_parent');
                // bind data in case of update
                var input_value = inputField.val();
                if (input_value !== undefined || input_value !== \"\") {
                    var idNumber = parseInt(input_value.split('_')[1]);
                    jQuery('div#userInputFormBody').html('');
                    jQuery('div#responseTextFormBody').html('');
                    jQuery('div#responseFunctionFormBody').html('');
                    // get values using ajax
                    var ajaxurl = '". esc_url(admin_url("admin-ajax.php")) ."';
                    jQuery('div#responseFunctionFormBody').append('<img id=\"geekybot-loading-icon\" src=\"".GEEKYBOT_PLUGIN_URL ."includes/images/story/story_load.gif\" />');
                    jQuery.post(ajaxurl, {
                        action: 'geekybot_ajax',
                        geekybotme: 'stories',
                        task: 'getResponseFunctionFormBodyHTMLAjax',
                        id: idNumber,
                        story_type: ".esc_attr(geekybot::$_data[0]['story']->story_type).",
                        '_wpnonce':'". esc_attr(wp_create_nonce("get-form-html")) ."'
                    }, function(data) {
                        jQuery('div#responseFunctionFormBody').find('img#geekybot-loading-icon').remove();
                        if (data) {
                            jQuery('div#responseFunctionFormBody').html(geekybot_DecodeHTML(data));
                        } else {
                            jQuery('div#responseFunctionFormBody').html('<span class=\"geekybot_error_msg\">". esc_attr(__('Something went wrong try again later!', 'geeky-bot')) ."</span>');
                        }
                    });
                }
                // move the controll to the specific node
                var nid = jQuery('.possible_node.active_node').attr('data-nodeid');
                var node = jQuery('#' + nid); // Select the node using its ID
                if (node.length) {
                    var nodePosition = node.position().left; // Get the left position of the node
                    nodePosition = nodePosition - 250;
                    var canvasContainer = document.getElementById('canvas_container');

                    // Scroll the canvas container to the node's position smoothly
                    canvasContainer.scrollTo({left: nodePosition, behavior: 'smooth'});
                    // canvasContainer.animate({ scrollLeft: nodePosition }, 800);
                }
            } else {
                jQuery('input[type=\"hidden\"]').removeClass('active_node');
                jQuery('div.geekybot_story_leftaction_wrp').removeClass('active_node_parent');
            }
        });
        // 
        function initScrollArrows() {
            let scrollContainer = jQuery('#searchResults');
            let leftArrow = jQuery('#geekybot-story-result-left-arrow');
            let rightArrow = jQuery('#geekybot-story-result-right-arrow');

            if (!scrollContainer.length) return;

            let isRTL = scrollContainer.css('direction') === 'rtl';

            function updateArrows() {
                let scrollLeft = Math.abs(scrollContainer.scrollLeft());
                let containerWidth = scrollContainer.outerWidth();
                let scrollWidth = scrollContainer[0].scrollWidth;
                let maxScrollLeft = Math.abs(scrollWidth - containerWidth);

                let isScrollable = scrollWidth > containerWidth;

                // Hide both arrows if no scrolling is needed
                if (!isScrollable) {
                    leftArrow.hide();
                    rightArrow.hide();
                    return;
                }

                // Show/hide arrows based on scroll position
                leftArrow.toggle(scrollLeft > 0);
                rightArrow.toggle(scrollLeft < maxScrollLeft - 1);
            }

            // Handle RTL scrolling properly
            leftArrow.off('click').on('click', function () {
                let scrollAmount = isRTL ? '+=200' : '-=200';
                scrollContainer.animate({ scrollLeft: scrollAmount }, 300, updateArrows);
            });

            rightArrow.off('click').on('click', function () {
                let scrollAmount = isRTL ? '-=200' : '+=200';
                scrollContainer.animate({ scrollLeft: scrollAmount }, 300, updateArrows);
            });

            scrollContainer.off('scroll').on('scroll', updateArrows);
            updateArrows();
        }

        // **MutationObserver for Dynamic Updates**
        let observer = new MutationObserver(function () {
            setTimeout(initScrollArrows, 100);
        });

        observer.observe(document.body, { childList: true, subtree: true });

        initScrollArrows();

        // 
        jQuery(document).on('click', '#move_to_end', function(event) {
            if (canvas.width > 1200) {
                // Scroll the container to the end
                var canvasContainer = document.getElementById('canvas_container');
                canvasContainer.scrollTo({left: canvas.width, behavior: 'smooth'});
            }
        });
        jQuery(document).on('click', '#move_to_start', function(event) {
            if (canvas.width > 1200) {
                // Scroll the container to the start
                var canvasContainer = document.getElementById('canvas_container');
                canvasContainer.scrollTo({left: 0, behavior: 'smooth'});
            }
        });
        jQuery(document).on('click', '#move_to_left', function(event) {
            if (canvas.width > 1200) {
                // Scroll the container one screen width to the left
                var canvasContainer = document.getElementById('canvas_container');
                canvasContainer.scrollBy({ left: -canvasContainer.clientWidth, behavior: 'smooth' });
            }
        });
        jQuery(document).on('click', '#move_to_right', function(event) {
            if (canvas.width > 1200) {
                // Scroll the container one screen width to the right
                var canvasContainer = document.getElementById('canvas_container');
                canvasContainer.scrollBy({ left: canvasContainer.clientWidth, behavior: 'smooth' });
            }
        });
        // reset story
        jQuery(document).on('click', '#Reset-Story', function(event) {
            var confirmed = confirm(\"".__('Are you sure to reset it?','geeky-bot')."\");
            if (!confirmed) {
                event.preventDefault(); // Prevent default action if not confirmed
            } else {
                var storyid = jQuery('input#storyid').val();
                geekybotShowLoading();
                jQuery.post(ajaxurl, {
                    action: 'geekybot_ajax',
                    geekybotme: 'stories',
                    task: 'resetStory',
                    storyid: storyid,
                    '_wpnonce':'". esc_attr(wp_create_nonce('reset-story')) ."'
                }, function(data) {
                    geekybotHideLoading();
                    if (data) {
                        idCounter = 2;
                        // start point position
                        positions = [
                            { id: 'node1', top: 500, left: 0, parentId: null, parentType: null, type: 'start_point', text: \"".$startPointMsg."\", image: 'home', class: 'node_start_point', category: 'start' }
                        ];
                        setCanvasWidthHeight();
                        idCounter = idCounter++;
                        drawNodes(idCounter);
                        // Scroll the container to the right to show the new object smoothly
                        var canvasContainer = document.getElementById('canvas_container');
                        canvasContainer.scrollTo({left: canvas.width, behavior: 'smooth'});
                        window.location.reload();
                    } else {
                        console.error('AJAX Error:', textStatus, errorThrown);
                    }
                });
            }
        });
        jQuery(document).on('click', '.typeAndSelect', function() {
            jQuery(this).on('input', function() {
                jQuery('.typeAndSelect').removeClass('specialClassAutocomplete');
                jQuery(this).addClass('specialClassAutocomplete');
                jQuery('.suggestions-for-autocomplete').remove();
                jQuery(this).autocomplete({
                    source: function(request, response) {
                        geekybotShowLoading();
                        jQuery.post(ajaxurl, {
                            action: 'geekybot_ajax',
                            geekybotme: 'slots',
                            task: 'getVariablesValuesForSelect',
                            dataType: 'json',
                            term: request.term,
                            '_wpnonce':'". esc_attr(wp_create_nonce("get-variables")) ."'
                        }, function(data) {
                            geekybotHideLoading();
                            if (data) {
                                var decoded_data = JSON.parse(data);
                                var data = jQuery(decoded_data);
                                if (data.find('.geekybot-intent-usr-msg').length > 0) {
                                    jQuery('.specialClassAutocomplete').after(JSON.parse(data));
                                } else {
                                    jQuery('select:disabled').prop('disabled', false);
                                    jQuery('input[readonly]').removeAttr('readonly');
                                }
                                jQuery('.specialClassAutocomplete').removeClass('ui-autocomplete-loading');
                                
                            } else {
                                console.error('AJAX Error:', textStatus, errorThrown);
                            }
                        });
                    },
                    minLength: 2 // Minimum characters to trigger search
                });
            });
        });
        jQuery(\"select[name='variable_type[]']\").click(function(event) {
            jQuery('.suggestions-for-autocomplete').remove();
        });
        jQuery(document).on('click', '.geekybot-intent-usr-msg', function() {
            var value = jQuery(this).attr('data-value');
            var text = jQuery(this).text();
            var parentDiv = jQuery(this).closest('.geeky-popup-dynamic-field.geeky-popup-cutom-form-dynamic-field');
            // make inactive the previously selected items
            jQuery('.suggestions-for-autocomplete').remove();
            jQuery('.geeky-popup-dynamic-field.geeky-popup-cutom-form-dynamic-field').removeClass('selectedFormField');
            jQuery('.variableName').removeClass('selectedVariableName');
            jQuery('.variableType').removeClass('selectedVariableType');
            jQuery('.variablePossibleValues').removeClass('selectedVariablePossibleValues');
            // make active the currently selected items
            jQuery(parentDiv).addClass('selectedFormField');
            jQuery('.selectedFormField .variableName').addClass('selectedVariableName');
            jQuery('.selectedFormField .variableType').addClass('selectedVariableType');
            jQuery('.selectedFormField .variablePossibleValues').addClass('selectedVariablePossibleValues');
            // call ajax to bind the values
            bindValuesOnSelect(value);
            // set the fields readonly on select value
            jQuery('.selectedVariableType').prop('disabled', true);
            jQuery('.selectedVariablePossibleValues').prop('readonly', true);
        });
        // here
        jQuery('#responseAddForm').click(function(event) {
            jQuery('.suggestions-for-autocomplete').remove();
        });
        jQuery(document).on('change', 'select.response-btn-type', function() {
            jQuery('.geeky-popup-dynamic-field').removeClass('geeky-popup-dynamic-field-active');
            jQuery(this).closest('.geeky-popup-dynamic-field').addClass('geeky-popup-dynamic-field-active');

            var type = jQuery(this).val();
            if(type == '1') {
                jQuery('.geeky-popup-dynamic-field-active .response-btn-url').css('display', 'none');
                jQuery('.geeky-popup-dynamic-field-active .response-btn-value').css('display', 'block');
            } else {
                jQuery('.geeky-popup-dynamic-field-active .response-btn-value').css('display', 'none');
                jQuery('.geeky-popup-dynamic-field-active .response-btn-url').css('display', 'block');
            }
        });
        // available variable popup
        jQuery(document).on('click', '#geekybot-avlble-varbtn', function() {
            jQuery('div.geekybot-avlble-varpopup').slideDown('slow');
        });
        jQuery('#avlble-varpopup-closebtn').click(function (e) {
            jQuery('div.geekybot-avlble-varpopup').slideUp('slow');
        });
    });

    // function to bind the values
    function bindValuesOnSelect(id){
        // get values using ajax
        var ajaxurl = '". esc_url(admin_url("admin-ajax.php")) ."';
        geekybotShowLoading();
        jQuery.post(ajaxurl, {
            action: 'geekybot_ajax',
            geekybotme: 'slots',
            task: 'bindValuesOnSelectAjax',
            id: id,
            '_wpnonce':'". esc_attr(wp_create_nonce("get-variable-attributes")) ."'
        }, function(data) {
            geekybotHideLoading();
            if (data) {
                var decoded_data = jQuery.parseJSON(data);
                jQuery('.selectedVariableName').val(decoded_data.name);
                jQuery('.selectedVariableType').val(decoded_data.type);
                jQuery('.selectedVariablePossibleValues').val(decoded_data.possible_values);
            } else {
                jQuery('.selectedFormField').html('<span class=\"geekybot_error_msg\">". esc_attr(__('Something went wrong try again later!', 'geeky-bot')) ."</span>');
            }
        });
    }
    function deleteIntentFallback(group_id){
        var story_id = ". $story_id .";
        var ajaxurl = '". esc_url(admin_url("admin-ajax.php")) ."';
        jQuery.post(ajaxurl, {
            action: 'geekybot_ajax',
            geekybotme: 'intent',
            task: 'deleteIntentFallback',
            group_id: group_id,
            story_id: story_id,
            '_wpnonce':'". esc_attr(wp_create_nonce("delete-intent-fallback-".$story_id)) ."'
        }, function(data) {
            
        });
    }
    function deleteDefaultFallback(){
        var story_id = ". $story_id .";
        var ajaxurl = '". esc_url(admin_url("admin-ajax.php")) ."';
        jQuery.post(ajaxurl, {
            action: 'geekybot_ajax',
            geekybotme: 'stories',
            task: 'deleteDefaultFallback',
            story_id: story_id,
            '_wpnonce':'". esc_attr(wp_create_nonce("delete-default-fallback-".$story_id)) ."'
        }, function(data) {
            
        });
    }


    // select and type end
    // Function to create input component
    var userInputDivId = 1;
    function addUserInputText(param) {
        if (userInputDivId != param && userInputDivId < param) {
            userInputDivId = param;
        }
        // console.log(userInputDivId);
        var container = jQuery('#user-popup-inputs');
        container.append('<div class=\"geeky-popup-dynamic-field\" id=\"div_'+userInputDivId+'\"><input name = \"user_messages[]\" type=\"text\" value = \"\" class=\"inputbox geeky-popup-dynamic-field-input\" autocomplete=\"off\" placeholder=\"". esc_attr(__('User Input','geeky-bot'))."\" /><span class=\"geeky-popup-dynamic-remov-image remove-btn\" onClick=\"deleteUserInputText(div_'+userInputDivId+')\"><img title=\"". esc_html(__('Delete','geeky-bot'))."\" alt=\"". esc_html(__('Close','geeky-bot'))."\" class=\"userpopup-close\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/close.png\" /></span></div>');
        userInputDivId++;
    }
    function deleteUserInputText(userInputDivId){
        jQuery(userInputDivId).remove();
    }
    // Function to create response buttons
    var responseButtonDivId = 1;
    function addResponseButton(param) {
        if (responseButtonDivId != param && responseButtonDivId < param) {
            responseButtonDivId = param;
        }
        // console.log(userInputDivId);
        var container = jQuery('#response-popup-text');
        container.append('<div class=\"geeky-popup-dynamic-field\" id=\"div_'+responseButtonDivId+'\"><select name=\"response_btn_type[]\" id=\"response_btn_type[]\" class=\"response-btn-type inputbox geeky-popup-dynamic-field-input geeky-popup-dynamic-field-select\" data-validation=\"required\"><option value=\"1\">". esc_attr(__('User Input','geeky-bot'))."</option><option value=\"2\">". esc_attr(__('URL','geeky-bot'))."</option></select><input name = \"response_btn_text[]\" type=\"text\" value = \"\" class=\"inputbox geeky-popup-dynamic-field-input\" autocomplete=\"off\" placeholder=\"". esc_attr(__('Button text here','geeky-bot'))."\" /><input name = \"response_btn_value[]\" type=\"text\" value = \"\" class=\"response-btn-value inputbox geeky-popup-dynamic-field-input\" autocomplete=\"off\" placeholder=\"". esc_attr(__('Button value here','geeky-bot'))."\" /><input name = \"response_btn_url[]\" type=\"text\" value = \"\" class=\"response-btn-url inputbox geeky-popup-dynamic-field-input\" autocomplete=\"off\" placeholder=\"". esc_attr(__('Enter URL here','geeky-bot'))."\" style=\"display: none\"  /><span class=\"geeky-popup-dynamic-remov-image remove-btn\" title=\"". esc_attr(__('Delete','geeky-bot'))."\" onClick=\"deleteResponseTextBotton(div_'+responseButtonDivId+')\">". esc_html(__('Delete','geeky-bot')) ."</span></div>');
        responseButtonDivId++;
    }
    function deleteResponseTextBotton(responseButtonId){
        jQuery(responseButtonId).remove();
    }
    // Function to create fallback buttons
    var fallbackButtonDivId = 1;
    function addFallBackButton(param) {
        if (fallbackButtonDivId != param && fallbackButtonDivId < param) {
            fallbackButtonDivId = param;
        }
        // console.log(userInputDivId);
        var container = jQuery('#fallback-popup-text');
        container.append('<div class=\"geeky-popup-dynamic-field\" id=\"div_'+fallbackButtonDivId+'\"><input name = \"fallback_btn_text[]\" type=\"text\" value = \"\" class=\"inputbox geeky-popup-dynamic-field-input\" autocomplete=\"off\" placeholder=\"". esc_attr(__('Button text here','geeky-bot'))."\" /><select name=\"fallback_btn_type[]\" class=\"response-btn-type inputbox geeky-popup-dynamic-field-input geeky-popup-dynamic-field-select\" data-validation=\"required\"><option value=\"1\">". esc_attr(__('User Input','geeky-bot'))."</option><option value=\"2\">". esc_attr(__('URL','geeky-bot'))."</option></select><input name = \"fallback_btn_value[]\" type=\"text\" value = \"\" class=\"response-btn-value inputbox geeky-popup-dynamic-field-input\" autocomplete=\"off\" placeholder=\"". esc_attr(__('Button value here','geeky-bot'))."\" /><input name = \"fallback_btn_url[]\" type=\"text\" value = \"\" class=\"response-btn-url inputbox geeky-popup-dynamic-field-input\" autocomplete=\"off\" placeholder=\"". esc_attr(__('Enter URL here','geeky-bot'))."\" style=\"display: none\"  /><span class=\"geeky-popup-dynamic-remov-image remove-btn\" title=\"". esc_attr(__('Delete','geeky-bot'))."\" onClick=\"deleteFallbackBotton(div_'+fallbackButtonDivId+')\">". esc_html(__('Delete','geeky-bot')) ."</span></div>');
        fallbackButtonDivId++;
    }
    function deleteFallbackBotton(fallbackButtonId){
        jQuery(fallbackButtonId).remove();
    }
    // Function to create intent fallback buttons
    var intentFallbackButtonDivId = 1;
    function addIntentFallBackButton(param) {
        if (intentFallbackButtonDivId != param && intentFallbackButtonDivId < param) {
            intentFallbackButtonDivId = param;
        }
        // console.log(userInputDivId);
        var container = jQuery('#intent-fallback-popup-text');
        container.append('<div class=\"geeky-popup-dynamic-field\" id=\"div_'+intentFallbackButtonDivId+'\"><input name = \"fallback_btn_text[]\" type=\"text\" value = \"\" class=\"inputbox geeky-popup-dynamic-field-input\" autocomplete=\"off\" placeholder=\"". esc_attr(__('Button text here','geeky-bot'))."\" /><select name=\"fallback_btn_type[]\" class=\"response-btn-type inputbox geeky-popup-dynamic-field-input geeky-popup-dynamic-field-select\" data-validation=\"required\"><option value=\"1\">". esc_attr(__('User Input','geeky-bot'))."</option><option value=\"2\">". esc_attr(__('URL','geeky-bot'))."</option></select><input name = \"fallback_btn_value[]\" type=\"text\" value = \"\" class=\"response-btn-value inputbox geeky-popup-dynamic-field-input\" autocomplete=\"off\" placeholder=\"". esc_attr(__('Button value here','geeky-bot'))."\" /><input name = \"fallback_btn_url[]\" type=\"text\" value = \"\" class=\"response-btn-url inputbox geeky-popup-dynamic-field-input\" autocomplete=\"off\" placeholder=\"". esc_attr(__('Enter URL here','geeky-bot'))."\" style=\"display: none\"  /><span class=\"geeky-popup-dynamic-remov-image remove-btn\" title=\"". esc_attr(__('Delete','geeky-bot'))."\" onClick=\"deleteIntentFallbackBotton(div_'+intentFallbackButtonDivId+')\">". esc_html(__('Delete','geeky-bot')) ."</span></div>');
        intentFallbackButtonDivId++;
    }
    function deleteIntentFallbackBotton(intentFallbackButtonId){
        jQuery(intentFallbackButtonId).remove();
    }
    // Function to create custome actions
    function addCustomeActions() {
        jQuery('div#response-add-action-popup').slideDown('slow');
    }
    // Function to create custom Action parameters
    var actionParameterDivId = 1;
    function addActionParameter() {
        var container = jQuery('#user-action-inputs');
        container.append('<div class=\"geeky-popup-dynamic-field geeky-popup-cutom-form-dynamic-field\" id=\"div_'+actionParameterDivId+'\"><input name = \"paramname[]\" type=\"text\" value = \"\" class=\"inputbox geeky-popup-dynamic-field-input\" autocomplete=\"off\" placeholder=\"". esc_attr(__('Parameter Name *','geeky-bot')) ."\" /><div class=\"geekybot-custom-form-dropdown\">". wp_kses(GEEKYBOTformfield::GEEKYBOT_select('paramtype[]', $paramlist, isset($paramtype) ? $paramtype : '', null, array('class' => 'inputbox geekybot-form-select-field')), GEEKYBOT_ALLOWED_TAGS)."</div><span class=\"geeky-popup-dynamic-remov-image remove-btn\" onClick=\"deleteActionParameter(div_'+actionParameterDivId+')\"><img title=\"". esc_html(__('Delete','geeky-bot')) ."\" alt=\"".  esc_html(__('Close','geeky-bot')) ."\" class=\"userpopup-close\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/close.png\" /></span>');
        actionParameterDivId++;
    }
    function deleteActionParameter(actionParameterId){
        jQuery(actionParameterId).remove();
    }
    // Function to create new form
    function addNewForms() {
        jQuery('div.geekybot_story_right_popup_inner_wrp').slideUp('slow');
        jQuery('div#response-add-form-popup').slideDown('slow');
    }
    // Function to create custom form variable
    var formVariableDivId = 1;
    function addFormVariable() {";
        if (isset(geekybot::$_data[0]->possible_values)) {
            $possible_values = geekybot::GEEKYBOT_getVarValue(geekybot::$_data[0]->possible_values);
        } else {
            $possible_values = '';
        }
        $geekybot_js .="
        var container = jQuery('#user-form-inputs');
        container.append('<div class=\"geeky-popup-dynamic-field geeky-popup-cutom-form-dynamic-field\" id=\"div_'+formVariableDivId+'\"><input name = \"variable_name[]\" type=\"text\" value = \"\" class=\"typeAndSelect inputbox geeky-popup-dynamic-field-input variableName\" autocomplete=\"off\" placeholder=\"". esc_attr(__('Varibale Name *','geeky-bot')) ."\" /><div class=\"geekybot-custom-form-dropdown\">". wp_kses(GEEKYBOTformfield::GEEKYBOT_select('variable_type[]', $typelist, isset($typelist) ? $typelist : '', null, array('class' => 'inputbox geekybot-form-select-field variableType')), GEEKYBOT_ALLOWED_TAGS) ."</div><input name = \"variable_possible_values[]\" type=\"text\" value = \"".$possible_values ."\" class=\"inputbox geeky-popup-dynamic-field-input variablePossibleValues\" placeholder=\"". esc_attr(__('Add Comma Seprated Values','geeky-bot')) ."\" /><span class=\"geeky-popup-dynamic-remov-image remove-btn\" onClick=\"deleteFormVariable(div_'+formVariableDivId+')\"><img title=\"". esc_html(__('Delete','geeky-bot')) ."\" alt=\"". esc_html(__('Close','geeky-bot')) ."\" class=\"userpopup-close\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL)."includes/images/close.png\" /></span>');
        formVariableDivId++;
    }
    function deleteFormVariable(formVariableId){
        jQuery(formVariableId).remove();
    }

    jQuery(document).ready(function (jQuery) {
        // save story
        jQuery(document).on('click', '#Save-Story', function() {
            updateStory();
            jQuery('form#stories_form').submit();
        });
        
        function storeActiveNodeValue(data){
            var category = jQuery('.possible_node.active_node').attr('data-category');
            jQuery('.possible_node.active_node').val(category + 'id_' + data);
            var nid = jQuery('.possible_node.active_node').attr('data-nodeid');
            var nvalue = jQuery('.possible_node.active_node').val();
            var currentNode = positions.find(n => n.id === nid);
            currentNode.value = nvalue;

            // hamza ali
            // updateStory();
        }

        function updateStory(){
            geekybotShowLoading();
            var formData = jQuery('form#stories_form').serializeArray();
            var storyid = jQuery('input#storyid').val();
            var ids = [];
            jQuery(\"input[name='story[ids][]']\").each(function() {
                var value = jQuery(this).val();
                ids.push(value);
            });
            // update story using ajax
            var ajaxurl = '". esc_url(admin_url("admin-ajax.php")) ."';
            jQuery.post(ajaxurl, {
                action: 'geekybot_ajax',
                geekybotme: 'stories',
                task: 'updateStoryAjax',
                ids: ids,
                storyid: storyid,
                positionsarray: JSON.stringify(positions), // Explicitly serialize the positions array
                '_wpnonce':'". esc_attr(wp_create_nonce("save-story")) ."'
            }, function(data) {
                geekybotHideLoading();
                if (data) {
                    jQuery('#user-input-msg').html('<div class=\"geeky-bot-popop-save-success-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"". esc_html(__('Info','geeky-bot')) ."\" title=\"". esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL)."includes/images/story/info-green.png\" />". esc_attr(__("User input has been successfully saved.", 'geeky-bot'))."</div></div>');
                } else {
                    jQuery('#user-input-msg').html('<div class=\"geeky-bot-popop-save-success-msg geeky-error-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"". esc_html(__('Info','geeky-bot')) ."\" title=\"". esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-red.png\" />". esc_attr(__('Something went wrong try again later!', 'geeky-bot'))."</div></div>');
                }
            });
            // jQuery('form#stories_form').submit();
        }

        var story_id = ".$story_id."
        // save user input
        jQuery('form#userInputForm').submit(function (e) {
            e.preventDefault();
            var error = 0;
            var group_id = jQuery('input#group_id').val();
            var userMessages = [];
            jQuery(\"input[name='user_messages[]']\").each(function() {
                var value = jQuery(this).val();
                var valueId = jQuery(this).attr('data-id');
                var messageData = {
                    id: valueId,
                    message: value,
                };
                userMessages.push(messageData);
                if (value == '') {
                    error = 1;
                }
            });
            if (error == 1) {
                jQuery('#user-input-msg').html('<div class=\"geeky-bot-popop-save-success-msg geeky-error-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"". esc_html(__('Info','geeky-bot'))."\" title=\"". esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL)."includes/images/story/info-red.png\" />". esc_attr(__("An empty user input field.", 'geeky-bot')) ."</div></div>');
            } else {
                geekybotShowLoading();
                var ajaxurl =
                    '". esc_url(admin_url("admin-ajax.php"))."';
                jQuery.post(ajaxurl, {
                    action: 'geekybot_ajax',
                    geekybotme: 'intent',
                    task: 'saveUserInputAjax',
                    group_id: group_id,
                    user_messages: userMessages,
                    '_wpnonce':'". esc_attr(wp_create_nonce("save-intent")) ."'
                }, function(data) {
                    geekybotHideLoading();
                    if (data) {
                        data = jQuery.parseJSON(data);
                        jQuery('input#group_id').val(data);
                        storeActiveNodeValue(data);
                        jQuery('#user-input-msg').html('<div class=\"geeky-bot-popop-save-success-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"". esc_html(__('Info','geeky-bot')) ."\" title=\"". esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-green.png\" />". esc_attr(__("User input has been successfully saved.", 'geeky-bot')) ."</div></div>');
                    } else {
                        jQuery('#user-input-msg').html('<div class=\"geeky-bot-popop-save-success-msg geeky-error-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"". esc_html(__('Info','geeky-bot'))."\" title=\"". esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL)."includes/images/story/info-red.png\" />". esc_attr(__('Something went wrong try again later!', 'geeky-bot'))."</div></div>');
                    }
                });
            }
            clearNotifications();
        });
        // save text response
        jQuery('form#responseTextForm').submit(function (e) {
            e.preventDefault();
            var id = jQuery('input#id').val();
            var response_type_text = jQuery('input#response_type_text').val();
            var bot_response = jQuery('textarea#bot_response').val();
            var responseBtnText = [];
            jQuery(\"input[name='response_btn_text[]']\").each(function() {
                var value = jQuery(this).val();
                responseBtnText.push(value);
            });
            var responseBtnType = [];
            jQuery(\"select[name='response_btn_type[]']\").each(function() {
                var value = jQuery(this).val();
                responseBtnType.push(value);
            });
            var responseBtnValue = [];
            jQuery(\"input[name='response_btn_value[]']\").each(function() {
                var value = jQuery(this).val();
                responseBtnValue.push(value);
            });
            var responseBtnUrl = [];
            jQuery(\"input[name='response_btn_url[]']\").each(function() {
                var value = jQuery(this).val();
                responseBtnUrl.push(value);
            });
            if (bot_response == '') {
                jQuery('#response-text-msg').html('<div class=\"geeky-bot-popop-save-success-msg geeky-error-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"". esc_html(__('Info','geeky-bot')) ."\" title=\"". esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-red.png\" />". esc_attr(__('An empty bot response.', 'geeky-bot'))."</div></div>');
            } else {
                var ajaxurl =
                    '". esc_url(admin_url("admin-ajax.php")) ."';
                geekybotShowLoading();
                jQuery.post(ajaxurl, {
                    action: 'geekybot_ajax',
                    geekybotme: 'responses',
                    task: 'saveResponsesAjax',
                    id: id,
                    response_type: response_type_text,
                    bot_response: bot_response,
                    btn_text: responseBtnText,
                    btn_type: responseBtnType,
                    btn_value: responseBtnValue,
                    btn_url: responseBtnUrl,
                    '_wpnonce':'". esc_attr(wp_create_nonce("save-responses")) ."'
                }, function(data) {
                    geekybotHideLoading();
                    if (data) {
                        data = jQuery.parseJSON(data);
                        jQuery('div#responseTextFormBody input#id').val(data);
                        storeActiveNodeValue(data);
                        jQuery('#response-text-msg').html('<div class=\"geeky-bot-popop-save-success-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"". esc_html(__('Info','geeky-bot')) ."\" title=\"". esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-green.png\" />". esc_attr(__('Response Successfully Saved!', 'geeky-bot')) ."</div></div>');
                    } else {
                        jQuery('#response-text-msg').html('<div class=\"geeky-bot-popop-save-success-msg geeky-error-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"". esc_html(__('Info','geeky-bot')) ."\" title=\"". esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-red.png\" />". esc_attr(__('Something went wrong try again later!', 'geeky-bot')) ."</div></div>');
                    }
                });
            }
            clearNotifications();
        });
        // save function response
        jQuery('form#responseFunctionForm').submit(function (e) {
            e.preventDefault();
            geekybotShowLoading();
            var id = jQuery('input#id').val();
            var response_type_function = jQuery('input#response_type_function').val();
            var function_id = jQuery('select#function_id').val();
            var ajaxurl =
                '". esc_url(admin_url("admin-ajax.php")) ."';
            jQuery.post(ajaxurl, {
                action: 'geekybot_ajax',
                geekybotme: 'responses',
                task: 'saveResponsesAjax',
                id: id,
                response_type: response_type_function,
                function_id: function_id,
                '_wpnonce':'". esc_attr(wp_create_nonce("save-responses")) ."'
            }, function(data) {
                geekybotHideLoading();
                if (data) {
                    data = jQuery.parseJSON(data);
                    storeActiveNodeValue(data);
                    jQuery('#response-function-msg').html('<div class=\"geeky-bot-popop-save-success-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"". esc_html(__('Info','geeky-bot')) ."\" title=\"". esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-green.png\" />". esc_attr(__('Response Successfully Saved!', 'geeky-bot'))."</div></div>');
                } else {
                    jQuery('#response-function-msg').html('<div class=\"geeky-bot-popop-save-success-msg geeky-error-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"". esc_html(__('Info','geeky-bot')) ."\" title=\"". esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-red.png\" />". esc_attr(__('Something went wrong try again later!', 'geeky-bot')) ."</div></div>');
                }
                clearNotifications();
            });
        });
        
        // save action response
        jQuery('form#responseActionForm').submit(function (e) {
            e.preventDefault();
            geekybotShowLoading();
            var id = jQuery('input#id').val();
            var response_type_action = jQuery('input#response_type_action').val();
            var action_id = jQuery('select#action_id').val();
            var ajaxurl =
                '". esc_url(admin_url("admin-ajax.php"))."';
            jQuery.post(ajaxurl, {
                action: 'geekybot_ajax',
                geekybotme: 'responses',
                task: 'saveResponsesAjax',
                id: id,
                response_type: response_type_action,
                action_id: action_id,
                '_wpnonce':'". esc_attr(wp_create_nonce("save-responses")) ."'
            }, function(data) {
                geekybotHideLoading();
                if (data) {
                    data = jQuery.parseJSON(data);
                    storeActiveNodeValue(data);
                    jQuery('#response-action-msg').html('<div class=\"geeky-bot-popop-save-success-msg geeky-error-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"". esc_html(__('Info','geeky-bot')) ."\" title=\"". esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-red.png\" />". esc_attr(__('Response Successfully Saved!', 'geeky-bot'))."</div></div>');
                } else {
                    jQuery('#response-action-msg').html('<div class=\"geeky-bot-popop-save-success-msg geeky-error-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"". esc_html(__('Info','geeky-bot')) ."\" title=\"". esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-red.png\" />". esc_attr(__('Something went wrong try again later!', 'geeky-bot')) ."</div></div>');
                }
                clearNotifications();
            });
        });
        // add new custom action
        jQuery('form#responseAddActionForm').submit(function (e) {
            e.preventDefault();
            geekybotShowLoading();
            var functionName = jQuery('input#function_name').val();
            var paramName = [];
            jQuery(\"input[name='paramname[]']\").each(function() {
                var value = jQuery(this).val();
                paramName.push(value);
            });
            var paramType = [];
            jQuery(\"select[name='paramtype[]']\").each(function() {
                var value = jQuery(this).val();
                paramType.push(value);
            });
            var ajaxurl =
                '". esc_url(admin_url("admin-ajax.php")) ."';
            jQuery.post(ajaxurl, {
                action: 'geekybot_ajax',
                geekybotme: 'action',
                task: 'saveCustomeActionAjax',
                function_name: functionName,
                paramtype: paramType,
                paramname: paramName,
                '_wpnonce':'". esc_attr(wp_create_nonce("save-action")) ."'
            }, function(data) {
                geekybotHideLoading();
                if (data) {
                    jQuery('#response-add-action-msg').html('<div class=\"geeky-bot-popop-save-success-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"". esc_html(__('Info','geeky-bot')) ."\" title=\"". esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-green.png\" />". esc_attr(__('Action Successfully Saved!', 'geeky-bot')) ."</div></div>');
                    updateActionValueOnPopup();
                } else {
                    jQuery('#response-add-action-msg').html('<div class=\"geeky-bot-popop-save-success-msg geeky-error-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"".  esc_html(__('Info','geeky-bot')) ."\" title=\"". esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-red.png\" />". esc_attr(__('Something went wrong try again later!', 'geeky-bot')) ."</div></div>');
                }
                clearNotifications();
            });
        });
        // save form response
        jQuery('form#responseFormForm').submit(function (e) {
            e.preventDefault();
            geekybotShowLoading();
            var id = jQuery('input#id').val();
            var response_type_form = jQuery('input#response_type_form').val();
            var form_id = jQuery('select#form_id').val();
            var ajaxurl =
                '". esc_url(admin_url("admin-ajax.php")) ."';
            jQuery.post(ajaxurl, {
                action: 'geekybot_ajax',
                geekybotme: 'responses',
                task: 'saveResponsesAjax',
                id: id,
                response_type: response_type_form,
                form_id: form_id,
                '_wpnonce':'". esc_attr(wp_create_nonce("save-responses")) ."'
            }, function(data) {
                geekybotHideLoading();
                if (data) {
                    data = jQuery.parseJSON(data);
                    storeActiveNodeValue(data);
                    jQuery('#response-form-msg').html('<div class=\"geeky-bot-popop-save-success-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"". esc_html(__('Info','geeky-bot')) ."\" title=\"". esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-green.png\" />". esc_attr(__('Form Successfully Saved!', 'geeky-bot')) ."</div></div>');
                } else {
                    jQuery('#response-form-msg').html('<div class=\"geeky-bot-popop-save-success-msg geeky-error-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"".  esc_html(__('Info','geeky-bot')) ."\" title=\"".  esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-red.png\" />". esc_attr(__('Something went wrong try again later!', 'geeky-bot')) ."</div></div>');
                }
                clearNotifications();
            });
        });
        // add new custom form
        jQuery('form#responseAddForm').submit(function (e) {
            e.preventDefault();
            geekybotShowLoading();
            var custome_form_id = jQuery('input#custome_form_id').val();
            var form_name = jQuery('input#form_name').val();
            var variables = jQuery('input#variables').val();
            var variableName = [];
            var variableType = [];
            var variablePossibleValues = [];
            jQuery(\"input[name='variable_name[]']\").each(function() {
                var value = jQuery(this).val();
                variableName.push(value);
            });
            jQuery(\"select[name='variable_type[]']\").each(function() {
                var value = jQuery(this).val();
                variableType.push(value);
            });
            jQuery(\"input[name='variable_possible_values[]']\").each(function() {
                var value = jQuery(this).val();
                variablePossibleValues.push(value);
            });
            var ajaxurl =
                '". esc_url(admin_url("admin-ajax.php")) ."';
            jQuery.post(ajaxurl, {
                action: 'geekybot_ajax',
                geekybotme: 'forms',
                task: 'saveCustomeFormAjax',
                custome_form_id: custome_form_id,
                form_name: form_name,
                variables: variables,
                variable_name: variableName,
                variable_type: variableType,
                variable_possible_values: variablePossibleValues,
                '_wpnonce':'". esc_attr(wp_create_nonce("save-form")) ."'
            }, function(data) {
                geekybotHideLoading();
                if (data) {
                    jQuery('#custome_form_id').val(data);
                    jQuery('#response-add-form-msg').html('<div class=\"geeky-bot-popop-save-success-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"".  esc_html(__('Info','geeky-bot')) ."\" title=\"". esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-green.png\" />". esc_attr(__('Form Successfully Saved!', 'geeky-bot')) ."</div></div>');
                    updateFormsValue();
                } else {
                    jQuery('#response-add-form-msg').html('<div class=\"geeky-bot-popop-save-success-msg geeky-error-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"".  esc_html(__('Info','geeky-bot')) ."\" title=\"". esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL). "includes/images/story/info-red.png\" />". esc_attr(__('Something went wrong try again later!', 'geeky-bot')) ."</div></div>');
                }
                clearNotifications();
            });
        });
        // save default fallback form
        jQuery('form#defaultFallbackForm').submit(function (e) {
            e.preventDefault();
            geekybotShowLoading();
            var story_id = ". $story_id .";
            var default_fallback_text = jQuery('textarea#default_fallback_text').val();
            var fallbackBtnText = [];
            jQuery(\"input[name='fallback_btn_text[]']\").each(function() {
                var value = jQuery(this).val();
                fallbackBtnText.push(value);
            });
            var fallbackBtnType = [];
            jQuery(\"select[name='fallback_btn_type[]']\").each(function() {
                var value = jQuery(this).val();
                fallbackBtnType.push(value);
            });
            var fallbackBtnValue = [];
            jQuery(\"input[name='fallback_btn_value[]']\").each(function() {
                var value = jQuery(this).val();
                fallbackBtnValue.push(value);
            });
            var fallbackBtnUrl = [];
            jQuery(\"input[name='fallback_btn_url[]']\").each(function() {
                var value = jQuery(this).val();
                fallbackBtnUrl.push(value);
            });

            var ajaxurl =
                '". esc_url(admin_url("admin-ajax.php")) ."';
            jQuery.post(ajaxurl, {
                action: 'geekybot_ajax',
                geekybotme: 'stories',
                task: 'savedefaultFallbackFormAjax',
                story_id: story_id,
                default_fallback: default_fallback_text,
                btn_text: fallbackBtnText,
                btn_type: fallbackBtnType,
                btn_value: fallbackBtnValue,
                btn_url: fallbackBtnUrl,
                '_wpnonce':'". esc_attr(wp_create_nonce("save-default-fallback")) ."'
            }, function(data) {
                geekybotHideLoading();
                if (data == 1) {
                    jQuery('#default-fallback-msg').html('<div class=\"geeky-bot-popop-save-success-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"".  esc_html(__('Info','geeky-bot')) ."\" title=\"". esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-green.png\" />". esc_attr(__('Default Fallback Successfully Saved!', 'geeky-bot')) ."</div></div>');
                } else {
                    jQuery('#default-fallback-msg').html('<div class=\"geeky-bot-popop-save-success-msg geeky-error-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"". esc_html(__('Info','geeky-bot')) ."\" title=\"". esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-red.png\" />". esc_attr(__('Something went wrong try again later!', 'geeky-bot')) ."</div></div>');
                }
                clearNotifications();
            });
        });
        // save default intent fallback form
        jQuery('form#defaultIntentFallbackForm').submit(function (e) {
            e.preventDefault();
            geekybotShowLoading();
            var id = jQuery('#id').val();
            var group_id = jQuery('#group_id').val();
            var story_id = ". $story_id .";
            var default_intent_fallback_text = jQuery('textarea#default_intent_fallback_text').val();
            var fallbackBtnText = [];
            jQuery(\"input[name='fallback_btn_text[]']\").each(function() {
                var value = jQuery(this).val();
                fallbackBtnText.push(value);
            });
            var fallbackBtnType = [];
            jQuery(\"select[name='fallback_btn_type[]']\").each(function() {
                var value = jQuery(this).val();
                fallbackBtnType.push(value);
            });
            var fallbackBtnValue = [];
            jQuery(\"input[name='fallback_btn_value[]']\").each(function() {
                var value = jQuery(this).val();
                fallbackBtnValue.push(value);
            });
            var fallbackBtnUrl = [];
            jQuery(\"input[name='fallback_btn_url[]']\").each(function() {
                var value = jQuery(this).val();
                fallbackBtnUrl.push(value);
            });
            var ajaxurl =
                '". esc_url(admin_url("admin-ajax.php")) ."';
            jQuery.post(ajaxurl, {
                action: 'geekybot_ajax',
                geekybotme: 'intent',
                task: 'savedefaultIntentFallbackFormAjax',
                id: id,
                group_id: group_id,
                story_id: story_id,
                default_intent_fallback: default_intent_fallback_text,
                btn_text: fallbackBtnText,
                btn_type: fallbackBtnType,
                btn_value: fallbackBtnValue,
                btn_url: fallbackBtnUrl,
                '_wpnonce':'". esc_attr(wp_create_nonce("save-default-intent-fallback")) ."'
            }, function(data) {
                geekybotHideLoading();
                if (data == -1) {
                    jQuery('#default-intent-fallback-msg').html('<div class=\"geeky-bot-popop-save-success-msg geeky-error-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"". esc_html(__('Info','geeky-bot')) ."\" title=\"". esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-red.png\" />". esc_attr(__('Store relevant user input first, then fallback.', 'geeky-bot')) ."</div></div>');
                } else if (data == -2) {
                    jQuery('#default-intent-fallback-msg').html('<div class=\"geeky-bot-popop-save-success-msg geeky-error-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"". esc_html(__('Info','geeky-bot')) ."\" title=\"". esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-red.png\" />". esc_attr(__('An empty default fallback.', 'geeky-bot')) ."</div></div>');
                } else if (data) {
                    jQuery('form#defaultIntentFallbackForm input#id').val(data);
                    jQuery('#default-intent-fallback-msg').html('<div class=\"geeky-bot-popop-save-success-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"".  esc_html(__('Info','geeky-bot')) ."\" title=\"". esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-green.png\" />". esc_attr(__('Default Intent Fallback Successfully Saved!', 'geeky-bot')) ."</div></div>');
                } else {
                    jQuery('#default-intent-fallback-msg').html('<div class=\"geeky-bot-popop-save-success-msg geeky-error-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"". esc_html(__('Info','geeky-bot')) ."\" title=\"". esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-red.png\" />". esc_attr(__('Something went wrong try again later!', 'geeky-bot')) ."</div></div>');
                }
                clearNotifications();
            });
        });

        function updateFormsValue() {
            var ajaxurl =
                '". esc_url(admin_url("admin-ajax.php")) ."';
            geekybotShowLoading();
            jQuery.post(ajaxurl, {
                action: 'geekybot_ajax',
                geekybotme: 'forms',
                task: 'updateFormsValueFormAjax',
                form_ids: '". geekybot::$_data[0]['story']->form_ids ."',
                '_wpnonce':'". esc_attr(wp_create_nonce("update-forms-value")) ."'
            }, function(data) {
                geekybotHideLoading();
                if (data) {
                    jQuery('div.geekybot_story_form_wrp').html(geekybot_DecodeHTML(data));
                }else{
                    jQuery('div.geekybot_story_form_wrp').html(\"<div class='premade-no-rec'>No form found</div>\");
                }
            });
        }

        function updateActionValueOnPopup() {
            var ajaxurl =
                '". esc_url(admin_url("admin-ajax.php")) ."';
            geekybotShowLoading();
            jQuery.post(ajaxurl, {
                action: 'geekybot_ajax',
                geekybotme: 'action',
                task: 'updateActionValueOnPopupFormAjax'
            }, function(data) {
                geekybotHideLoading();
                if (data) {
                    jQuery('#visibleAction').html(geekybot_DecodeHTML(data));
                }else{
                    jQuery('#visibleAction').html(\"<div class='premade-no-rec'>No response found</div>\");
                }
            });
        }
    });
    function drawNodes(id){
        var canvas = document.getElementById('canvas');
        var ctx = canvas.getContext('2d');
        jQuery('#geekybot_container').text('');
        jQuery(document).ready(function (jQuery) {
            // scrol start
            var canvasContainer = document.getElementById('canvas_container');
            var isDragging = false;
            var isOverContainer = false; // Flag to track if mouse is over the container
            var prevX, prevY;
            var startX, startY;
            var scrollTimeout;
            function handleMouseMove(e) {
                if (isDragging) {
                    var deltaX = e.clientX - prevX;
                    var deltaY = e.clientY - prevY;
                    // Smoothly scroll the container
                    canvasContainer.scrollLeft -= deltaX;
                    canvasContainer.scrollTop -= deltaY;
                    prevX = e.clientX;
                    prevY = e.clientY;
                }
                // isDragging = false;
            }
            window.addEventListener('mousedown', function(e) {
                startX = e.clientX;
                startY = e.clientY;
            });
            canvas.addEventListener('mousedown', function(e) {
                isDragging = true;
                prevX = e.clientX;
                prevY = e.clientY;
                canvasContainer.style.cursor = 'grabbing'; // Change cursor style when dragging
            });
            window.addEventListener('mouseup', function(e) {
                // if (isDragging) {
                if (startX !== undefined && startY !== undefined) {
                    var endX = e.clientX;
                    var endY = e.clientY;
                    // Calculate distance moved
                    var distance = Math.sqrt(Math.pow(endX - startX, 2) + Math.pow(endY - startY, 2));
                    // If distance is below a threshold, treat it as a click
                    if (distance < 5) { // Adjust threshold as needed
                        var clickedElement = e.target;
                        if (clickedElement.tagName === 'DIV' || clickedElement.tagName === 'IMG' || clickedElement.tagName === 'SPAN') {
                            var parentDiv = clickedElement.tagName === 'DIV' ? clickedElement.parentElement : clickedElement.parentElement.parentElement;
                            var mak_node_active = 0;
                            var node_type = 'user_input';
                            // click on delete node
                            if (clickedElement.tagName === 'IMG' && clickedElement.classList.contains('geekybot_node_remove')) {
                                mak_node_active = 1;
                            } else if (parentDiv.classList.contains('node_action_user_input')) {
                                jQuery('div#userinput-popup').slideDown('slow');
                                jQuery('div.geekybot_story_right_popup_inner_wrp').slideUp('slow');
                                jQuery('div.geekybot-avlble-varpopup').slideUp('slow');
                                jQuery('div#response-text-popup').slideUp('slow');
                                jQuery('div#response-function-popup').slideUp('slow');
                                jQuery('div#response-action-popup').slideUp('slow');
                                jQuery('div#default-fallback-popup').slideUp('slow');
                                jQuery('div#default-intent-fallback-popup').slideUp('slow');
                                jQuery('div#response-form-popup').slideUp('slow');
                                mak_node_active = 1;
                                node_type = 'user_input';
                            } else if (parentDiv.classList.contains('node_action_text')) {
                                jQuery('div#response-text-popup').slideDown('slow');
                                jQuery('div.geekybot_story_right_popup_inner_wrp,div.geekybot-avlble-varpopup , div#userinput-popup, div#response-function-popup, div#response-action-popup, div#default-fallback-popup, div#response-form-popup, div#default-intent-fallback-popup').slideUp('slow');
                                mak_node_active = 1;
                                node_type = 'response_text';
                            } else if (parentDiv.classList.contains('node_action_function')) {
                                jQuery('div#response-function-popup').slideDown('slow');
                                jQuery('div.geekybot_story_right_popup_inner_wrp,div.geekybot-avlble-varpopup , div#userinput-popup, div#response-text-popup, div#response-action-popup, div#default-fallback-popup, div#response-form-popup, div#default-intent-fallback-popup').slideUp('slow');
                                mak_node_active = 1;
                                node_type = 'response_function';
                            }  else if (parentDiv.classList.contains('node_action_action')) {
                                jQuery('div#response-action-popup').slideDown('slow');
                                jQuery('div.geekybot_story_right_popup_inner_wrp,div.geekybot-avlble-varpopup , div#userinput-popup, div#response-text-popup, div#response-function-popup, div#default-fallback-popup, div#response-form-popup, div#default-intent-fallback-popup').slideUp('slow');
                                mak_node_active = 1;
                                node_type = 'response_action';
                            } else if (parentDiv.classList.contains('node_action_form')) {
                                jQuery('div#response-form-popup').slideDown('slow');
                                jQuery('div.geekybot_story_right_popup_inner_wrp,div.geekybot-avlble-varpopup , div#userinput-popup, div#response-text-popup, div#response-function-popup, div#response-action-popup, div#default-fallback-popup, div#default-intent-fallback-popup').slideUp('slow');
                                mak_node_active = 1;
                                node_type = 'response_form';
                            } else if (parentDiv.classList.contains('node_action_fallback')) {
                                removeFormHTLM();
                                jQuery('div#defaultFallbackFormBody').append('<img id=\"geekybot-loading-icon\" src=\"".GEEKYBOT_PLUGIN_URL ."includes/images/story/story_load.gif\" />');
                                jQuery('div#default-fallback-popup').slideDown('slow');
                                jQuery('div.geekybot_story_right_popup_inner_wrp,div.geekybot-avlble-varpopup , div#userinput-popup, div#response-text-popup,div#response-function-popup, div#response-action-popup, div#response-form-popup, div#default-intent-fallback-popup').slideUp('slow');
                                mak_node_active = 1;
                                node_type = 'fallback';
                                //
                                var ajaxurl = '". esc_url(admin_url("admin-ajax.php")) ."';
                                var storyId = ". $story_id .";
                                jQuery.post(ajaxurl, {
                                    action: 'geekybot_ajax',
                                    geekybotme: 'stories',
                                    task: 'getDefaultFallbackFormBodyHTMLAjax',
                                    storyId: storyId,
                                    '_wpnonce':'". esc_attr(wp_create_nonce("get-form-html")) ."'
                                }, function(data) {
                                    jQuery('div#defaultFallbackFormBody').find('img#geekybot-loading-icon').remove();
                                    if (data) {
                                        jQuery('div#defaultIntentFallbackFormBody').html('');
                                        jQuery('div#defaultFallbackFormBody').html(geekybot_DecodeHTML(data));
                                    } else {
                                        jQuery('div#defaultFallbackFormBody').html('<span class=\"geekybot_error_msg\">". esc_attr(__('Something went wrong try again later!', 'geeky-bot')) ."</span>');
                                    }
                                });
                            } else if (parentDiv.classList.contains('node_action_intent_fallback')) {
                                var groupId = '';
                                var intentNodeId = parentDiv.id;
                                var intentNodeParentId = jQuery('#'+intentNodeId+' .box').attr('data-parentid');
                                if(intentNodeParentId) {
                                    var intentNodeValue = jQuery('#' + intentNodeParentId + ' .possible_node').val();
                                    if(intentNodeValue){
                                        intentNodeValue = intentNodeValue.replace('intentid_', '');
                                        if(intentNodeValue) {
                                            groupId = intentNodeValue;
                                        }
                                    }
                                }
                                jQuery('div.geekybot_story_right_popup_inner_wrp,div.geekybot-avlble-varpopup , div#userinput-popup, div#response-text-popup,div#response-function-popup, div#response-action-popup, div#response-form-popup, div#default-fallback-popup').slideUp('slow');
                                jQuery('div#default-intent-fallback-popup').slideDown('slow');
                                mak_node_active = 1;
                                node_type = 'intent_fallback';
                            }
                            if(mak_node_active == 1) {
                                // Find the input field within the parentDiv
                                var jQueryParentDiv = jQuery(parentDiv);
                                var inputField = jQueryParentDiv.find('input[type=\"hidden\"]');
                                // Access and manipulate the input field (if it exists)
                                if (inputField.length > 0) {
                                    // Do something with the input field
                                    jQuery('input[type=\"hidden\"]').removeClass('active_node');
                                    jQuery('div.geekybot_story_leftaction_wrp').removeClass('active_node_parent');
                                    inputField.addClass('active_node'); // Example: Set a new value
                                    inputField.closest('.geekybot_story_leftaction_wrp').addClass('active_node_parent');
                                    // bind data in case of update
                                    var input_value = inputField.val();
                                    if (input_value !== undefined || input_value !== \"\") {
                                        var idNumber = parseInt(input_value.split('_')[1]);
                                        // get values using ajax
                                        var ajaxurl = '". esc_url(admin_url("admin-ajax.php")) ."';
                                        if (node_type == 'user_input') {
                                            removeFormHTLM();
                                            jQuery('div#userInputFormBody').append('<img id=\"geekybot-loading-icon\" src=\"".GEEKYBOT_PLUGIN_URL ."includes/images/story/story_load.gif\" />');
                                            jQuery.post(ajaxurl, {
                                                action: 'geekybot_ajax',
                                                geekybotme: 'stories',
                                                task: 'getUserInputFormBodyHTMLAjax',
                                                id: idNumber,
                                                '_wpnonce':'". esc_attr(wp_create_nonce("get-form-html")) ."'
                                            }, function(data) {
                                                jQuery('div#userInputFormBody').find('img#geekybot-loading-icon').remove();
                                                if (data) {
                                                    jQuery('div#userInputFormBody').html(geekybot_DecodeHTML(data));
                                                } else {
                                                    jQuery('div#userInputFormBody').html('<span class=\"geekybot_error_msg\">". esc_attr(__('Something went wrong try again later!', 'geeky-bot')) ."</span>');
                                                }
                                            });
                                        } else if (node_type == 'response_text') {
                                            removeFormHTLM();
                                            jQuery('div#responseTextFormBody').append('<img id=\"geekybot-loading-icon\" src=\"".GEEKYBOT_PLUGIN_URL ."includes/images/story/story_load.gif\" />');
                                            jQuery.post(ajaxurl, {
                                                action: 'geekybot_ajax',
                                                geekybotme: 'stories',
                                                task: 'getResponseTextFormBodyHTMLAjax',
                                                id: idNumber,
                                                '_wpnonce':'". esc_attr(wp_create_nonce("get-form-html")) ."'
                                            }, function(data) {
                                                jQuery('div#responseTextFormBody').find('img#geekybot-loading-icon').remove();
                                                if (data) {
                                                    jQuery('div#responseTextFormBody').html(geekybot_DecodeHTML(data));
                                                } else {
                                                    jQuery('div#responseTextFormBody').html('<span class=\"geekybot_error_msg\">". esc_attr(__('Something went wrong try again later!', 'geeky-bot')) ."</span>');
                                                }
                                            });
                                        } else if (node_type == 'response_function') {
                                            removeFormHTLM();
                                            jQuery('div#responseFunctionFormBody').append('<img id=\"geekybot-loading-icon\" src=\"".GEEKYBOT_PLUGIN_URL ."includes/images/story/story_load.gif\" />');
                                            jQuery.post(ajaxurl, {
                                                action: 'geekybot_ajax',
                                                geekybotme: 'stories',
                                                task: 'getResponseFunctionFormBodyHTMLAjax',
                                                id: idNumber,
                                                story_type: ".esc_attr(geekybot::$_data[0]['story']->story_type).",
                                                '_wpnonce':'". esc_attr(wp_create_nonce("get-form-html")) ."'
                                            }, function(data) {
                                                jQuery('div#responseFunctionFormBody').find('img#geekybot-loading-icon').remove();
                                                if (data) {
                                                    jQuery('div#responseFunctionFormBody').html(geekybot_DecodeHTML(data));
                                                } else {
                                                    jQuery('div#responseFunctionFormBody').html('<span class=\"geekybot_error_msg\">". esc_attr(__('Something went wrong try again later!', 'geeky-bot')) ."</span>');
                                                }
                                            });
                                        }  else if (node_type == 'response_action') {
                                            removeFormHTLM();
                                            jQuery('div#responseActionFormBody').append('<img id=\"geekybot-loading-icon\" src=\"".GEEKYBOT_PLUGIN_URL ."includes/images/story/story_load.gif\" />');
                                            jQuery.post(ajaxurl, {
                                                action: 'geekybot_ajax',
                                                geekybotme: 'stories',
                                                task: 'getResponseActionFormBodyHTMLAjax',
                                                id: idNumber,
                                                '_wpnonce':'". esc_attr(wp_create_nonce("get-form-html")) ."'
                                            }, function(data) {
                                                jQuery('div#responseActionFormBody').find('img#geekybot-loading-icon').remove();
                                                if (data) {
                                                    jQuery('div#responseActionFormBody').html(geekybot_DecodeHTML(data));
                                                } else {
                                                    jQuery('div#responseActionFormBody').html('<span class=\"geekybot_error_msg\">". esc_attr(__('Something went wrong try again later!', 'geeky-bot')) ."</span>');
                                                }
                                            });
                                        } else if (node_type == 'response_form') {
                                            removeFormHTLM();
                                            jQuery('div#responseFormFormBody').append('<img id=\"geekybot-loading-icon\" src=\"".GEEKYBOT_PLUGIN_URL ."includes/images/story/story_load.gif\" />');
                                            jQuery.post(ajaxurl, {
                                                action: 'geekybot_ajax',
                                                geekybotme: 'stories',
                                                task: 'getResponseFormFormBodyHTMLAjax',
                                                id: idNumber,
                                                '_wpnonce':'". esc_attr(wp_create_nonce("get-form-html")) ."'
                                            }, function(data) {
                                                jQuery('div#responseFormFormBody').find('img#geekybot-loading-icon').remove();
                                                if (data) {
                                                    jQuery('div#responseFormFormBody').html(geekybot_DecodeHTML(data));
                                                } else {
                                                    jQuery('div#responseFormFormBody').html('<span class=\"geekybot_error_msg\">". esc_attr(__('Something went wrong try again later!', 'geeky-bot')) ."</span>');
                                                }
                                            });
                                        } else if (node_type == 'intent_fallback') {
                                            //
                                            removeFormHTLM();
                                            var ajaxurl = '". esc_url(admin_url("admin-ajax.php")) ."';
                                            jQuery('div#defaultIntentFallbackFormBody').append('<img id=\"geekybot-loading-icon\" src=\"".GEEKYBOT_PLUGIN_URL ."includes/images/story/story_load.gif\" />');
                                            jQuery.post(ajaxurl, {
                                                action: 'geekybot_ajax',
                                                geekybotme: 'stories',
                                                task: 'getDefaultIntentFallbackFormBodyHTMLAjax',
                                                groupId: groupId,
                                                storyId: ". $story_id .",
                                                '_wpnonce':'". esc_attr(wp_create_nonce("get-form-html")) ."'
                                            }, function(data) {
                                                jQuery('div#defaultIntentFallbackFormBody').find('img#geekybot-loading-icon').remove();
                                                if (data) {
                                                    jQuery('div#defaultFallbackFormBody').html('');
                                                    jQuery('div#defaultIntentFallbackFormBody').html(geekybot_DecodeHTML(data));
                                                } else {
                                                    jQuery('div#defaultIntentFallbackFormBody').html('<span class=\"geekybot_error_msg\">". esc_attr(__('Something went wrong try again later!', 'geeky-bot')) ."</span>');
                                                }
                                            });
                                        }
                                    }
                                } else {
                                    jQuery('input[type=\"hidden\"]').removeClass('active_node');
                                    jQuery('div.geekybot_story_leftaction_wrp').removeClass('active_node_parent');
                                }
                            }
                        }
                    }
                    // Reset start coordinates
                    startX = undefined;
                    startY = undefined;
                }
                isDragging = false;
                canvasContainer.style.cursor = 'grab'; // Restore cursor style when dragging ends
            });
            window.addEventListener('mousemove', function(e) {
                // Throttle the scroll event handling
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(function() {
                    handleMouseMove(e);
                }, 15);
            });
            if (canvas.width > 1200) {
                // Add event listener for mouse wheel scroll
                canvasContainer.addEventListener('wheel', function(e) {
                    if (isOverContainer) {
                        // canvasContainer.scrollLeft += e.deltaY;
                        // Smoothly scroll the container
                        canvasContainer.scrollTo({
                            left: canvasContainer.scrollLeft + e.deltaY,
                            behavior: 'smooth'
                        });
                        e.preventDefault(); // Prevent default scrolling behavior
                    }
                });
                // Add event listeners to toggle isOverContainer flag
                canvasContainer.addEventListener('mouseenter', function() {
                    isOverContainer = true;
                });
                canvasContainer.addEventListener('mouseleave', function() {
                    isOverContainer = false;
                });
            }
            // scrol end
            jQuery('.node.big-box').draggable({
                containment: '#canvas',
                stop: function(event, ui) {
                    var nodeId = jQuery(this).attr('id');
                    var nodeIndex = find_node_index(positions, nodeId)
                    var droppedAt = ui.offset;
                    // Calculate the coordinates relative to #container
                    var droppedAtRelative = {
                        left: ui.position.left,
                        top: ui.position.top
                    };
                    positions[nodeIndex].top = droppedAtRelative.top;
                    // to limit the min distance from the left
                    var parentNodeId = positions.find(n => n.id === positions[nodeIndex].parentId);
                    if(parentNodeId.type == 'user_input'){
                        var minLeftDistance = parentNodeId.left + 110;
                    } else {
                        var minLeftDistance = parentNodeId.left + 200;
                    }
                    if (droppedAtRelative.left <= minLeftDistance) {
                        positions[nodeIndex].left = minLeftDistance;
                    } else {
                        positions[nodeIndex].left = droppedAtRelative.left;
                    }
                    // clear the canvas
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    positions.forEach(function(node, index) {
                        if (node.parentId) {
                            var parentNode = positions.find(n => n.id === node.parentId);
                            curvedLine(jQuery('#' + parentNode.id)[0], jQuery('#' + node.id)[0], index);
                        }
                    });
                    drawNodes(id);
                }
            });
            // show remove node
            // Retrieve tooltip text and position via AJAX
            jQuery('.geekybot_story_leftaction_wrp').each(function() {
                var tooltipVisible = false; // Flag to track tooltip visibility
                var ajaxRequest; // Variable to hold the AJAX request reference
                jQuery(this).hover(
                function() {
                    // Mouse enter
                    var pDiv = jQuery(this).closest('.node.big-box');
                    tooltipVisible = true; // Set the flag to true when mouse enters
                    if (!pDiv.hasClass('node_start_point')) {
                        jQuery(this).find('.geekybot_node_remove').show();
                    }
                    // Retrieve tooltip text and position via AJAX
                    var inputField = pDiv.find('input[type=\"hidden\"]');
                    var inputFieldVal = inputField.val();
                    var inputFieldCat = inputField.attr('data-category');

                    var ajaxurl = '". esc_url(admin_url('admin-ajax.php')) ."';
                    // Abort any previous unfinished AJAX request
                    if (ajaxRequest) {
                        ajaxRequest.abort();
                    }
                    ajaxRequest = jQuery.post(ajaxurl, {
                        action: 'geekybot_ajax',
                        geekybotme: 'stories',
                        task: 'getTextForTooltip',
                        value: inputFieldVal,
                        category: inputFieldCat,
                        '_wpnonce':'". esc_attr(wp_create_nonce('get-tooltip-text')) ."'
                    }, function(data) {
                        if (data && tooltipVisible) {
                            // Check if tooltip should still be visible
                            // Show tooltip
                            pDiv.addClass('geekybot_custom_tooltip_hover');
                            // pDiv.find('.geekybot_tooltip_text').text(data);
                            var tooltip = pDiv.find('.geekybot_tooltip_text');
                            tooltip.text(data).fadeIn('fast');
                            // Determine position based on element's current position
                            var topValue = pDiv.css('top');
                            var elementOffset = parseInt(topValue, 10);

                            if(elementOffset < 700 ) {
                                // Position tooltip at the bottom if element is too high on the page
                                tooltip.css({
                                    bottom: 'auto',
                                    top: '65px',
                                });
                            } else {
                                // Position tooltip at the top if there's space
                                tooltip.css({
                                    top: 'auto',
                                    bottom: '65px',
                                });
                            }
                            // Horizontally center the tooltip
                            tooltip.css({
                                left: '50%',
                                right: 'auto',
                                transform: 'translateX(-50%)'
                            });
                        }
                    });
                }, 
                function() {
                    // Mouse leave
                    jQuery(this).find('.geekybot_node_remove').hide();
                    jQuery('.geekybot_custom_tooltip').removeClass('geekybot_custom_tooltip_hover');
                    // If AJAX request is still running, abort it
                    if (ajaxRequest) {
                        ajaxRequest.abort();
                    }
                });
            });
            // show remove node
            jQuery('img.geekybot_node_remove').click(function (e) {
                e.preventDefault();
                e.stopPropagation();
                var removeParentDiv = jQuery(this).closest('.geekybot_story_leftaction_wrp');
                var removeNodeId = jQuery(removeParentDiv).find('.possible_node.active_node').attr('data-nodeid');
                var removeNodeIndex = find_node_index(positions, removeNodeId);
                if (removeNodeIndex !== -1) {
                    if (jQuery(this).hasClass('geekybot_node_remove_intent_fb')) {
                        var fb_parent_id = jQuery('#'+removeNodeId+' .box').attr('data-parentid');
                        if(fb_parent_id == 'node1') {
                            deleteDefaultFallback();
                        } else {
                            var fb_parent_value = jQuery('#' + fb_parent_id + ' .possible_node').val();
                            var fb_group_id = '';
                            if(fb_parent_value){
                                fb_parent_value = fb_parent_value.replace('intentid_', '');
                                if(fb_parent_value) {
                                    fb_group_id = fb_parent_value;
                                    deleteIntentFallback(fb_group_id);
                                }
                            }
                        }
                    }
                    // find where parend is current id
                    var removeNodeIdChild = positions.find(n => n.parentId === removeNodeId && n.type != 'fallback');
                    if(removeNodeIdChild) {
                        var removeNodeIdChildID = removeNodeIdChild.id;
                        var removeNodeIdChildIDIndex = find_node_index(positions, removeNodeIdChildID);
                        // change parentId of child node with the parent of the node that is going to be removed
                        positions[removeNodeIdChildIDIndex].parentId = positions[removeNodeIndex].parentId;
                    }
                    if (positions[removeNodeIndex].type != 'fallback') {
                        for (var index = positions.length - 1; index >= 0; index--) {
                            var node = positions[index];
                            if (index > removeNodeIndex && positions[index].type != 'fallback') {
                                var pindex = index - 1;
                                if (positions[pindex].type == 'fallback') {
                                    var parentOfFB = positions[pindex].parentId;
                                    if(parentOfFB){
                                        var parentOfFBNode = positions.find(n => n.id === parentOfFB);
                                        if(parentOfFBNode && pindex < positions.length) {
                                            positions[pindex].left = parentOfFBNode.left + 40;
                                        }
                                        console.log('positions[pindex]');
                                    }
                                    pindex = index - 2;
                                }
                                if(pindex < positions.length) {
                                    var expectedTop = positions[pindex].top;
                                    var expectedLeft = positions[pindex].left;
                                    positions[index].top = expectedTop;
                                    positions[index].left = expectedLeft;
                                }
                            }
                        }
                    }
                    positions.splice(removeNodeIndex, 1);
                    // find fallback and then remove
                    var fallBackNode = positions.find(n => n.type === 'fallback' && n.parentId === removeNodeId);
                    if(fallBackNode){
                        var removeFBNodeIndex = find_node_index(positions, fallBackNode.id);
                        if (removeFBNodeIndex !== -1) {
                            positions.splice(removeFBNodeIndex, 1);
                        }
                    }
                    // close action popup if open
                    jQuery('div.geekybot_story_right_popup_inner_wrp').slideDown('slow');
                    jQuery('div.geekybot-avlble-varpopup').slideUp('slow');
                    jQuery('div#userinput-popup').slideUp('slow');
                    jQuery('div#response-text-popup').slideUp('slow');
                    jQuery('div#response-function-popup').slideUp('slow');
                    jQuery('div#default-fallback-popup').slideUp('slow');
                    jQuery('div#default-intent-fallback-popup').slideUp('slow');
                    // redraw the nodes
                    setCanvasWidthHeight();
                    var newIdCounter = id;
                    --newIdCounter;
                    drawNodes(newIdCounter);
                }
            });
        });
        
        // Draw positions
        var filteredpositions = positions.filter(obj => !obj.id.startsWith('fallback_'));
        if(positions[positions.length - 1].type == 'fallback') {
            var lastNode = positions.length - 2;
        } else {
            var lastNode = positions.length - 1;
        }
        positions.forEach(function(node, index) {
            var node_id = node.id;
            var parent_node_id = node.parentId;
            var story_id = ". $story_id .";
            var nodeText = '<span>'+node.text+'</span>';
            var nodeCategory = node.category;
            if (node.value !== undefined || node.value !== \"\") {
                var nodeValue = node.value;
            } else {
                var nodeValue = '';
            }
            if (node.type == 'user_input') {
                nodeText = '';
            }
            if (node.type != 'fallback') {
                if (index === lastNode && node.type != 'fallback') {
                    jQuery('#geekybot_container').append('<div id=\"' + node_id + '\" style=\"top: '+node.top+'px; left: '+node.left+'px;\" class=\"'+node.class+' node big-box geekybot_custom_tooltip \"><div id=\"end_point\" class=\"box bg-black geekybot_node_body geekybot_story_leftaction_wrp 01\" data-parentid=\"'+node_id+'\" data-parenttype=\"'+node.type+'\" data-left=\"'+node.left+'\" data-top=\"'+node.top+'\" data-storyid=\"'+story_id+'\"><input class=\"possible_node\" type=\"hidden\" name=\"story[ids][]\" value=\"'+nodeValue+'\" required=\"\" data-category=\"'+nodeCategory+'\" data-nodeid=\"' + node_id + '\"><img src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/'+node.image+'.png\">'+nodeText+'<div class=\"drop_zone_grand_paraent_wrp\" ondrop=\"drop(event)\" ondragover=\"allowDrop(event)\"><div class=\"drop_zone_paraent_wrp\"><i class=\"fa fa-plus\"></i></div></div><img class=\"geekybot_node_remove\" title = \"". esc_attr(__('Delete', 'geeky-bot'))."\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/cross.png\" ><div class=\"active_zone_wrp\"></div><span class=\"geekybot_tooltip_text\"></span></div></div>');
                } else {
                    jQuery('#geekybot_container').append('<div id=\"' + node_id + '\" style=\"top: '+node.top+'px; left: '+node.left+'px; \" class=\"'+node.class+' node big-box geekybot_custom_tooltip \"><div class=\"box bg-black geekybot_story_leftaction_wrp 02\" data-parentid=\"'+parent_node_id+'\" data-parenttype=\"'+node.type+'\"><input class=\"possible_node\" type=\"hidden\" name=\"story[ids][]\" value=\"'+nodeValue+'\" required=\"\" data-category=\"'+nodeCategory+'\" data-nodeid=\"' + node_id + '\"><img src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/'+node.image+'.png\">'+nodeText+'<img class=\"geekybot_node_remove\" title = \"". esc_attr(__('Delete', 'geeky-bot'))."\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/cross.png\" ><div class=\"active_zone_wrp\"></div><span class=\"geekybot_tooltip_text\"></span></div></div>');
                }
            } else {
                // to auto draw the fallback
                // if (node.type == 'user_input') {
                if (positions.length >= 2) {
                    var fallBackNode = positions.find(n => n.type === 'fallback' && n.parentId === node.id);
                    // if (fallBackNode) 
                    {
                        var defaultFBClass = '';
                        if(node.id == 'node1') {
                            defaultFBClass = 'geekybot_node_remove_default_fb';
                        }
                        jQuery('#geekybot_container').append('<div id=\"' + node_id + '\" style=\"top: '+node.top+'px; left: '+node.left+'px; \" class=\"'+node.class+' node big-box geekybot_custom_tooltip\"><div class=\"box bg-black geekybot_story_leftaction_wrp 03 \" data-parentid=\"'+parent_node_id+'\" data-parenttype=\"'+node.type+' \"><input class=\"possible_node\" type=\"hidden\" name=\"\" value=\"'+nodeValue+'\" required=\"\" data-category=\"'+nodeCategory+'\" data-nodeid=\"' + node_id + '\"><img src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/'+node.image+'.png\"><span>'+node.text+'</span><img class=\"geekybot_node_remove geekybot_node_remove_intent_fb '+ defaultFBClass +'\" title = \"". esc_attr(__('Delete', 'geeky-bot'))."\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/cross.png\" ><div class=\"active_zone_wrp\"></div><span class=\"geekybot_tooltip_text\"></span></div></div>');
                    }
                }
            }
            // add the new point in the middle of two points
            if (index != 0 && node.type != 'fallback') {
                var pNode = positions[index - 1];
                if (pNode.type == 'fallback') {
                    pNode = positions[index - 2];
                }
                var additionalIndexes = 1;
                for (var i = 0; i < index; i++) {
                    var isfallbackNode = positions.find(n => n.id === 'fallback_node'+i);
                    if (isfallbackNode) {
                        additionalIndexes = additionalIndexes + 1;
                    }
                }
                // var specialIndex = index + additionalIndexes;
                var specialIndex = index;
                if (pNode.type == 'user_input') {
                    var midNodeTop = pNode.top + 15;
                    var midNodeLeft = pNode.left + 40;
                } else if (pNode.type == 'response_function') {
                    var midNodeTop = pNode.top + 10;
                    var midNodeLeft = pNode.left + 125;
                } else if (pNode.type == 'start_point') {
                    var midNodeTop = pNode.top + 10;
                    var midNodeLeft = pNode.left + 120;
                } else {
                    var midNodeTop = pNode.top + 10;
                    var midNodeLeft = pNode.left + 110;
                }
                var Top = node.top + 155;
                var Left = node.left - 200;
                var fallbackTop = midNodeTop;
                var fallbackLeft = midNodeLeft;
                var midnode_id = node_id.replace('node', '');
                midnode_id = parseInt(midnode_id, 10) - 1;
                midnode_id = 'node'+midnode_id;
                // big-box class remove due to bug
                jQuery('#geekybot_container').append('<div id=\"' + midnode_id + '\" style=\"top: '+midNodeTop+'px; left: '+midNodeLeft+'px;\" class=\"drop-zone midnode node\" id=\"node'+midnode_id+'\"><div class=\"geekey_add_newfield_wrapper box-blue box bg-black geekybot_node_body\" ondrop=\"middrop(event)\" data-parentid=\"'+node.parentId+'\" data-parenttype=\"'+pNode.type+'\" data-arrIndex=\"'+specialIndex+'\" data-childId=\"'+node_id+'\" fallback-data-left=\"'+fallbackLeft+'\" fallback-data-top=\"'+fallbackTop+'\" data-left=\"'+Left+'\" data-top=\"'+Top+'\" ondragover=\"allowDrop(event)\" data-storyid=\"1\">+<div class=\"drop_zone_grand_paraent_wrp\"><div class=\"drop_zone_paraent_wrp\"><i class=\"fa fa-plus\"></div></div></div></div>');
            }
        });
        // Draw lines between positions
        positions.forEach(function(node, index) {
            if (node.parentId) {
                var parentNode = positions.find(n => n.id === node.parentId);
                curvedLine(jQuery('#' + parentNode.id)[0], jQuery('#' + node.id)[0], index);
                var fallBackNode = positions.find(n => n.type === 'fallback' && n.parentId === node.id);
                if (fallBackNode) {
                    // curvedLine(jQuery('#' + fallBackNode.parentId)[0], jQuery('#' + fallBackNode.id)[0], index);
                }
                // if (node.type == 'user_input') {
                if (node.id == 'node3') {
                    var fallBackNode = positions.find(n => n.type === 'fallback' && n.parentId === 'node1');
                    // curvedLine(jQuery('#' + fallBackNode.parentId)[0], jQuery('#' + fallBackNode.id)[0], index);
                    // recheck this
                }
            }
        });
        function straightLine(first_node, second_node) {
            var rect1 = first_node.getBoundingClientRect();
            var rect2 = second_node.getBoundingClientRect();
            var first_node_data = positions.find(n => n.id === first_node.id);
            var second_node_data = positions.find(n => n.id === second_node.id);
            var x1 = first_node_data.left + rect1.width / 2;
            var y1 = first_node_data.top + rect1.height / 2;
            var x2 = second_node_data.left + rect2.width / 2;
            var y2 = second_node_data.top + rect2.height / 2;
            // Set starting point
            ctx.beginPath();
            ctx.moveTo(x1, y1); // Start from the center of the first node
            // Draw line to the center of the second node
            ctx.lineTo(x2, y2);
            // Set line style
            ctx.strokeStyle = 'grey';
            ctx.lineWidth = 1;
            // Draw the line
            ctx.stroke();
        }

        function curvedLine(first_node, second_node, index) {
            var rect1 = first_node.getBoundingClientRect();
            var rect2 = second_node.getBoundingClientRect();
            var first_node_parent_type = jQuery('#'+first_node.id+' .geekybot_node_body').attr('data-parenttype');
            var second_node_parent_type = jQuery('#'+first_node.id+' .box').attr('data-parenttype');
            var first_node_data = positions.find(n => n.id === first_node.id);
            var second_node_data = positions.find(n => n.id === second_node.id);
            var x1 = first_node_data.left + rect1.width / 2;
            if (second_node_data.type == 'fallback') {
                var x2 = second_node_data.left + 50 / 2;
            } else {
                if (second_node_data.type == 'user_input') {
                    var x2 = second_node_data.left + rect2.width / 2 + 25;
                } else {
                    var x2 = second_node_data.left + rect2.width / 2;
                }
            }
            var y1 = first_node_data.top + rect1.height / 2;
            var y2 = second_node_data.top + rect2.height / 2;
            // to limit the min distance from the left
            if (x2 < 150) {
                x2 = 200;
            }
            const angle = Math.atan2(y1 - y2, x1 - x2);
            const angleInDegrees = angle * 180 / Math.PI;
            var finalAngleOne = 40;
            var finalAngleTwo = 50;
            if (angleInDegrees > 155 && angleInDegrees < 160) {
                var finalAngleOne = 35;
                var finalAngleTwo = 45;
            } else if (angleInDegrees >= 160 && angleInDegrees < 165) {
                var finalAngleOne = 30;
                var finalAngleTwo = 40;
            } else if (angleInDegrees >= 165 && angleInDegrees < 170) {
                var finalAngleOne = 25;
                var finalAngleTwo = 35;
            } else if (angleInDegrees >= 170 && angleInDegrees < 180) {
                var finalAngleOne = 20;
                var finalAngleTwo = 30;
            }
            ctx.beginPath();// Set starting point
            ctx.moveTo(x1, y1); // Start from the specified point (x1, y1)
            // Draw straight line to a point closer to x2
            if (Math.abs(x1 - x2) > 25 && Math.abs(y1 - y2) > 25) {
                var midX = (x1 + x2) / 2;
                ctx.lineTo(midX - 60, y1); // Move straight to the midpoint on the x-axis
                // Draw rounded corner upwards
                if (y2 > y1) { // Object 2 is lower than object 1
                    ctx.arcTo(midX - 20, y1, midX - 20, y1 + 10, finalAngleOne); // Change y-offset to positive
                } else {
                    ctx.arcTo(midX - 20, y1, midX - 20, y1 - 10, finalAngleOne); // Bend upwards, control point adjusted, radius 40
                }
                // Draw straight line to a point closer to y2
                const midY = (y1 + y2) / 2;// Calculate midpoint between y1 and y2
                ctx.lineTo(midX - 20, midY); // Move straight to the midpoint on the y-axis 
                // Draw rounded corner to the final point (x2, y2)
                if (y2 > y1) { // Object 2 is lower than object 1
                    ctx.arcTo(midX - 20, midY + 70, x2, y2 , finalAngleTwo);
                } else {
                    ctx.arcTo(midX - 20, midY - 70, x2, y2, finalAngleTwo); // Bend to top-right, control point adjusted, radius 50
                }
            }
            ctx.lineTo(x2, y2); // Draw final line segment to the end point
            // Set line style
            ctx.strokeStyle = 'grey';
            ctx.lineWidth = 1;
            // Draw the curve
            ctx.stroke();
        }

        function drawLine(first_node, second_node) {
            var rect1 = first_node.getBoundingClientRect();
            var rect2 = second_node.getBoundingClientRect();
            var first_node_data = positions.find(n => n.id === first_node.id);
            var second_node_data = positions.find(n => n.id === second_node.id);
            var x1 = first_node_data.left + rect1.width / 2;
            var y1 = first_node_data.top + rect1.height / 2;
            var x2 = second_node_data.left + rect2.width / 2;
            var y2 = second_node_data.top + rect2.height / 2;
            // Set starting point
            ctx.beginPath();
            ctx.moveTo(x1, y1); // Start from the specified point
            // Draw straight line to the right
            ctx.lineTo(x1 + 60, y1); // Move straight to x-coordinate + 80
            // Draw rounded corner upwards
            ctx.arcTo(x1 + 120, y1, x1 + 120, y1 - 50, 40); // Bend upwards, control point at (x + 160, y), radius 40
            // Draw straight line to the right
            ctx.lineTo(x1 + 120, y1 - 100); // Move straight to y-coordinate - 100
            // Draw rounded corner to the final point
            ctx.arcTo(x1 + 120, y1 - 150, x1 + 240, y1 - 150, 50); // Bend to top-right, control point at (x + 160, y - 150), radius 50          
            // Set line style
            ctx.strokeStyle = 'grey';
            ctx.lineWidth = 1;
            // Draw the curve
            ctx.stroke();
        }

        function find_node_index(positions, nodeId) {
            var nodeMap = -1;
            positions.forEach(function(node, index) {
                if (node.id == nodeId) {
                    nodeMap = index;
                }
            });
            return nodeMap;
        }

        function removeFormHTLM() {
            jQuery('div#userInputFormBody').html('');
            jQuery('div#responseTextFormBody').html('');
            jQuery('div#responseFunctionFormBody').html('');
            jQuery('div#responseActionFormBody').html('');
            jQuery('div#responseFormFormBody').html('');
            jQuery('div#defaultIntentFallbackFormBody').html('');
            jQuery('div#defaultFallbackFormBody').html('');
        }
    }";
    wp_add_inline_script('geekybot-main-js',$geekybot_js);
    $story_mode_list = array(
      (object) array('id' => '1', 'text' => __('Discard', 'geeky-bot')),
      (object) array('id' => '2', 'text' => __('Temporary discard', 'geeky-bot')),
      (object) array('id' => '3', 'text' => __('Automate', 'geeky-bot'))
    );
    // if (!isset(geekybot::$_data[0]['missing_intent'])) {
        $missing_intent = geekybotphplib::GEEKYBOT_htmlspecialchars((GEEKYBOTrequest::GEEKYBOT_getVar('missing_intent','GET')), ENT_QUOTES, 'UTF-8');
    // }
    if(!isset(geekybot::$_data[0]['story']->positions_array) || geekybot::$_data[0]['story']->positions_array == ''){
        $startPointMsg = __('Start Point', 'geeky-bot');
        $fallbackMsg = __('Default Fallback', 'geeky-bot');
        $geekybot_js ="
        // Get max canvas size dynamically based on the user's browser
        let maxCanvasSize = getMaxCanvasSize();
        var idCounter = 2;
        var add_missing_node = 0;
        // start point's position
        var positions = [
            { id: 'node1', top: 500, left: 0, parentId: null, parentType: 'start', type: 'start_point', text: \"".$startPointMsg."\", image: 'home', class: 'node_start_point', category: 'start' }
        ];";
        if (isset($missing_intent)) {
            $geekybot_js .="
            var missing= '".$missing_intent."'
            var position = { 
                id: 'node' + idCounter++, // Generate unique ID
                top: 352,   // Y coordinate relative to the parent
                left: 241, // X coordinate relative to the parent
                parentId: 'node1',
                type: 'user_input',
                text: 'User input',
                image: 'user-icon',
                class: 'node_action_user_input user_input_from_history',
                category: 'intent',
                value: ''
            };
            positions.push(position); // Push position into the array
            add_missing_node = 1;
            ";
        }
        $geekybot_js .="
        idCounter = idCounter++;
        console.log(positions);
        drawNodes(idCounter);
        ";
    } else {
        $geekybot_js ="
            // Get max canvas size dynamically based on the user's browser
            let maxCanvasSize = getMaxCanvasSize();
            // var idCounter = 2;
            var idCounter_no = ". geekybot::$_data[0]['story']->number_of_objects.";
            var idCounter = (idCounter_no * 2) - 2;
            var positions = ". geekybot::$_data[0]['story']->positions_array.";
            // Iterate over each object in the array
            var parentTop = 352;
            var parentLeft = 241;
            var parentId = 'node1';
            var add_missing_node = 0;
            positions.forEach(function(item) {
                // Convert string values representing numbers into actual numbers
                parentId = item.id;
                parentTop = item.top = parseInt(item.top);
                parentLeft = item.left = parseInt(item.left);
            });";
            if (isset($missing_intent)) {
                $geekybot_js .="
                var missing= '".$missing_intent."'
                // add the node to the array
                var maxParentTopDistance = 20;
                expectedParentTop = parseInt(parentTop, 10) - 138;
                if (expectedParentTop < maxParentTopDistance) {
                    parentTop = parseInt(parentTop, 10) - 6;
                } else {
                    parentTop = expectedParentTop;
                }
                parentLeft = parseInt(parentLeft, 10) + 241;
                var maxIdNumber = 0;
                var filtered_positions = positions.filter(obj => !obj.id.startsWith('fallback_'));
                positions.forEach(function(node, index) {
                    var nodeIdNumber = parseInt(node.id.replace('node', ''));
                    if (nodeIdNumber > maxIdNumber) {
                        maxIdNumber = nodeIdNumber;
                    }
                });
                if(maxIdNumber == 0) {
                    maxIdNumber = maxIdNumber + 1;
                } else {
                    maxIdNumber = maxIdNumber + 2;
                }
                
                idCounter++;
                var position = { 
                    id: 'node' + maxIdNumber, // Generate unique ID
                    top: parentTop,   // Y coordinate relative to the parent
                    left: parentLeft, // X coordinate relative to the parent
                    parentId: parentId,
                    type: 'user_input',
                    text: 'User input',
                    image: 'user-icon',
                    class: 'node_action_user_input user_input_from_history',
                    category: 'intent',
                    value: ''
                };
                positions.push(position); // Push position into the array
                add_missing_node = 1;
                ";
            }
            $geekybot_js .="
            setCanvasWidthHeight();
            idCounter = idCounter++;
            console.log(positions);
            drawNodes(idCounter);
            if (canvas.width > 1200) {
                // Scroll the container to the right to show the new object smoothly
                var canvasContainer = document.getElementById('canvas_container');
                canvasContainer.scrollTo({left: canvas.width, behavior: 'smooth'});
            }
        ";
    }
    $geekybot_js .="
    if(add_missing_node == 1) {
        // open the popup and bind possible_values
        jQuery('div.geekybot-avlble-varpopup').slideUp('slow');
        jQuery('div.geekybot_story_right_popup_inner_wrp').slideUp('slow');
        jQuery('div#userinput-popup').slideDown('slow');
        jQuery('div#response-text-popup').slideUp('slow');
        jQuery('div#response-function-popup').slideUp('slow');
        jQuery('div#response-action-popup').slideUp('slow');
        jQuery('div#default-fallback-popup').slideUp('slow');
        jQuery('div#default-intent-fallback-popup').slideUp('slow');
        jQuery('div#response-form-popup').slideUp('slow');

        // bind the missing value in the opened popup
        var inputField = jQuery('.user_input_from_history').find('input[type=\"hidden\"]');
        // Access and manipulate the input field (if it exists)
        if (inputField.length > 0) {
            // Do something with the input field
            jQuery('input[type=\"hidden\"]').removeClass('active_node');
            jQuery('div.geekybot_story_leftaction_wrp').removeClass('active_node_parent');
            inputField.addClass('active_node'); // Example: Set a new value
            inputField.closest('.geekybot_story_leftaction_wrp').addClass('active_node_parent');
            // bind data in case of update
            var input_value = inputField.val();
            if (input_value !== undefined || input_value !== '') {
                // get values using ajax
                var ajaxurl = '". esc_url(admin_url('admin-ajax.php')) ."';
                jQuery('div#userInputFormBody').append('<img id=\"geekybot-loading-icon\" src=\"".GEEKYBOT_PLUGIN_URL ."includes/images/story/story_load.gif\" />');
                jQuery.post(ajaxurl, {
                    action: 'geekybot_ajax',
                    geekybotme: 'stories',
                    task: 'getUserInputFormBodyHTMLAjax',
                    id: '',
                    '_wpnonce':'". esc_attr(wp_create_nonce('get-form-html')) ."'
                }, function(data) {
                    jQuery('div#userInputFormBody').find('img#geekybot-loading-icon').remove();
                    if (data) {
                        jQuery('div#userInputFormBody').html(geekybot_DecodeHTML(data));
                        jQuery(\"input[name='user_messages[]']\").each(function() {
                            var value = jQuery(this).val(missing);
                        });
                    }
                });
            }
        } else {
            jQuery('input[type=\"hidden\"]').removeClass('active_node');
            jQuery('div.geekybot_story_leftaction_wrp').removeClass('active_node_parent');
        }
        var inputField = jQuery('.node_action_user_input').removeClass('.user_input_from_history');
    }
    ";
    $geekybot_js .="
    var dragid = '';
    var intentCount = 0;
    var currentDirection = 'up';
    var maxParentTopDistance = 20;
    var maxParentBottomDistance = 720;
    
    function drag(key) {
        dragid = key;
    }

    function drop(event){
        event.preventDefault(); // Prevent default to enable dropping
        if (!dragid || dragid == '') {
            return
        }
        var dropdiv = event.target;
        dropdiv = jQuery(dropdiv);
        if (!dropdiv.hasClass('geekybot_node_body')) {
            dropdiv = dropdiv.closest('.geekybot_node_body');
            // dropdiv = dropdiv.parent();
        }
        var parentType = dropdiv.attr('data-parenttype');
        var parentId = dropdiv.attr('data-parentid');
        if(dragid == 'geekybot_fallback' && (parentType != 'user_input' && parentType != 'start_point')) {
            jQuery('div.drop_zone_grand_paraent_wrp').removeClass('drop_zone_grand_paraent_visible');
            return;
        }
        // check if the fallback already exist for the this node
        var isfallBackExist = positions.find(n => n.parentId === parentId && n.type === 'fallback');
        if(dragid == 'geekybot_fallback' && isfallBackExist) {
            jQuery('div.drop_zone_grand_paraent_wrp').removeClass('drop_zone_grand_paraent_visible');
            return;
        }
        storyid = dropdiv.attr('data-storyid');
        // dragid -= 1;
        // var box_id = intents[dragid]['id'];
        // recheck
        intentCount++;
        var clonedElement = dropdiv.clone(true); // Create a clone (optional)
        var parentElement = dropdiv.parent(); // Get the parent element

        // parentElement.remove(); // Remove the parent element
        dropdiv.remove(); // Remove the parent element

        // Insert the cloned element (optional)
        // recheck
        // dragid = '';
        var parentId = dropdiv.attr('data-parentid');
        var parentTop = dropdiv.attr('data-top');
        var parentLeft = dropdiv.attr('data-left');
        // get the dragged item data
        var [block_type, block_img, block_text, block_class, block_category] = getDraggedItemData(dragid, parentType);
        // check for fallback
        if(dragid == 'geekybot_fallback'){
            // to limit the max distance from the top
            var maxBottomDistance = 760;
            var next_position = parseInt(parentTop, 10) + 138;
            if (next_position > maxBottomDistance) {
                parentTop = parseInt(parentTop, 10) - 138;
            } else {
                parentTop = parseInt(parentTop, 10) + 138;
            }
            if(positions.length > 1) {
                parentLeft = parseInt(parentLeft, 10) + 100;
            } else {
                parentLeft = parseInt(parentLeft, 10) + 220;
            }
        } else {
            // to limit the max distance from the top
            expectedParentTopUp = parseInt(parentTop, 10) - 138;
            expectedParentTopDown = parseInt(parentTop, 10) + 138;
            if (currentDirection === 'up') {
                if (expectedParentTopUp >= maxParentTopDistance) {
                    // Move up if within limits
                    parentTop = expectedParentTopUp;
                } else {
                    // Reached top limit, switch to downward movement
                    currentDirection = 'down';
                    parentTop = expectedParentTopDown;
                }
            } else if (currentDirection === 'down') {
                if (expectedParentTopDown <= maxParentBottomDistance) {
                    // Move down if within limits
                    parentTop = expectedParentTopDown;
                } else {
                    // Reached bottom limit, switch to upward movement
                    currentDirection = 'up';
                    parentTop = expectedParentTopUp;
                }
            }
            if(block_type == 'response_function') {
                parentLeft = parseInt(parentLeft, 10) + 200;
            } else if(block_type != 'user_input') {
                parentLeft = parseInt(parentLeft, 10) + 210;
            } else {
                parentLeft = parseInt(parentLeft, 10) + 240;
            }
        }
        // get the next unique id
        var maxIdNumber = 0;
        var filtered_positions = positions.filter(obj => !obj.id.startsWith('fallback_'));
        positions.forEach(function(node, index) {
            var nodeIdNumber = parseInt(node.id.replace('node', ''));
            if (nodeIdNumber > maxIdNumber) {
                maxIdNumber = nodeIdNumber;
            }
        });
        if(maxIdNumber == 0) {
            maxIdNumber = maxIdNumber + 1;
        } else {
            maxIdNumber = maxIdNumber + 2;
        }
        var position = {
            id: 'node' + maxIdNumber, // Generate unique ID
            top: parentTop,   // Y coordinate relative to the parent
            left: parentLeft, // X coordinate relative to the parent
            parentId: parentId,
            parentType: parentType,
            type: block_type,
            text: block_text,
            image: block_img,
            class: block_class,
            category: block_category,
            value: ''
        };
        positions.push(position); // Push position into the array
        // set the canvas width and height according to the node size
        setCanvasWidthHeight();
        idCounter = idCounter++;
        drawNodes(idCounter);
        if (canvas.width > 1200) {
            // Scroll the container to the right to show the new object smoothly
            var canvasContainer = document.getElementById('canvas_container');
            canvasContainer.scrollTo({left: canvas.width, behavior: 'smooth'});
        }
    }

    function middrop(event){
        event.preventDefault(); // Prevent default to enable dropping
        if (!dragid || dragid == '') {
            return
        }
        var dropdiv = event.target;
        dropdiv = jQuery(dropdiv);
        if (!dropdiv.hasClass('geekybot_node_body')) {
            dropdiv = dropdiv.closest('.geekybot_node_body');
            // dropdiv = dropdiv.parent();
        }
        var parentType = dropdiv.attr('data-parenttype');
        var parentId = dropdiv.attr('data-parentid');
        if(dragid == 'geekybot_fallback' && !isfallBackExist && (parentType != 'user_input' && parentType != 'start_point')) {
            jQuery('div.drop_zone_grand_paraent_wrp').removeClass('drop_zone_grand_paraent_visible');
            return;
        }
        // check if the fallback already exist for the this node
        var isfallBackExist = positions.find(n => n.parentId === parentId && n.type === 'fallback');
        if(dragid == 'geekybot_fallback' && isfallBackExist) {
            jQuery('div.drop_zone_grand_paraent_wrp').removeClass('drop_zone_grand_paraent_visible');
            return;
        }
        if (dropdiv.hasClass('geekybot_node_body')) {
          dropdiv = dropdiv;
        }
        idCounter = idCounter + 1;
        // dragid -= 1;
        var clonedElement = dropdiv.clone(true); // Create a clone (optional)
        var parentElement = dropdiv.parent(); // Get the parent element
        dropdiv.remove(); // Remove the parent element
        // dragid = '';
        var newindex = dropdiv.attr('data-arrIndex');
        var childId = dropdiv.attr('data-childId');
        var parentTop = dropdiv.attr('data-top');
        var parentLeft = dropdiv.attr('data-left');
        var fallBackTop = dropdiv.attr('fallback-data-top');
        var fallBackLeft = dropdiv.attr('fallback-data-left');
        // check for fallback
        if(dragid == 'geekybot_fallback'){
            // to limit the max distance from the top
            var maxBottomDistance = 760;
            var next_position = parseInt(fallBackTop, 10) + 138;
            if (next_position > maxBottomDistance) {
                parentTop = parseInt(fallBackTop, 10) - 138;
            } else {
                parentTop = parseInt(fallBackTop, 10) + 138;
            }
            parentLeft = parseInt(fallBackLeft, 10) + 100;
        } else {
            // to limit the max distance from the top
            var maxParentTopDistance = 20;
            expectedParentTop = parseInt(parentTop, 10) - 150;
            if (expectedParentTop < maxParentTopDistance) {
                parentTop = parseInt(parentTop, 10);
                parentLeft = parseInt(parentLeft, 10) + 240;
            } else {
                parentTop = expectedParentTop;
                parentLeft = parseInt(parentLeft, 10) + 200;
            }
        }
        var maxIdNumber = 0;
        var filtered_positions = positions.filter(obj => !obj.id.startsWith('fallback_'));
        positions.forEach(function(node, index) {
            var nodeIdNumber = parseInt(node.id.replace('node', ''));
            if (nodeIdNumber > maxIdNumber) {
                maxIdNumber = nodeIdNumber;
            }
        });
        maxIdNumber = maxIdNumber + 2;
        var cid = 'node' + maxIdNumber;
        // get the dragged item data
        var [block_type, block_img, block_text, block_class, block_category] = getDraggedItemData(dragid, parentType);
        var newPosition = { 
            id: 'node' + maxIdNumber, // Generate unique ID
            top: parentTop,   // Y coordinate relative to the parent
            left: parentLeft, // X coordinate relative to the parent
            parentId: parentId,
            parentType: parentType,
            type: block_type,
            text: block_text,
            image: block_img,
            class: block_class,
            category: block_category,
            value: ''
        };
        // var positions = positions.filter(obj => !obj.id.startsWith('fallback_'));
        if(dragid == 'geekybot_fallback'){
            positions.splice(newindex, 0, newPosition);
        } else {
            positions.splice(newindex, 0, newPosition);
            positions.forEach(function(node, index) {
                if (index > newindex) {
                    if(node.type == 'fallback'){
                        var fbIntentNodeId = node.parentId;
                        var fallBackParentNode = positions.find(n => n.id === fbIntentNodeId);
                        // to limit the max distance from the top
                        parentTop = fallBackParentNode.top + 150;
                        parentLeft = fallBackParentNode.left + 240;
                    } else {
                        // to limit the max distance from the top
                        var maxParentTopDistance = 20;
                        expectedParentTop = positions[index].top - 155;
                        if (expectedParentTop < maxParentTopDistance) {
                            parentTop = positions[index].top;
                            parentLeft = positions[index].left + 240;
                        } else {
                            parentTop = expectedParentTop;
                            parentLeft = positions[index].left + 200;
                        }
                    }
                    var node_id = node.id;
                    positions[index].top = parentTop;
                    positions[index].left = parentLeft;
                }
            });
        }
        newindex++;
        if(dragid != 'geekybot_fallback'){
            positions[newindex].parentId = cid;
        }
        // set the canvas width and height according to the node size
        setCanvasWidthHeight();
        maxIdNumber++;
        drawNodes(maxIdNumber);
    }

    function getDraggedItemData(dragid, dropdiv){
        var block_type = 'user_input';
        var block_img = 'user-icon';
        var block_text = \"".__('User input', 'geeky-bot')."\";
        var block_class = 'node_action_user_input';
        var block_category = 'intent';
        if(dragid == 'geekybot_userinput'){
            block_type = 'user_input';
            block_img = 'user-icon';
            block_text = \"".__('User input', 'geeky-bot')."\";
            block_class = 'node_action_user_input';
            block_category = 'intent';
        } else if(dragid == 'geekybot_text'){
            block_type = 'response_text';
            block_img = 'bot-text';
            block_text = \"".__('Text', 'geeky-bot')."\";
            block_class = 'node_action_text';
            block_category = 'response';
        } else if(dragid == 'geekybot_function'){
            block_type = 'response_function';
            block_img = 'bot-function';
            block_text = \"".__('Function', 'geeky-bot')."\";
            block_class = 'node_action_function';
            block_category = 'response';
        } else if(dragid == 'geekybot_action'){
            block_type = 'response_action';
            block_img = 'bot-action';
            block_text = \"".__('Action', 'geeky-bot')."\";
            block_class = 'node_action_action';
            block_category = 'response';
        } else if(dragid == 'geekybot_form'){
            block_type = 'response_form';
            block_img = 'bot-form';
            block_text = \"".__('Form', 'geeky-bot')."\";
            block_class = 'node_action_form';
            block_category = 'response';
        } else if(dragid == 'geekybot_fallback'){
            block_type = 'fallback';
            block_img = 'fallback';
            console.log(dropdiv);
            if(dropdiv == 'start_point') {
                block_text = \"".__('Default Fallback', 'geeky-bot')."\";
                block_class = 'node_action_fallback';
            } else {
                block_text = \"".__('Fallback', 'geeky-bot')."\";
                block_class = 'node_action_intent_fallback';
            }
            block_category = 'fallback';
        }
        return [block_type, block_img, block_text, block_class, block_category];
    }

    function getMaxCanvasSize() {
        let testCanvas = document.createElement('canvas');
        let ctx = testCanvas.getContext('2d');

        // Default safe values (lower than max limits for all browsers)
        let maxSize = { width: 16384, height: 8192 };

        try {
            // Function to check max width
            function detectMaxDimension(axis) {
                let size = 4096; // Start from a reasonable size
                let step = 4096; // Increment step

                while (size + step <= 65536) { // Chrome max is 65535, Firefox lower
                    if (axis === 'width') testCanvas.width = size + step;
                    else testCanvas.height = size + step;

                    // Check when `getImageData` starts failing  
                    try {
                        ctx.getImageData(0, 0, 1, 1);
                    } catch (e) {
                        break; // Stop increasing size when Firefox silently fails
                    }
                    size += step;
                }
                return size;
            }

            // Detect browser-specific max width and height
            maxSize.width = detectMaxDimension('width');
            maxSize.height = detectMaxDimension('height');

            // Ensure we do not exceed known max values for browsers
            maxSize.width = Math.min(maxSize.width, 65535);  // Chrome max
            maxSize.height = Math.min(maxSize.height, 16384); // Chrome max
        } catch (e) {
            console.warn('Canvas size detection failed, using safe defaults.');
        }

        return maxSize;
    }


    function setCanvasWidthHeight() {
        var filtered_positions = positions.filter(obj => obj.type !== 'fallback');

        if (filtered_positions.length > 0) {
            var lastElementLeft = filtered_positions[filtered_positions.length - 1].left;
            var canvasWidth = lastElementLeft + 200;
            console.log(canvasWidth);
            // Prevent exceeding browser limits
            canvas.width = Math.min(canvasWidth, maxCanvasSize.width);
            canvas.height = 800;
        } else {
            // Default canvas size
            canvas.width = 1200;
            canvas.height = 800;
        }
    }

    function allowDrop(ev){
        ev.preventDefault();
    }

    function clearNotifications(){
        setTimeout(function(){
            jQuery('.geeky-bot-popop-save-success-msg').slideUp();
        }, 1500);
        setTimeout(function(){
            jQuery('.geeky-bot-popop-save-success-msg').remove()
        }, 2000);";
        $geekybot_js .= geekybot::$_data[0]['missing_intent'] = 1;
        $geekybot_js .="
    }

    function ShowList(i) {
        var data = ".$slotList.";
        jQuery('#autocomplete'+i).autocomplete({
            source: data,
            autoFocus: true,
            classes: {
                'ui-autocomplete': 'geekybot-ui-autocomplete'
                },
                select: function(event, ui) {
                    // prevent autocomplete from updating the textbox
                    event.preventDefault();
                    // manually update the textbox and hidden field
                    jQuery(this).val(ui.item.value);
                    jQuery('#autocomplete'+i+'-value').val(ui.item.value);
                    jQuery('#typ'+i+' select').val(ui.item.type);
                    addParameter();
                }
        }).data('ui-autocomplete')._renderItem = function( ul, item ) {
            let txt = String(item.value).replace(new RegExp(this.term, 'i'),'<b>$&</b>');
            return jQuery('<li></li>')
            .data('ui-autocomplete-item', item)
            .append('<a>' + txt + '</a>')
            .appendTo(ul);
        };
    }";
    wp_add_inline_script('geekybot-main-js',$geekybot_js);
    $geekybot_js ="
    jQuery(document).ready(function() {
        // drawNodes();
    });

    jQuery(document).ready(function (jQuery) {
        // Increase the shell to make more prominent
        const draggedElement = jQuery('.drag-item.geekybot_story_right_menu_cnt.geekybot_story_response');
        // const draggedItem = document.getElementById('draggedItem');
        const draggableElements = document.querySelectorAll('.geekybot_story_right_menu_cnt');
        // const draggedItem = jQuery('#ali123')[0];

        draggedElement.on('dragstart', function(event) {
            const dropTargets = document.querySelectorAll('.bg-black.geekybot_node_body');
            draggableElements.forEach(draggable => {
                // draggable.addEventListener('drag', (event) => {
                document.addEventListener('dragover', function (event) {
                    event.preventDefault();
                    handleProximityZones(this, event, draggable, dropTargets);
                });
            });

            function handleProximityZones(target, event, draggedItem, dropTargets) {
                const proximityThreshold = 150; // Adjust this value as needed
                // Calculate the center of the dragged item
                let nearestDropTarget = null;
                let minDistance = Infinity;
                const draggedCenterX = event.clientX;
                const draggedCenterY = event.clientY;
                dropTargets.forEach(dropTarget => {
                    // Get the position and size of the drop target
                    const targetRect = dropTarget.getBoundingClientRect();
                    // Calculate the center of the drop target
                    const targetCenterX = targetRect.left + targetRect.width / 2;
                    const targetCenterY = targetRect.top + targetRect.height / 2;
                    // Calculate the distance between the centers of the dragged item and the drop target
                    const distance = Math.sqrt(Math.pow(draggedCenterX - targetCenterX, 2) + Math.pow(draggedCenterY - targetCenterY, 2));
                    // Check if this drop target is closer than the previous closest one
                    if (distance < proximityThreshold && distance < minDistance) {
                      minDistance = distance;
                      nearestDropTarget = dropTarget;
                    }
                });
                // Apply custom behavior based on proximity
                dropTargets.forEach(dropTarget => {
                    if (dropTarget === nearestDropTarget) {
                        // Increase the shell or perform other actions
                        dropTarget.style.border = '1px solid green';
                        // Add a new class to the child of the nearest drop target
                        const childElement = dropTarget.querySelector('.drop_zone_grand_paraent_wrp');
                        if (childElement) {
                            childElement.classList.add('drop_zone_grand_paraent_visible');
                        }
                        // jQuery('#end_point').find('.drop_zone_grand_paraent_wrp').addClass('drop_zone_grand_paraent_visible');
                    } else {
                        dropTarget.style.border = '1px solid #e6e7e8';
                        // Reset styles for other drop targets
                        const childElement = dropTarget.querySelector('.drop_zone_grand_paraent_wrp');
                        if (childElement) {
                            childElement.classList.remove('drop_zone_grand_paraent_visible');
                        }
                    }
                });
            }
        });
        // Increase the shell code end here
        // close popup on click
        jQuery('img#userinputPopupCloseBtn, img#responseTextPopupCloseBtn, img#responseFormPopupCloseBtn, img#responseFunctionPopupCloseBtn, img#responseActionPopupCloseBtn, img#fallbackPopupCloseBtn, img#intentFallbackPopupCloseBtn, .save-and-close-popup').click(function (e) {
            jQuery('div.geekybot-avlble-varpopup').slideUp('slow');
            jQuery('div.geekybot_story_right_popup_inner_wrp').slideDown('slow');
            jQuery('div#userinput-popup').slideUp('slow');
            jQuery('div#response-text-popup').slideUp('slow');
            jQuery('div#response-function-popup').slideUp('slow');
            jQuery('div#response-action-popup').slideUp('slow');
            jQuery('div#response-form-popup').slideUp('slow');
            jQuery('div#default-fallback-popup').slideUp('slow');
            jQuery('div#default-intent-fallback-popup').slideUp('slow');
            // remove highlight effects
            jQuery('div.geekybot_story_leftaction_wrp').removeClass('active_node_parent');
        });

        jQuery('img#responseAddActionPopupCloseBtn').click(function (e) {
            jQuery('div#response-add-action-popup').slideUp('slow');
        });
        
        jQuery('img#responseAddFormPopupCloseBtn').click(function (e) {
            jQuery('div#response-add-form-popup').slideUp('slow');
            jQuery('div.geekybot_story_right_popup_inner_wrp').slideDown('slow');
        });
    });";
    wp_add_inline_script('geekybot-main-js',$geekybot_js);
}
if (!GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/header',array('module' => 'stories'))){
  return;
}
$search_type_list = array(
  (object) array('id' => '2', 'text' => __('NLP', 'geeky-bot')),
  (object) array('id' => '1', 'text' => __('Simple', 'geeky-bot'))
);
?>
<!-- main wrapper -->
<div id="geekybotadmin-wrapper" class="geekybot-admin-main-wrapper">
    <?php GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/upper-nav',array('module' => 'stories','layouts' => 'formstory')); ?>
    <div class="geekybotadmin-body-main geekybotadmin-storypge-bodymain">
        <!-- left menu -->
        <div id="geekybotadmin-leftmenu-main">
            <?php  GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/leftmenue',array('module' => 'stories')); ?>
        </div>
        <div id="geekybotadmin-data" class="geekybotadmin-story-data">
            <div class="geekybot_story_search_section_mainwrp">
                <div class="geekybot_story_search_section_searchwrp">
                    <div class="geekybot_story_main_heading">
                        <h1 class="geekybot-head-text">
                            <?php echo isset(geekybot::$_data[0]['story']->name) ? esc_attr(geekybot::$_data[0]['story']->name) : ''; ?>
                        </h1>
                    </div>
                    <!-- filter form -->
                    <div class="geekybot_story_main_search_wrp">
                        <div id="geekybot-searchbar" class="geekybot-searchbar-btn">
                            <div class="geekybot-window-bottom-inner geekybot-story-inner">
                                <button title="<?php echo esc_html(__('Search', 'geeky-bot')); ?>" type="submit" name="searchBtn" id="searchBtn" value="Search" class="button geekybot-form-search-btn">
                                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/loupe.png" alt="<?php echo esc_attr(__('Search', 'geeky-bot')); ?>" class="geekybot-action-img">
                                </button>
                                <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('searchInput','', array('class' => 'inputbox geekybot-form-input-field', 'placeholder' => esc_attr(__('Search', 'geeky-bot')))), GEEKYBOT_ALLOWED_TAGS); ?>
                                <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_select('searchType', $search_type_list, '', null, array('class' => 'inputbox geekybot-form-input-field geekybot-form-select-field')), GEEKYBOT_ALLOWED_TAGS); ?>
                            </div>
                            <div id="geekybot-reset-btn-main" >
                                <a id="searchReset" class="geekybot-Intents-reset-btn" href="#" title="<?php echo esc_attr(__('Reset', 'geeky-bot')); ?>">
                                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/reset.png" alt="<?php echo esc_attr(__('Reset', 'geeky-bot')); ?>" />
                                </a>
                            </div>
                        </div>
                        <!-- searchInput -->
                    </div>
                </div>
                <div id="geekybotSearchResultsWrp">
                    <!-- Results will appear here -->
                </div>
            </div>
            <!-- filter form -->
            <!-- page content -->
            <div id="geekybot-admin-wrapper" class="p0 bg-n bs-n">
                <!-- filter form -->
                <!-- top head -->
                <?php
                if (!empty(geekybot::$_data[0])) {?>
                    <div class="geekybot_story">
                        <form id="stories_form" class="geekybot-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=stories&task=savestories"),"save-story")); ?>">
                            <div class="geekybot_story_left story_wrp">
                                <!-- new code -->
                                <div class="geekybot_story_form_wrp">
                                    <?php
                                    // show all forms
                                    $formsData = GEEKYBOTincluder::GEEKYBOT_getModel('forms')->getFormsForDropDown();
                                    $valuearray = explode(", ", geekybot::$_data[0]['story']->form_ids);
                                    $i = 0;
                                    foreach ($formsData AS $form) {
                                        $check = '';
                                        if(in_array($form->id, $valuearray)){
                                            $check = 'checked';
                                        } ?>
                                        <input type="checkbox" <?php echo esc_attr($check) ?> class="radiobutton js-ticket-append-radio-btn " value="<?php echo esc_attr($form->id) ?>" id="<?php echo esc_attr($form->id).'_'.esc_attr($i) ?>" name="story[form_ids][]">
                                        <label for="story[form_ids][]" id="foruf_checkbox1">
                                            <?php echo esc_html($form->form_name) ?>
                                        </label>
                                        <?php
                                        $i++;
                                    } ?>
                                </div>
                                <div class="geeky_hide geekybot_form_wrp">
                                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_select("story[story_mode]", $story_mode_list, isset(geekybot::$_data[0]['story']->story_mode) ? geekybot::$_data[0]['story']->story_mode : '', null, array('class' => 'inputbox geekybot-form-select-field')), GEEKYBOT_ALLOWED_TAGS); ?>
                                </div>
                                <!-- new code end -->
                                <div id="story_<?php echo esc_attr($story_id); ?>" class="geekybot_story_wrp">
                                    <input id="storyid" type="text" name="storyid" value="<?php echo esc_attr($story_id); ?>" hidden="">
                                    <div style="float: left;width:100%;height:800px;" >
                                        <div style="height:800px;" class="p-3  w-100 geekybot_story_main_wrp">
                                            <div id="canvas_container">
                                                <div id="geekybot_container"></div>
                                                <canvas id="canvas" width="1200" height="800"></canvas>
                                            </div>
                                            <div class="geekybot_story_pagination_wrp">
                                                <span id="move_to_start" >
                                                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>/includes/images/story/start.png" alt="<?php echo esc_attr(__('Move To Start', 'geeky-bot')); ?>" title="<?php echo esc_attr(__('Move To Start', 'geeky-bot')); ?>">
                                                </span>
                                                <span id="move_to_left" >
                                                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>/includes/images/story/left.png" alt="<?php echo esc_attr(__('Move To Left', 'geeky-bot')); ?>" title="<?php echo esc_attr(__('Move To Left', 'geeky-bot')); ?>">
                                                </span>
                                                <span id="move_to_right" >
                                                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>/includes/images/story/right.png" alt="<?php echo esc_attr(__('Move To Right', 'geeky-bot')); ?>" title="<?php echo esc_attr(__('Move To Right', 'geeky-bot')); ?>">
                                                </span>
                                                <span id="move_to_end" >
                                                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>/includes/images/story/end.png" alt="<?php echo esc_attr(__('Move To End', 'geeky-bot')); ?>" title="<?php echo esc_attr(__('Move To End', 'geeky-bot')); ?>">
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php
                            echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('_wpnonce', wp_create_nonce('delete-story')),GEEKYBOT_ALLOWED_TAGS);
                            echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('form_request', 'geekybot'),GEEKYBOT_ALLOWED_TAGS);
                            echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('isadmin', '1'),GEEKYBOT_ALLOWED_TAGS);
                            echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('user-popup-title-text', __('Chat ', 'geeky-bot')),GEEKYBOT_ALLOWED_TAGS);
                            ?>
                        </form>
                        <div class="geekybot_story_right">
                            <div class="geekybot-avlble-varpopup geekybot-popup-wrapper" style="display: none;">
                                <div class="avlble-varpopup-userpopup-top">
                                    <div class="userpopup-top">
                                        <div class="userpopup-heading">
                                            <?php echo esc_html(__('Available Variable','geeky-bot')); ?>
                                        </div>
                                        <img id="avlble-varpopup-closebtn" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>/includes/images/close.png" alt="<?php echo esc_attr(__('Close', 'geeky-bot')); ?>" title="<?php echo esc_attr(__('Close', 'geeky-bot')); ?>">
                                    </div>
                                </div>
                                <div class="geekybot-avlble-varpopup-body-mainwrp">
                                    <?php 
                                    if (isset(geekybot::$_data[0]['story']) && geekybot::$_data[0]['story']->story_type == 2) { ?>
                                        <div class="geekybot-avlble-varpopup-body">
                                            <h5 class="avlble-varpopup-heading">
                                                <?php echo esc_html(__('Product Name','geeky-bot')); ?>
                                            </h5>
                                            <div class="avlble-varpopup-disc">
                                                <?php echo esc_html('[item](woo_product_name)'); ?>
                                            </div>
                                        </div>
                                        <div class="geekybot-avlble-varpopup-body">
                                            <h5 class="avlble-varpopup-heading">
                                                <?php echo esc_html(__('Minimum Price','geeky-bot')); ?>
                                            </h5>
                                            <div class="avlble-varpopup-disc">
                                                <?php echo esc_html('[15](woo_product_min_price)'); ?>
                                            </div>
                                        </div>
                                        <div class="geekybot-avlble-varpopup-body">
                                            <h5 class="avlble-varpopup-heading">
                                                <?php echo esc_html(__('Maximum Price','geeky-bot')); ?>
                                            </h5>
                                            <div class="avlble-varpopup-disc">
                                                <?php echo esc_html('[25](woo_product_max_price)'); ?>
                                            </div>
                                        </div>
                                        <?php
                                    } elseif (isset(geekybot::$_data[0]['story']) && geekybot::$_data[0]['story']->story_type == 1) { ?>
                                        <div class="geekybot-avlble-varpopup-body">
                                            <h5 class="avlble-varpopup-heading">
                                                <?php echo esc_html(__('User Login','geeky-bot')); ?>
                                            </h5>
                                            <div class="avlble-varpopup-disc">
                                                <?php echo esc_html('[Jack](geekybot_user_login)'); ?>
                                            </div>
                                        </div>
                                        <?php
                                    } ?>
                                </div>
                            </div>
                            <div class="geekybot_story_right_popup_inner_wrp">
                                <div class="geekybot_story_right_popup_nav">
                                    <div class="geekybot_story_right_navlogo">
                                        <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/story/action-icon.png" alt="<?php echo esc_attr(__('Action Icon', 'geeky-bot')); ?>" title="<?php echo esc_attr(__('Action', 'geeky-bot')); ?>">
                                        <?php echo esc_attr(__('Action', 'geeky-bot')); ?>
                                    </div>
                                </div>
                                <div class="geekybot_story_right_body">
                                  <div class="geekybot_story_right_menu">
                                    <h5><?php echo esc_attr(__('User Input', 'geeky-bot')); ?></h5>
                                    <div id="draggedItem" class="geekybot_userinput drag-item geekybot_story_right_menu_cnt geekybot_story_response geeky_popup_userinput" draggable="true" ondragstart="drag('geekybot_userinput')">
                                      <div class="geekybot_story_right_menu_cnt_img">
                                        <img class="geeky_popup_userinput_icon" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/story/user-icon.png" alt="<?php echo esc_attr(__('User Icon', 'geeky-bot')); ?>"title="<?php echo esc_attr(__('User', 'geeky-bot')); ?>">
                                      </div>
                                      <div class="geekybot_story_right_menu_cnt_title">
                                        <?php echo esc_attr(__('User Input', 'geeky-bot')); ?>
                                      </div>
                                    </div>
                                    <h5><?php echo esc_attr(__('Bot Response', 'geeky-bot')); ?></h5>
                                    <div id="draggedItem" class="geekybot_text drag-item geekybot_story_right_menu_cnt geekybot_story_response" draggable="true" ondragstart="drag('geekybot_text')">
                                      <div class="geekybot_story_right_menu_cnt_img">
                                        <img class="geeky_popup_usertext_icon" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/story/bot-text.png" alt="<?php echo esc_attr(__('User Text Icon', 'geeky-bot')); ?>"title="<?php echo esc_attr(__('Text', 'geeky-bot')); ?>">
                                      </div>
                                      <div class="geekybot_story_right_menu_cnt_title">
                                        <?php echo esc_attr(__('Text', 'geeky-bot')); ?>
                                      </div>
                                    </div>
                                    <div id="draggedItem" class="geekybot_function drag-item geekybot_story_right_menu_cnt geekybot_story_response" draggable="true" ondragstart="drag('geekybot_function')">
                                      <div class="geekybot_story_right_menu_cnt_img">
                                        <img class="geeky_popup_userfunction_icon" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/story/bot-function.png" alt="<?php echo esc_attr(__('Bot Function Icon', 'geeky-bot')); ?>"title="<?php echo esc_attr(__('Bot Function', 'geeky-bot')); ?>">
                                      </div>
                                      <div class="geekybot_story_right_menu_cnt_title">
                                        <?php echo esc_attr(__('Predefined Function', 'geeky-bot')); ?>
                                      </div>
                                    </div>
                                    <div id="draggedItem" class="geeky_hide geekybot_action drag-item geekybot_story_right_menu_cnt geekybot_story_response" draggable="true" ondragstart="drag('geekybot_action')">
                                      <div class="geekybot_story_right_menu_cnt_img">
                                        <img class="geeky_popup_useraction_icon" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/story/bot-action.png" alt="<?php echo esc_attr(__('Bot Action Icon', 'geeky-bot')); ?>"title="<?php echo esc_attr(__('Bot Action', 'geeky-bot')); ?>">
                                      </div>
                                      <div class="geekybot_story_right_menu_cnt_title">
                                        <?php echo esc_attr(__('Actions', 'geeky-bot')); ?>
                                      </div>
                                    </div>
                                    <div id="draggedItem" class="geeky_hide geekybot_form drag-item geekybot_story_right_menu_cnt geekybot_story_response" draggable="true" ondragstart="drag('geekybot_form')">
                                      <div class="geekybot_story_right_menu_cnt_img">
                                        <img class="geeky_popup_botform_icon" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/story/bot-form.png" alt="<?php echo esc_attr(__('Bot Form Icon', 'geeky-bot')); ?>"title="<?php echo esc_attr(__('Bot Form', 'geeky-bot')); ?>">
                                      </div>
                                      <div class="geekybot_story_right_menu_cnt_title">
                                        <?php echo esc_attr(__('Forms', 'geeky-bot')); ?>
                                      </div>
                                    </div>
                                    <h5><?php echo esc_attr(__('Fallback', 'geeky-bot')); ?></h5>
                                    <div id="draggedItem" class="geekybot_fallback drag-item geekybot_story_right_menu_cnt geekybot_story_response" draggable="true" ondragstart="drag('geekybot_fallback')">
                                        <div class="geekybot_story_right_menu_cnt_img">
                                            <img class="geeky_popup_fallback_icon" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/story/fallback.png" alt="<?php echo esc_attr(__('Fallback Icon', 'geeky-bot')); ?>"title="<?php echo esc_attr(__('Fallback', 'geeky-bot')); ?>">
                                        </div>
                                        <div class="geekybot_story_right_menu_cnt_title">
                                            <?php echo esc_attr(__('Fallback Massege', 'geeky-bot')); ?>
                                        </div>
                                    </div>
                                  </div>
                                </div>
                            </div>
                            <!-- popup code start from here -->
                            <!-- user input popup -->
                            <div id="userinput-popup" class="geekybot-popup-wrapper" style="display: none;">
                                <div class="userpopup-top">
                                    <div class="userpopup-heading">
                                        <div class="userpopup-heading-mainwrp"><img class="geeky-popup-heading-lfticon" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/story/user-blue.png" alt="<?php echo esc_attr(__('User Icon', 'geeky-bot')); ?>"title="<?php echo esc_attr(__('User', 'geeky-bot')); ?>"></div>
                                        <?php echo esc_html(__('User Input','geeky-bot')); ?>
                                    </div>
                                    <img title="<?php echo esc_html(__('Close','geeky-bot')); ?>" alt="<?php echo esc_html(__('Close','geeky-bot')); ?>" id="userinputPopupCloseBtn" title="<?php echo esc_attr(__('Close', 'geeky-bot')); ?>" class="userpopup-close" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/close.png" />
                                </div>
                                <div class="geekybot-admin-popup-cnt">
                                    <form id="userInputForm" class="geekybot-popup-form" method="post" enctype="multipart/form-data" action="#">
                                        <div id="userInputFormBody" >
                                            <!-- bind data from afax -->
                                        </div>
                                        <div class="geekybot-form-button">
                                            <?php
                                            echo wp_kses(GEEKYBOTformfield::GEEKYBOT_submitbutton('save', __('Save','geeky-bot'), array('class' => 'button  geekybot-admin-pop-btn-block', 'title' => __('Save', 'geeky-bot'))),GEEKYBOT_ALLOWED_TAGS);
                                            echo wp_kses(GEEKYBOTformfield::GEEKYBOT_submitbutton('save', __('Save & Close','geeky-bot'), array('class' => 'button  geekybot-admin-pop-btn-block save-and-close-popup', 'title' => __('Save & Close', 'geeky-bot'))),GEEKYBOT_ALLOWED_TAGS);
                                            ?>
                                        </div>
                                        <div id="user-input-msg"></div>
                                    </form>
                                </div>
                            </div>
                            <!-- response text popup -->
                            <div id="response-text-popup" class="geekybot-popup-wrapper" style="display: none;">
                                <div class="userpopup-top">
                                    <div class="userpopup-heading">
                                        <div class="userpopup-heading-mainwrp"><img class="geeky-popup-heading-lfticon" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/story/bot-text.png" alt="<?php echo esc_attr(__('User Text Icon', 'geeky-bot')); ?>"title="<?php echo esc_attr(__('Text', 'geeky-bot')); ?>"></div>
                                        <?php echo esc_html(__('Text','geeky-bot')); ?>
                                    </div>
                                    <img alt="<?php echo esc_html(__('Close','geeky-bot')); ?>" id="responseTextPopupCloseBtn" class="userpopup-close" title="<?php echo esc_html(__('Close','geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/close.png" />
                                </div>
                                <div class="geekybot-admin-popup-cnt">
                                    <form id="responseTextForm" class="geekybot-popup-form" method="post" action="#">
                                        <div id="responseTextFormBody" >
                                            <!-- bind data from afax -->
                                        </div>
                                        <div class="geeky-bot-prametrinfo-wrp">
                                            <div class="geeky-infoicon-image-text-wraper">
                                                <img alt="<?php echo esc_html(__('Info Icon','geeky-bot')); ?>"title="<?php echo esc_html(__('Info','geeky-bot'));?>" class="userpopup-info-icon" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/story/info-blue.png" />
                                                <?php echo esc_attr(__('How to add parameters', 'geeky-bot')); ?>
                                            </div>
                                            <div class="geeky-popup-youtube-icon-wrapper">
                                                <i class="fa fa-solid fa-play"></i>
                                            </div>
                                        </div>
                                        <div class="geekybot-form-button">
                                            <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_submitbutton('save', __('Save','geeky-bot'), array('class' => 'button geekybot-admin-pop-btn-block', 'title' => __('Save', 'geeky-bot'))), GEEKYBOT_ALLOWED_TAGS);
                                            echo wp_kses(GEEKYBOTformfield::GEEKYBOT_submitbutton('save', __('Save & Close','geeky-bot'), array('class' => 'button geekybot-admin-pop-btn-block save-and-close-popup', 'title' => __('Save & Close', 'geeky-bot'))), GEEKYBOT_ALLOWED_TAGS);
                                            ?>
                                        </div>
                                        <div id="response-text-msg"></div>
                                    </form>
                                </div>
                            </div>
                            <!-- form popup -->
                            <div id="response-form-popup" class="geekybot-popup-wrapper" style="display: none;">
                                <div class="userpopup-top">
                                    <div class="userpopup-heading">
                                        <div class="userpopup-heading-mainwrp">
                                            <img class="geeky-popup-heading-lfticon" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/story/bot-form.png" alt="<?php echo esc_attr(__('Bot Form Icon', 'geeky-bot')); ?>"title="<?php echo esc_attr(__('Bot Form', 'geeky-bot')); ?>">
                                        </div>
                                        <?php echo esc_html(__('Forms','geeky-bot')); ?>
                                    </div>
                                    <img id="responseFormPopupCloseBtn" title="<?php echo esc_html(__('Close','geeky-bot')); ?>" alt="<?php echo esc_html(__('Close','geeky-bot')); ?>" class="userpopup-close" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/close.png" />
                                </div>
                                <div class="geekybot-admin-popup-cnt">
                                    <form id="responseFormForm" class="geekybot-popup-form" method="post" action="#">
                                        <div id="responseFormFormBody">
                                            <!-- bind data from afax -->
                                        </div>
                                        <div class="geekybot-form-button">
                                            <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_submitbutton('save', __('Save','geeky-bot') .' '. __('Form', 'geeky-bot'), array('class' => 'button geekybot-admin-pop-btn-block', 'title' => __('Save', 'geeky-bot'))), GEEKYBOT_ALLOWED_TAGS);
                                            ?>
                                        </div>
                                        <div id="response-form-msg"></div>
                                    </form>
                                </div>
                            </div>
                            <!-- add Custom Form Popup -->
                            <div id="response-add-form-popup" class="geekybot-popup-wrapper user-custom-form-popup" style="display: none;">
                                <div class="userpopup-top">
                                    <div class="userpopup-heading">
                                        <div class="userpopup-heading-mainwrp"><img class="geeky-popup-heading-lfticon" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/story/bot-form.png" alt="<?php echo esc_attr(__('User form Icon', 'geeky-bot')); ?>"title="<?php echo esc_attr(__('Form', 'geeky-bot')); ?>"></div>
                                        <?php echo esc_html(__('Add New Form','geeky-bot')); ?>
                                    </div>
                                    <img alt="<?php echo esc_html(__('Close','geeky-bot')); ?>" id="responseAddFormPopupCloseBtn" class="userpopup-close" title="<?php echo esc_html(__('Close','geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/close.png" />
                                </div>
                                <div class="geekybot-admin-popup-cnt">
                                    <form id="responseAddForm" class="geekybot-popup-form" method="post" action="#">
                                        <div class="geekybot-form-wrapper">
                                            <div class="geekybot-form-value">
                                                <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('custome_form_id',''),GEEKYBOT_ALLOWED_TAGS); ?>
                                                <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('form_name', isset($data->form_name) ? geekybot::GEEKYBOT_getVarValue(geekybot::$_data[0]->form_name) : '', array('class' => 'inputbox geekybot-form-input-field', 'data-validation' => 'required', 'placeholder' => __('Form Name *', 'geeky-bot') , 'pattern' => '^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$')), GEEKYBOT_ALLOWED_TAGS) ?>
                                            </div>
                                        </div>
                                        <div class="geekybot-form-add-newfield-button">
                                            <div id="user-form-inputs">
                                                <div class="geeky-popup-dynamic-field geeky-popup-cutom-form-dynamic-field">
                                                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('variable_name[]', '', array('class' => 'typeAndSelect inputbox geeky-popup-dynamic-field-input variableName', 'placeholder' => __('Varibale Name *', 'geeky-bot'))),GEEKYBOT_ALLOWED_TAGS); ?>
                                                    <div class="geekybot-custom-form-dropdown">
                                                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_select('variable_type[]', $typelist, isset($typelist) ? $typelist : '', null, array('class' => 'inputbox geekybot-form-select-field variableType')), GEEKYBOT_ALLOWED_TAGS); ?>
                                                    </div>
                                                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('variable_possible_values[]', isset(geekybot::$_data[0]->possible_values) ? geekybot::GEEKYBOT_getVarValue(geekybot::$_data[0]->possible_values) : '', array('class' => 'inputbox geeky-popup-dynamic-field-input variablePossibleValues', 'placeholder' => __('Add Comma Seprated Values', 'geeky-bot'))),GEEKYBOT_ALLOWED_TAGS); ?>
                                                </div>
                                                <!-- Dynamically created input fields will be appended here -->
                                            </div>
                                            <div id="create-form-input">
                                                <span class="geekybot-frm-add-field-button" title="<?php echo esc_attr(__('Add New Variable','geeky-bot')); ?>" title="<?php echo esc_html(__('Add','geeky-bot')); ?>" onclick="addFormVariable();">
                                                    <span class="geekybot-frm-add-field-add-iconbtn-wrp"><img alt="<?php echo esc_html(__('Add Icon','geeky-bot')); ?>" title="<?php echo esc_html(__('Add','geeky-bot')); ?>" class="userpopup-plus-icon" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/add-icon.png" /></span>
                                                    <?php echo esc_attr(__('Add New Variable','geeky-bot')); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="geekybot-form-button">
                                            <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_submitbutton('save', esc_html(__('Save','geeky-bot')) .' '. esc_html(__('Forms', 'geeky-bot')), array('class' => 'button geekybot-admin-pop-btn-block','title' => __('Save', 'geeky-bot'))), GEEKYBOT_ALLOWED_TAGS); ?>
                                        </div>
                                        <div id="response-add-form-msg"></div>
                                    </form>
                                </div>
                            </div>
                            <!-- Predefined Function popup -->
                            <div id="response-function-popup" class="geekybot-popup-wrapper" style="display: none;">
                                <div class="userpopup-top">
                                    <div class="userpopup-heading">
                                    <div class="userpopup-heading-mainwrp"><img class="geeky-popup-heading-lfticon" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/story/bot-function.png" alt="<?php echo esc_attr(__('Bot Function Icon', 'geeky-bot')); ?>"title="<?php echo esc_attr(__('Function Form', 'geeky-bot')); ?>"></div>
                                        <?php echo esc_html(__('Predefined Function','geeky-bot')); ?>
                                    </div>
                                    <img id="responseFunctionPopupCloseBtn" title="<?php echo esc_html(__('Close','geeky-bot')); ?>" alt="<?php echo esc_html(__('Close','geeky-bot')); ?>" class="userpopup-close" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/close.png" />
                                </div>
                                <div class="geekybot-admin-popup-cnt">
                                    <form id="responseFunctionForm" class="geekybot-popup-form" method="post" action="#">
                                        <div id="responseFunctionFormBody">
                                            <!-- bind data from afax -->
                                        </div>
                                        <div class="geekybot-form-button">
                                            <?php
                                            echo wp_kses(GEEKYBOTformfield::GEEKYBOT_submitbutton('save', __('Save','geeky-bot'), array('class' => 'button geekybot-admin-pop-btn-block','title' => __('Save', 'geeky-bot'))), GEEKYBOT_ALLOWED_TAGS);
                                            echo wp_kses(GEEKYBOTformfield::GEEKYBOT_submitbutton('save', __('Save & Close','geeky-bot'), array('class' => 'button geekybot-admin-pop-btn-block save-and-close-popup','title' => __('Save & Close', 'geeky-bot'))), GEEKYBOT_ALLOWED_TAGS);
                                            ?>
                                        </div>
                                        <div id="response-function-msg"></div>
                                    </form>
                                </div>
                            </div>
                            <!-- Action popup -->
                            <div id="response-action-popup" class="geekybot-popup-wrapper" style="display: none;">
                                <div class="userpopup-top">
                                    <div class="userpopup-heading">
                                    <div class="userpopup-heading-mainwrp"><img class="geeky-popup-heading-lfticon" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/story/bot-action.png" alt="<?php echo esc_attr(__('Bot Action Icon', 'geeky-bot')); ?>"title="<?php echo esc_attr(__('Action Form', 'geeky-bot')); ?>"></div>
                                        <?php echo esc_html(__('Custom Action','geeky-bot')); ?>
                                    </div>
                                    <img id="responseActionPopupCloseBtn" title="<?php echo esc_html(__('Close','geeky-bot')); ?>" alt="<?php echo esc_html(__('Close','geeky-bot')); ?>" class="userpopup-close" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/close.png" />
                                </div>
                                <div class="geekybot-admin-popup-cnt">
                                    <form id="responseActionForm" class="geekybot-popup-form" method="post" action="#">
                                        <div id="responseActionFormBody">
                                            <!-- bind data from afax -->
                                        </div>
                                        <div class="geekybot-form-button">
                                            <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_submitbutton('save', __('Save','geeky-bot') .' '. __('Response', 'geeky-bot'), array('class' => 'button geekybot-admin-pop-btn-block','title' => __('Save', 'geeky-bot'))), GEEKYBOT_ALLOWED_TAGS);
                                            ?>
                                        </div>
                                        <div id="response-action-msg"></div>
                                    </form>
                                </div>
                            </div>
                            <!-- add Custom Action Popup -->
                            <div id="response-add-action-popup" class="geekybot-popup-wrapper user-custom-form-popup" style="display: none;">
                                <div class="userpopup-top">
                                    <div class="userpopup-heading">
                                        <div class="userpopup-heading-mainwrp"><img class="geeky-popup-heading-lfticon" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/story/bot-action.png" alt="<?php echo esc_attr(__('User form Icon', 'geeky-bot')); ?>"title="<?php echo esc_attr(__('Action', 'geeky-bot')); ?>"></div>
                                        <?php echo esc_html(__('Add New Custom Action','geeky-bot')); ?>
                                    </div>
                                    <img alt="<?php echo esc_html(__('Close','geeky-bot')); ?>" id="responseAddActionPopupCloseBtn" class="userpopup-close" title="<?php echo esc_html(__('Close','geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/close.png" />
                                </div>
                                <div class="geekybot-admin-popup-cnt">
                                    <form id="responseAddActionForm" class="geekybot-popup-form" method="post" action="#">
                                        <div class="geekybot-form-wrapper">
                                            <div class="geekybot-form-value">
                                                <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('function_name', isset($data->function_name) ? geekybot::GEEKYBOT_getVarValue(geekybot::$_data[0]->function_name) : '', array('class' => 'inputbox geekybot-form-input-field', 'data-validation' => 'required', 'placeholder' => 'Function Name' , 'pattern' => '^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$')), GEEKYBOT_ALLOWED_TAGS) ?>
                                            </div>
                                        </div>
                                        <div class="geekybot-form-add-newfield-button">
                                            <div id="user-action-inputs">
                                                <div class="geeky-popup-dynamic-field geeky-popup-cutom-form-dynamic-field">
                                                    <input name = "paramname[]" type="text" value = "" class="inputbox geeky-popup-dynamic-field-input" autocomplete="off" placeholder="<?php echo esc_attr(__('Parameter Name *','geeky-bot')); ?>" />
                                                    <div class="geekybot-custom-form-dropdown">
                                                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_select('paramtype[]', $paramlist, isset($paramtype) ? $paramtype : '', null, array('class' => 'inputbox geekybot-form-select-field')), GEEKYBOT_ALLOWED_TAGS); ?>
                                                    </div>
                                                </div>
                                                <!-- Dynamically created input fields will be appended here -->
                                            </div>
                                            <div id="create-action-input">
                                                <span class="geekybot-frm-add-field-button" title="<?php echo esc_attr(__('Add New Parameter','geeky-bot')); ?>" onclick="addActionParameter();">
                                                    <span class="geekybot-frm-add-field-add-iconbtn-wrp"><img alt="<?php echo esc_html(__('Add Icon','geeky-bot')); ?>" class="userpopup-plus-icon" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/add-icon.png" /></span>
                                                    <?php echo esc_attr(__('Add New Parameter','geeky-bot')); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="geekybot-form-button">
                                            <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_submitbutton('save', __('Save','geeky-bot') .' '. __('Action', 'geeky-bot'), array('class' => 'button geekybot-admin-pop-btn-block','title' => __('Save', 'geeky-bot'))), GEEKYBOT_ALLOWED_TAGS); ?>
                                        </div>
                                        <div id="response-add-action-msg"></div>
                                    </form>
                                </div>
                            </div>
                            <!-- Fall Back Popup -->
                            <div id="default-fallback-popup" class="geekybot-popup-wrapper" style="display: none;">
                                <div class="userpopup-top">
                                    <div class="userpopup-heading">
                                        <div class="userpopup-heading-mainwrp"><img class="geeky-popup-heading-lfticon" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/story/fallback.png" alt="<?php echo esc_attr(__('Fallback Icon', 'geeky-bot')); ?>" title="<?php echo esc_attr(__('Fallback', 'geeky-bot')); ?>"></div>
                                        <?php echo esc_html(__('Default Fallback','geeky-bot')); ?>
                                    </div>
                                    <img alt="<?php echo esc_html(__('Close','geeky-bot')); ?>" id="fallbackPopupCloseBtn" title="<?php echo esc_attr(__('Close','geeky-bot')); ?>" class="userpopup-close" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/close.png" />
                                </div>
                                <div class="geekybot-admin-popup-cnt">
                                    <form id="defaultFallbackForm" class="geekybot-popup-form" method="post" action="#">
                                        <div id="defaultFallbackFormBody">
                                            <!-- bind data from afax -->
                                        </div>
                                        <div class="geekybot-form-button">
                                            <?php
                                            echo wp_kses(GEEKYBOTformfield::GEEKYBOT_submitbutton('save', __('Save','geeky-bot'), array('class' => 'button  geekybot-admin-pop-btn-block','title' => __('Save', 'geeky-bot'))),GEEKYBOT_ALLOWED_TAGS);
                                            echo wp_kses(GEEKYBOTformfield::GEEKYBOT_submitbutton('save', __('Save & Close','geeky-bot'), array('class' => 'button  geekybot-admin-pop-btn-block save-and-close-popup','title' => __('Save & Close', 'geeky-bot'))),GEEKYBOT_ALLOWED_TAGS);
                                             ?>
                                        </div>
                                        <div id="default-fallback-msg"></div>
                                    </form>
                                </div>
                            </div>
                            <!-- Intent Fall Back Popup -->
                            <div id="default-intent-fallback-popup" class="geekybot-popup-wrapper" style="display: none;">
                                <div class="userpopup-top">
                                    <div class="userpopup-heading">
                                        <div class="userpopup-heading-mainwrp"><img class="geeky-popup-heading-lfticon" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/story/fallback.png" alt="<?php echo esc_attr(__('Fallback Icon', 'geeky-bot')); ?>" title="<?php echo esc_attr(__('Fallback', 'geeky-bot')); ?>"></div>
                                        <?php echo esc_html(__('Intent Fallback','geeky-bot')); ?>
                                    </div>
                                    <img alt="<?php echo esc_html(__('Close','geeky-bot')); ?>" id="intentFallbackPopupCloseBtn" title="<?php echo esc_attr(__('Close','geeky-bot')); ?>" class="userpopup-close" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/close.png" />
                                </div>
                                <div class="geekybot-admin-popup-cnt">
                                    <form id="defaultIntentFallbackForm" class="geekybot-popup-form" method="post" action="#">
                                        <div id="defaultIntentFallbackFormBody">
                                            <!-- bind data from afax -->
                                        </div>
                                        <div class="geekybot-form-button">
                                            <?php
                                            echo wp_kses(GEEKYBOTformfield::GEEKYBOT_submitbutton('save', __('Save','geeky-bot'), array('class' => 'button  geekybot-admin-pop-btn-block','title' => __('Save', 'geeky-bot'))),GEEKYBOT_ALLOWED_TAGS);
                                            echo wp_kses(GEEKYBOTformfield::GEEKYBOT_submitbutton('save', __('Save & Close','geeky-bot'), array('class' => 'button  geekybot-admin-pop-btn-block save-and-close-popup','title' => __('Save & Close', 'geeky-bot'))),GEEKYBOT_ALLOWED_TAGS);
                                             ?>
                                        </div>
                                        <div id="default-intent-fallback-msg"></div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <!-- save and reset buttons -->
                        <div class="geekybot_story_left geekybot-form-button">
                            <div class="geekybot-btm-save-button">
                                <button type="submit" id="Save-Story" class="button geekybot-form-save-btn" title="<?php echo esc_attr(__('Save Story', 'geeky-bot')); ?>" value=""><?php echo esc_attr(__('Save Story', 'geeky-bot')); ?></button>
                                <button type="reset" id="Reset-Story" class="button geekybot-form-reset-btn" title="<?php echo esc_attr(__('Reset Story', 'geeky-bot')); ?>" value=""><?php echo esc_attr(__('Reset Story', 'geeky-bot')); ?></button>
                                <button style="margin-top: 15px;" onclick="addNewForms();" id="create-form" class="geeky_hide button geekybot-form-reset-btn" value=""><?php echo esc_attr(__('Add New Form', 'geeky-bot')); ?></button>
                            </div>
                        </div>
                    </div>
                    <?php
                    if (isset(geekybot::$_data[1]) ) {
                      GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/pagination',array('module' => 'stories' , 'pagination' => geekybot::$_data[1]));
                    }
                } else {
                    $msg = __('No record found','geeky-bot');
                    $link[] = array('link' => 'admin.php?page=geekybot_stories&geekybotlt=stories', 'text' => __('Add New','geeky-bot') .'&nbsp;'. __('Story','geeky-bot'));
                    echo wp_kses(GEEKYBOTlayout::GEEKYBOT_getNoRecordFound($msg,$link), GEEKYBOT_ALLOWED_TAGS);
                } ?>
            </div>
        </div>
    </div>
</div>
