<?php
    if (!defined('ABSPATH'))
        die('Restricted Access');
    if (!GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/header',array('module' => 'chathistory'))){
        return;
    }
?>
<!-- main wrapper -->
<div id="geekybotadmin-wrapper">
    <?php  GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/upper-nav',array('module' => 'chathistory','layouts' => 'chathistory')); ?>
    <div class="geekybotadmin-body-main">
        <!-- left menu -->
        <div id="geekybotadmin-leftmenu-main">
            <?php  GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/leftmenue',array('module' => 'chathistory')); ?>
        </div>
        <div id="geekybotadmin-data" class="geekybotadmin-cahthistory-data">
            <!-- top head -->
            <?php  GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/pagetitle',array('module' => 'chathistory','layouts' => 'chathistory')); ?>
            <form class="geekybot-filter-form" name="geekybotform" id="geekybotform" method="post" action="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=geekybot_chathistory"),"chat-history")); ?>">
                <div id="geekybot-searchbar" class="geekybot-searchbar-btn">
                    <div class="window-two-btm-inner geekybot-story-inner">
                        <button title="<?php echo esc_html(__('Search','geeky-bot')); ?>" type="submit" name="btnsubmit" id="btnsubmit" value="Search" class="button geekybot-form-search-btn"><img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/loupe.png" alt="<?php echo esc_attr(__('Search', 'geeky-bot')); ?>" class="geekybot-search-img"></button>
                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('searchtitle', geekybot::$_data['filter']['searchtitle'], array('class' => 'inputbox geekybot-form-input-field', 'placeholder' => __('Search with user name', 'geeky-bot'))),GEEKYBOT_ALLOWED_TAGS); ?>
                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('GEEKYBOT_form_search', 'GEEKYBOT_SEARCH'),GEEKYBOT_ALLOWED_TAGS); ?>
                    </div>
                    <div id="geekybot-reset-btn-main" >
                        <a class="geekybot-Intents-reset-btn"   href="javascript:resetFrom();" title="<?php echo esc_attr(__('reset', 'geeky-bot')); ?>">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/reset.png" alt="<?php echo esc_attr(__('reset', 'geeky-bot')); ?>" />
                        </a>
                    </div>
                </div>
            </form>
            <!-- page content -->
            <div id="geekybot-admin-wrapper" class="p0 bg-n bs-n">
                <?php
                if ( !empty(geekybot::$_data[0]['chathistory'])) { ?>
                    <div class="chatHistory">
                        <div class="geekybot-chat-history-toogle">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/menu.png" alt="<?php echo esc_attr(__('user', 'geeky-bot')); ?>" >
                            <span class="geekybotchat_text">
                                <?php echo esc_html(__('Select Chat' , 'geeky-bot')); ?>
                            </span>
                        </div>
                        <div class="chatHistory leftmenu" id="geekybtchat-leftmenu">
                            <?php
                            foreach (geekybot::$_data[0] as $key => $value) {
                                foreach ($value as $key3 => $value3) {
                                    $ctime = $value3->created;
                                    if ($value3->user_name != '') {
                                        $user_name = $value3->user_name;
                                    } else {
                                        $user_name = __('Guest', 'geeky-bot');
                                    }
                                    if (isset($value3->user_id) && $value3->user_id != 0) {
                                        $user_id = $value3->user_id;
                                    } else {
                                        $user_id = '';
                                    }
                                    $datet = gmdate("d M/Y H:i:s",geekybotphplib::GEEKYBOT_strtotime(gmdate($ctime)));
                                    ?>
                                    <div class="leftmenuuser" data-userid ="<?php echo esc_attr($value3->id) ?>" onclick="makeMeActive('<?php echo esc_js($user_name) ?>', '<?php echo esc_js($user_id) ?>', '<?php echo esc_js($value3->id) ?>', this, '<?php echo esc_js($datet); ?>', 0, 0)">
                                        <div class="menuImg" style="">
                                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/chat-history/users.png" alt="<?php echo esc_attr(__('user', 'geeky-bot')); ?>" >
                                        </div>
                                        <div class="menuUser" style="">
                                            <p class="leftmenusetting username">
                                                <?php
                                                    echo esc_html($user_name);
                                                ?>
                                            </p>
                                            <h4><?php echo esc_html(__('User ID', 'geeky-bot')); ?> : 
                                                <span class="leftmenuuserid">
                                                    <?php
                                                    if (isset($value3->user_id) && $value3->user_id != 0) {
                                                        echo esc_html($value3->user_id);
                                                    }
                                                    ?>
                                                </span>
                                            </h4>
                                        </div>
                                        <div class="menuDateTime" style="">
                                            <p class="leftmenusetting geekybot_datetime">
                                                <?php
                                                if ($value3->created != '') {
                                                    echo esc_html($value3->created); 
                                                }
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                    <?php
                                }
                            }
                            $html = getNextChatHistorySessionsHtml();
                            echo wp_kses($html, GEEKYBOT_ALLOWED_TAGS);
                            ?>
                        </div>
                        <div class="chatHistory rightContent">
                            <div class="user">
                                <div style="" class="user-info">
                                    <h1 class="username"></h1>
                                    <h4><?php echo esc_html(__('User ID', 'geeky-bot')); ?> : <span class="userid"></span></h4>
                                </div>
                            </div>
                        </div>
                        <div class="chatHistory chatcontent">
                            <div class="header">
                                <div class="senderTitle"><?php echo esc_html(__('Sender', 'geeky-bot')); ?></div>
                                <div class="messageTitle"> <?php echo esc_html(__('Message', 'geeky-bot')); ?></div>
                                <div class="actionTitle"><?php echo esc_html(__('Action', 'geeky-bot')); ?></div>
                            </div>
                            <div class="body">
                                <div class="body-inner">
                                    <div class="geekybot-error-messages-wrp geekybot-error-messages-style2">
                                        <div class="geekybot-error-msg-image-wrp">
                                            <img class="geekybot-error-msg-image" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) ?>includes/images/errors/select-chat.png" alt="<?php echo esc_attr(__("select chat", "geeky-bot")) ?>" />
                                        </div>
                                        <div class="geekybot-error-msg-txt">
                                            <?php echo esc_html(__('Chat history is not selected.', 'geeky-bot')) ?>
                                        </div>
                                        <div class="geekybot-error-msg-txt2">
                                            <?php echo esc_html(__("Please select a conversation to view the user's chat history.",'geeky-bot')) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                } else {
                    $msg = __('No record found','geeky-bot');
                    echo wp_kses(GEEKYBOTlayout::GEEKYBOT_getNoRecordFound($msg), GEEKYBOT_ALLOWED_TAGS);
                }
            ?>
            </div>
        </div>
    </div>
