<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

if (!defined('GEEKYBOT_FILE_TYPE_ERROR')) {
    define('GEEKYBOT_FILE_TYPE_ERROR', 'GEEKYBOT_FILE_TYPE_ERROR');
    define('GEEKYBOT_FILE_SIZE_ERROR', 'GEEKYBOT_FILE_SIZE_ERROR');
    define('GEEKYBOT_ALREADY_EXIST', 'GEEKYBOT_ALREADY_EXIST');
    define('GEEKYBOT_NOT_EXIST', 'GEEKYBOT_NOT_EXIST');
    define('GEEKYBOT_IN_USE', 'GEEKYBOT_IN_USE');
    define('GEEKYBOT_STATUS_CHANGED', 'GEEKYBOT_STATUS_CHANGED');
    define('GEEKYBOT_STATUS_CHANGED_ERROR', 'GEEKYBOT_STATUS_CHANGED_ERROR');
    define('GEEKYBOT_DATA_SYNCHRONIZE', 'GEEKYBOT_DATA_SYNCHRONIZE');
    define('GEEKYBOT_DATA_SYNCHRONIZE_ERROR', 'GEEKYBOT_DATA_SYNCHRONIZE_ERROR');
    define('GEEKYBOT_EXPORT', 'GEEKYBOT_EXPORT');
    define('GEEKYBOT_EXPORT_ERROR', 'GEEKYBOT_EXPORT_ERROR');
    define('GEEKYBOT_REQUIRED', 'GEEKYBOT_REQUIRED');
    define('GEEKYBOT_REQUIRED_ERROR', 'GEEKYBOT_REQUIRED_ERROR');
    define('GEEKYBOT_NOT_REQUIRED', 'GEEKYBOT_NOT_REQUIRED');
    define('GEEKYBOT_NOT_REQUIRED_ERROR', 'GEEKYBOT_NOT_REQUIRED_ERROR');
    define('GEEKYBOT_SAVED', 'GEEKYBOT_SAVED');
    define('GEEKYBOT_SAVE_ERROR', 'GEEKYBOT_SAVE_ERROR');
    define('GEEKYBOT_THEME_SAVE_ERROR', 'GEEKYBOT_THEME_SAVE_ERROR');
    define('GEEKYBOT_THEME_SAVED', 'GEEKYBOT_THEME_SAVED');
    define('GEEKYBOT_ALREADY_ADD', 'GEEKYBOT_ALREADY_ADD');
    define('GEEKYBOT_DELETED', 'GEEKYBOT_DELETED');
    define('GEEKYBOT_DELETE_ERROR', 'GEEKYBOT_DELETE_ERROR');
    define('GEEKYBOT_VERIFIED', 'GEEKYBOT_VERIFIED');
    define('GEEKYBOT_KEY_INVALID_ERROR', 'GEEKYBOT_KEY_INVALID_ERROR');
    define('GEEKYBOT_KEY_EMPTY_ERROR', 'GEEKYBOT_KEY_EMPTY_ERROR');

    define('GEEKYBOT_UN_VERIFIED', 'GEEKYBOT_UN_VERIFIED');
    define('GEEKYBOT_VERIFIED_ERROR', 'GEEKYBOT_VERIFIED_ERROR');
    define('GEEKYBOT_UN_VERIFIED_ERROR', 'GEEKYBOT_UN_VERIFIED_ERROR');
    define('GEEKYBOT_INVALID_REQUEST', 'GEEKYBOT_INVALID_REQUEST');
    define('GEEKYBOT_SAVE_STORY_NAME_ERROR', 'GEEKYBOT_SAVE_STORY_NAME_ERROR' );
    define('GEEKYBOT_SAVE_INTENT_ARRAY_ERROR', 'GEEKYBOT_SAVE_INTENT_ARRAY_ERROR' );
    define('GEEKYBOT_CONFIGURATION_SAVED','GEEKYBOT_CONFIGURATION_SAVED');
    define('GEEKYBOT_CONFIGURATION_SAVE_ERROR','GEEKYBOT_CONFIGURATION_SAVE_ERROR');
    define('GEEKYBOT_RESET','GEEKYBOT_RESET');
    define('GEEKYBOT_INTENT',1);
    define('CONFIGURATION',28);
    define('EMAILTEMPLATE',29);
    define('GEEKYBOT_PLUGIN_PATH', plugin_dir_path( __DIR__ ));
    define('GEEKYBOT_PLUGIN_URL', plugin_dir_url( __DIR__ ));
    define('GEEKYBOT_PLUGIN_VERSION', '1.0.0');
    define('GEEKYBOT_ALLOWED_TAGS', array(
        'div'      => array(
            'class'  => array(),
            'id' => array(),
            'data-sitekey' => array(),
            'title' => array(),
            'role' => array(),
            'onclick' => array(),
            'onmouseout' => array(),
            'onmouseover' => array(),
            'data-section' => array(),
            'data-sectionid' => array(),
            'data-sitekey' => array(),
            'data-boxid' => array(),
            'data-per' => array(),
            'data-nonce' => array(),
            'style' => array(),
            'data-templateid' => array(),
        ),
        'section'      => array(
            'class'  => array(),
            'id' => array(),
            'style' => array(),
        ),
        'button'      => array(
            'class'  => array(),
            'id' => array(),
            'type' => array(),
            'title' => array(),
            'role' => array(),
            'data-dismiss' => array(),
            'aria-label' => array(),
            'style' => array(),
        ),
        'i'      => array(
            'class'  => array(),
            'id' => array(),
            'style' => array(),
        ),
        'h1'      => array(
            'class'  => array(),
            'id' => array(),
            'style' => array(),
        ),
        'h2'      => array(
            'class'  => array(),
            'id' => array(),
            'style' => array(),
        ),
        'h3'      => array(
            'class'  => array(),
            'id' => array(),
            'style' => array(),
        ),
        'h4'      => array(
            'class'  => array(),
            'id' => array(),
            'style' => array(),
        ),
        'h5'      => array(
            'class'  => array(),
            'id' => array(),
            'style' => array(),
        ),
        'h6'      => array(
            'class'  => array(),
            'id' => array(),
            'style' => array(),
        ),
        'font'      => array(
            'class'  => array(),
            'id' => array(),
            'style' => array(),
        ),
        'span'      => array(
            'class'  => array(),
            'id' => array(),
            'aria-hidden' => array(),
            'style' => array(),
            'onclick' => array(),
            'data-intent' => array(),
        ),
        'input'      => array(
            'type'  => array(),
            'id' => array(),
            'class' => array(),
            'name' => array(),
            'value' => array(),
            'onclick' => array(),
            'onchange' => array(),
            'data-validation' => array(),
            'required' => array(),
            'size' => array(),
            'placeholder' => array(),
            'checked' => array(),
            'autocomplete' => array(),
            'multiple' => array(),
            'rel' => array(),
            'maxlength' => array(),
            'disabled' => array(),
            'readonly' => array(),
            'credit_userid' => array(),
            'data-dismiss' => array(),
            'data-validation-optional' => array(),
            'style' => array(),
            'title' => array(),
            'pattern' => array(),
        ),
        'textarea'     => array(
            'rows' => array(),
            'name' => array(),
            'class' => array(),
            'id' => array(),
            'value' => array(),
            'cols' => array(),
            'data-validation' => array(),
            'autocomplete' => array(),
            'placeholder' => array(),
            'style' => array(),
        ),
        'button'      => array(
            'type'  => array(),
            'id' => array(),
            'class' => array(),
            'name' => array(),
            'value' => array(),
            'onclick' => array(),
            'data-validation' => array(),
            'required' => array(),
            'data-dismiss' => array(),
            'style' => array(),
        ),
        'select'      => array(
            'id' => array(),
            'class' => array(),
            'name' => array(),
            'onchange' => array(),
            'data-validation' => array(),
            'required' => array(),
            'multiple' => array(),
            'style' => array(),
            'onclick' => array(),
        ),
        'option'      => array(
            'id' => array(),
            'class' => array(),
            'name' => array(),
            'value' => array(),
            'selected' => array(),
            'style' => array(),
            'disabled' => array(),
        ),
        'img'      => array(
            'src'  => array(),
            'id' => array(),
            'class' => array(),
            'onclick' => array(),
            'alt' => array(),
            'title' => array(),
            'width' => array(),
            'height' => array(),
            'border' => array(),
            'style' => array(),
        ),
        'link'      => array(
            'src'  => array(),
            'id' => array(),
            'rel' => array(),
            'href' => array(),
            'media' => array(),
            'style' => array(),
        ),
        'meta'      => array(
            'property'  => array(),
            'content' => array(),
            'style' => array(),
        ),
        'a'      => array(
            'href'  => array(),
            'title' => array(),
            'onclick' => array(),
            'id' => array(),
            'class' => array(),
            'name' => array(),
            'data-toggle' => array(),
            'data-id' => array(),
            'data-name' => array(),
            'data-email' => array(),
            'data-type' => array(),
            'message' => array(),
            'confirmmessage' => array(),
            'data-for' => array(),
            'data-sortby' => array(),
            'data-image1' => array(),
            'data-image2' => array(),
            'data-scrolltask' => array(),
            'data-offset' => array(),
            'target' => array(),
            'data-tab-number' => array(),
            'style' => array(),
        ),
        'ul'      => array(
            'type'  => array(),
            'id' => array(),
            'class' => array(),
            'style' => array(),
        ),
        'ol'      => array(
            'id' => array(),
            'class' => array(),
            'style' => array(),
        ),
        'li'      => array(
            'id' => array(),
            'class' => array(),
            'style' => array(),
        ),
        'dl'      => array(
            'id' => array(),
            'class' => array(),
            'style' => array(),
        ),
        'dt'      => array(
            'id' => array(),
            'class' => array(),
            'style' => array(),
        ),
        'dd'      => array(
            'id' => array(),
            'class' => array(),
            'style' => array(),
        ),
        'table'      => array(
            'id' => array(),
            'class' => array(),
            'style' => array(),
        ),
        'tr'      => array(
            'id' => array(),
            'class' => array(),
            'style' => array(),
        ),
        'td'      => array(
            'id' => array(),
            'class' => array(),
            'style' => array(),
        ),
        'th'      => array(
            'id' => array(),
            'class' => array(),
            'style' => array(),
        ),
        'p'      => array(
            'id' => array(),
            'class' => array(),
            'style' => array(),
            'onclick' => array(),
        ),
        'form'      => array(
            'id' => array(),
            'class' => array(),
            'method' => array(),
            'action' => array(),
            'enctype' => array(),
        ),
        'label'      => array(
            'id' => array(),
            'class' => array(),
            'for' => array(),
            'onclick' => array(),
            'style' => array(),
        ),
        'i'     => array(
            'id' => array(),
            'class' => array(),
            'style' => array(),
        ),
        'script'     => array(
            'type' => array(),
            'class' => array(),
            'style' => array(),
        ),
        'br'     => array(
            'style' => array(),),
        'hr'     => array(
            'style' => array(),),
        'b'     => array(
            'style' => array(),),
        'em'     => array(
            'style' => array(),),
        'strong' => array(
            'style' => array(),
        ),
        'small' => array(
            'style' => array(),
        ),
        'del' => array(
            'aria-hidden' => array(),
        ),
        'ins' => array(
            'aria-hidden' => array(),
        ),
        'bdi' => array(),
        ' ' => array(),
        '&nbsp' => array(),
    ));
}
?>
