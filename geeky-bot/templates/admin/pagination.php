<?php
/**
* @param Pagination geekybot Admin s
*/
if (!defined('ABSPATH'))
    die('Restricted Access');
?>
<?php
if($module){
	$html = '<div class="geeky-bot-tablenav"><div class="geekybot-tablenav-pages">' . wp_kses_post($pagination) . '</div></div>';
	echo wp_kses($html, GEEKYBOT_ALLOWED_TAGS);
}