</div>

<!-- popup code start from here -->
<!-- user input popup -->
<div id="addIntentToStoryblack" style="display:none;"> </div>
<div id="addIntentToStory" style="display:none">
    <div class="ms-popup-header">
        <div class="popup-header-text">
            <?php echo esc_html(__('Add User Input To Story', 'geeky-bot')); ?>
        </div>
        <div class="popup-header-close-img">
        </div>
    </div>
    <div>
        <form id="stories_form" class="geekybot-form" method="post" enctype="multipart/form-data" action="#">
            <div class="geekybot-form-value" id="visibleAction">
                <?php
                    echo wp_kses(GEEKYBOTformfield::GEEKYBOT_select('storyid', GEEKYBOTincluder::GEEKYBOT_getModel('stories')->getStoriesForCombobox(), isset($action->action_id) ? $action->action_id : '', esc_html(__('Select','geeky-bot')) .' '. esc_html(__('Story', 'geeky-bot')), array('class' => 'inputbox geekybot-form-select-field', 'data-validation' => 'required')), GEEKYBOT_ALLOWED_TAGS)
                ?>
            </div>
            <div class="geekybot-form-value" id="visibleAction">
                <?php
                    echo wp_kses(GEEKYBOTformfield::GEEKYBOT_textarea('missing_intent', isset(geekybot::$_data[0]['default_message']) ? geekybot::$_data[0]['default_message'] : '', array('class' => 'inputbox js-textarea', 'data-validation' => 'required')), GEEKYBOT_ALLOWED_TAGS);
                ?>
            </div>
            <div class="geekybot-form-button">
                <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_submitbutton('addIntent', esc_html(__('Add User Input','geeky-bot')), array('class' => 'button geekybot-admin-pop-btn-block')), GEEKYBOT_ALLOWED_TAGS); ?>
            </div>
            <?php
            echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('form_request', 'geekybot'),GEEKYBOT_ALLOWED_TAGS);
            echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('isadmin', '1'),GEEKYBOT_ALLOWED_TAGS);
            ?>
            <div id="form-text-msg"></div>
        </form>
    </div> <!-- end of assigntostaff div -->
</div>
<?php

function getNextChatHistorySessionsHtml(){
    $nextpage = 2;
    $html = '<a id="jsjb-jm-showmorejobs" class="scrolltask" data-scrolltask="getNextChatHistorySessions" data-offset="'.esc_attr($nextpage).'" style="display:none;"></a>';
    return $html;
}

