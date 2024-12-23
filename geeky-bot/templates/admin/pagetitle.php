<?php
/**
 * @param module 		module name - optional
 * module => id
 *layouts => from which layouts
 */
if (!defined('ABSPATH'))
    die('Restricted Access');
?>
<?php
$html ='';

if ($module) {
	$html.= '<div id="geekybot-head">';
		switch ($layouts) {
			case 'controlpanel':
				$html.='<h1 class="geekybot-head-text">'. esc_html(__('Dashboard', 'geeky-bot')) .'</h1>
	        			<a class="geekybot-add-link orange-bg button" href="'.esc_url(wp_nonce_url("admin.php?page=geekybot_intent&geekybotlt=formintent","intents")) .'" title="'. esc_attr(__('add intent','geeky-bot')) .'">
	        				<img src="'. esc_url(GEEKYBOT_PLUGIN_URL) .'includes/images/control_panel/plus-icon.png" alt="'. esc_attr(__('plus icon','geeky-bot')) .'" />
	        				'. esc_html(__('Add Intents','geeky-bot')).'
	        			</a>';
			break;
            case 'step1':
				$html.='<h1 class="geekybot-head-text">'. esc_html(__('Keys', 'geeky-bot')) .'</h1>';
				$html.='<p class="geekybot-head-text">'. esc_html(__('On the responses listing page, all the responses are listed here; you can check them out.', 'geeky-bot')) .'</p>';
			break;
            case 'intents':
				$html.='<h1 class="geekybot-head-text">'. esc_html(__('Intents', 'geeky-bot')) .'</h1>
						';
			break;
			case 'formintents':
				$html.='<h1 class="geekybot-head-text">'. esc_html(__('Add Intent', 'geeky-bot')) .'</h1>';
			break;
            case 'forms':
				$html.='<h1 class="geekybot-head-text">'. esc_html(__('Forms', 'geeky-bot')) .'</h1>';
				$html.='<p class="geekybot-head-text">'. esc_html(__('On the form listing page, all the forms are listed here; you can check them out.', 'geeky-bot')) .'</p>';
			break;
			case 'formforms':
				$html.='<h1 class="geekybot-head-text">'. esc_html(__('Add Forms', 'geeky-bot')) .'</h1>';
				$html.='<p class="geekybot-head-text">'. esc_html(__('On the add form page, you can add a new form.', 'geeky-bot')) .'</p>';
			break;
            case 'responses':
				$html.='<h1 class="geekybot-head-text">'. esc_html(__('Responses', 'geeky-bot')) .'</h1>';
				$html.='<p class="geekybot-head-text">'. esc_html(__('On the responses listing page, all the responses are listed here; you can check them out.', 'geeky-bot')) .'</p>';
			break;
			case 'formresponses':
				$html.='<h1 class="geekybot-head-text">'. esc_html(__('Add Response', 'geeky-bot')) .'</h1>';
				$html.='<p class="geekybot-head-text">'. esc_html(__('On the add Response page, you can add a new Response.', 'geeky-bot')) .'</p>';
			break;
			case 'action':
				$html.='<h1 class="geekybot-head-text">'. esc_html(__('Actions', 'geeky-bot')) .'</h1>';
				$html.='<p class="geekybot-head-text">'. esc_html(__('On the actions listing page, all the actions are listed here; you can check them out.', 'geeky-bot')) .'</p>';
			break;
            case 'formaction':
				$html.='<h1 class="geekybot-head-text">'. esc_html(__('Add  Action', 'geeky-bot')) .'</h1>';
				$html.='<p class="geekybot-head-text">'. esc_html(__('On the add action page, you can add a new action.', 'geeky-bot')) .'</p>';
			break;
            case 'slots':
				$html.='<h1 class="geekybot-head-text">'. esc_html(__('Variables', 'geeky-bot')) .'</h1>';
			break;
            case 'formslots':
            	if (isset(geekybot::$_data[0]->id)) {
                    $html.='<h1 class="geekybot-head-text">'. esc_html(__('Edit Variable', 'geeky-bot')) .'</h1>';
                } else {
                    $html.='<h1 class="geekybot-head-text">'. esc_html(__('Add Variable', 'geeky-bot')) .'</h1>';
                }
			break;
            case 'websearch':
				$html.='<h1 class="geekybot-head-text">'. esc_html(__('AI Web Search', 'geeky-bot')) .'</h1>';
			break;
            case 'formwebsearch':
            	if (isset(geekybot::$_data[0]->id)) {
                    $html.='<h1 class="geekybot-head-text">'. esc_html(__('Edit Post Type', 'geeky-bot')) .'</h1>';
                }
			break;
            case 'formintentgroup':
				$html.='<h1 class="geekybot-head-text">'. esc_html(__('Add Intent Group', 'geeky-bot')) .'</h1>';
			break;
			case 'formstory':
				$html.='';
			break;
			case 'stories':
				$html.='<h1 class="geekybot-head-text">'. esc_html(__('Stories', 'geeky-bot')) .'</h1>
						';
			break;
			case 'chathistory':
				$html.='<h1 class="geekybot-head-text">'. esc_html(__('Chat History', 'geeky-bot')) .'</h1>';
			break;
			case 'addintent':
				$html.='<h1 class="geekybot-head-text">'. esc_html(__('Add Intent', 'geeky-bot')) .'</h1>';
			break;			
		}
	$html.=  '</div>';
	echo wp_kses($html, GEEKYBOT_ALLOWED_TAGS);
}
?>

