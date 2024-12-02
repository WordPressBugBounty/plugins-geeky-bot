<?php
/**
 * @param field 		fieldordering field object
 * @param title 		field title or name
 * @param required  	is field required
 * @param content 		field html
 * @param description 	field description
 */
if (!defined('ABSPATH'))
    die('Restricted Access');
if (isset($field)) {
	if (!isset($title)) {
		$title = $field->fieldtitle;
	}
	if (!isset($required)) {
		$required = $field->required;
	}
	if (!isset($description)) {
		$description = $field->description;
	}
} else {
    if (!isset($title)) {
        $title = '';
    }
    if (!isset($required)) {
        $required = false;
    }
    if (!isset($description)) {
        $description = '';
    }
}

$fullwidth_class = "";
if(isset($field->field) && $field->field == 'description') {
   $fullwidth_class = "geekybot-fullwidth";
}


?>
<div class="geekybot-form-wrapper <?php echo esc_attr($fullwidth_class); ?>">
    <div class="geekybot-form-title">
        <?php echo esc_html($title); ?>
        <?php if($required == 1): ?>
        	<span color="red">*</span>
    	<?php endif; ?>
    </div>
    <div class="geekybot-form-value">
        <?php echo esc_html($content); ?>
        <?php if(!empty($description)): ?>
        <?php endif; ?>
    </div>
    <div class="geekybot-form-description"><?php echo wp_kses($description, GEEKYBOT_ALLOWED_TAGS); ?></div>
</div>