$geekybot_js ="
    jQuery(document).ready(function(){
        jQuery('.chatHistory.leftmenu div:first').addClass('active');
    });
    function makeMeActive(userName, userId, chatHistoryId, htmlDiv, datet, pagenum, pagination) {
        if(pagination == 0) {
            var clickedDiv = jQuery(htmlDiv);
            var allusers = jQuery('.leftmenuuser').removeClass('active');
            clickedDiv.addClass('active');
        }
        var ajaxurl = '". esc_url(admin_url('admin-ajax.php')) ."';
        jQuery.post(ajaxurl, {action: 'geekybot_ajax', geekybotme: 'chathistory', task: 'getUserChatHistoryMessages', username:userName, userid:userId, chatlimit:pagenum, chatHistoryId: chatHistoryId, datet: datet, '_wpnonce':'". esc_attr(wp_create_nonce("get-user-chat-history"))."'}, function (data) {
            if (data) {
                jQuery('.userid').text(' '+userId);
                jQuery('.user-info .username').text(userName);
                jQuery('div.body').html('');
                jQuery('div.body').html(geekybot_DecodeHTML(data));
                if(pagination == 1) {
                    jQuery('html, body').animate({ scrollTop: 0 }, 'slow');
                }
            } else {
                alert('No data Found');
                return false;
            }
        });
    }

    function resetFrom() {
        jQuery('input#searchtitle').val('');
        jQuery('form#geekybotform').submit();
    }
    jQuery(document).ready(function($) {
        jQuery(document).on('click', '.geekybot-table-act-btn', function() {
            jQuery('div#addIntentToStory').slideDown('slow');
            jQuery('div#addIntentToStoryblack').show();

            var parentDiv = jQuery(this).closest('.body-content');
            var intent = jQuery(parentDiv).find('.body-content-message-value').attr('data-intent');
            jQuery('#missing_intent').addClass(intent);
            jQuery('#missing_intent').val(intent);
        });
        jQuery('div.popup-header-close-img, div#addIntentToStoryblack').click(function(e) {
            jQuery('div#addIntentToStory').slideUp('slow');
            setTimeout(function() {
                jQuery('div#addIntentToStoryblack').hide();
            }, 700);
        });
    });
";
wp_add_inline_script('geekybot-main-js',$geekybot_js);
?>
<!-- popup code end -->
<?php
$geekybot_js ="
    jQuery(document).ready(function () {
        jQuery('.geekybot-chat-history-toogle').click(function(){
            jQuery('.chatHistory .leftmenu').toggle();
            jQuery('.chatHistory .leftmenu .leftmenuuser').addClass('leftmenumobile');
        });
        jQuery(document).on('click', '.leftmenumobile', function() {
            jQuery('.leftmenu').toggle();
        });
    });
    jQuery('form#stories_form').submit(function (e) {
        e.preventDefault();
        var storyid = jQuery('select#storyid').val();
        var missing_intent = jQuery('textarea#missing_intent').val();
        if (storyid == '' || storyid == null) {
            jQuery('#form-text-msg').html('<div class=\"geeky-bot-popop-save-success-msg geeky-error-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"". esc_html(__('Info','geeky-bot')) ."\" title=\"". esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-red.png\" />". esc_attr(__('You must choose a story; it cannot be empty.', 'geeky-bot'))."</div></div>');
        } else {
            var ajaxurl =
                '". esc_url(admin_url("admin-ajax.php")) ."';
            jQuery.post(ajaxurl, {
                action: 'geekybot_ajax',
                geekybotme: 'stories',
                task: 'addIntentToStory',
                storyid: storyid,
                missing_intent: missing_intent,
                '_wpnonce':'". esc_attr(wp_create_nonce("add-intent")) ."'
            }, function(data) {
                if (data) {
                    window.location.href = data;
                } else {
                    jQuery('#form-text-msg').html('<div class=\"geeky-bot-popop-save-success-msg geeky-error-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"". esc_html(__('Info','geeky-bot')) ."\" title=\"". esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-red.png\" />". esc_attr(__('Something went wrong try again later!', 'geeky-bot')) ."</div></div>');
                }
            });
        }
    });
    jQuery('#geekybtchat-leftmenu').on('scroll', function() {
        var scrollTop = jQuery(this).scrollTop();
        var innerHeight = jQuery(this).innerHeight();
        var scrollHeight = jQuery(this)[0].scrollHeight;
        // console.log('ScrollTop:', scrollTop);
        // console.log('InnerHeight:', innerHeight);
        // console.log('ScrollHeight:', scrollHeight);
        if (scrollTop + innerHeight >= scrollHeight - 10) {  // Adding a small threshold to ensure it triggers
            var scrolltask = jQuery('div#geekybtchat-leftmenu').find('a.scrolltask').attr('data-scrolltask');
            var offset = jQuery('div#geekybtchat-leftmenu').find('a.scrolltask').attr('data-offset');
            if (scrolltask != null && scrolltask != '' && scrolltask != 'undefined') {
                jQuery('div#geekybtchat-leftmenu').find('a.scrolltask').remove();
                var searchtitle = jQuery('input#searchtitle').val();
                var ajaxurl = '". esc_url(admin_url('admin-ajax.php')) ."';
                jQuery('div#geekybtchat-leftmenu').append('<img id=\"geekybot-loading-icon\" src=\"".GEEKYBOT_PLUGIN_URL ."includes/images/chat-history/load.gif\" />');
                jQuery.post(ajaxurl, {action: 'geekybot_ajax', geekybotme: 'chathistory', task: scrolltask,  offset:offset, searchtitle:searchtitle}, function (data) {
                    jQuery('div#geekybtchat-leftmenu').append(data);
                    jQuery('div#geekybtchat-leftmenu').find('img#geekybot-loading-icon').remove();
                });
            }
        }
    });
";
wp_add_inline_script('geekybot-main-js',$geekybot_js);
?>
