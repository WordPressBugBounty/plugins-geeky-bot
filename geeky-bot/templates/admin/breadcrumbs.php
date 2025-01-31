<?php
if (!defined('ABSPATH'))
    die('Restricted Access');
?>
<?php
$html ='';

if ($module) {
	$html.= '<div class="geekybot-breadcrumbs-wrp">';
		switch ($layouts) {
			case 'controlpanel':
				$html.='
				<a href="'. esc_url(wp_nonce_url('admin.php?page=geekybot','geeky-bot')) .'" title="'. esc_html(__('Dashboard', 'geeky-bot')) .'" class="breadcrumb">
            		<i class="fa fa-home"></i>
        		</a>';
			break;
            case 'configurations':
				$html.='
				<a href="'. esc_url(wp_nonce_url('admin.php?page=geekybot','geeky-bot')) .'" title="'. esc_html(__('Dashboard', 'geeky-bot')) .'" class="breadcrumb">
            		<i class="fa fa-home"></i>
        		</a>
        		<span class="geekybot-breadcrumbs-slash">/</span>
    			<span class="active-breadcrumb">
        			'. esc_html(__('Settings', 'geeky-bot')) .'
    			</span>';
			break;
            case 'themes':
				$html.='
				<a href="'. esc_url(wp_nonce_url('admin.php?page=geekybot','geeky-bot')) .'" title="'. esc_html(__('Dashboard', 'geeky-bot')) .'" class="breadcrumb">
            		<i class="fa fa-home"></i>
        		</a>
        		<span class="geekybot-breadcrumbs-slash">/</span>
    			<span class="active-breadcrumb">
        			'. esc_html(__('Chatbot', 'geeky-bot')) .'
    			</span>';
			break;
            case 'slots':
				$html.='
				<a href="'. esc_url(wp_nonce_url('admin.php?page=geekybot','geeky-bot')) .'" title="'. esc_html(__('Dashboard', 'geeky-bot')) .'" class="breadcrumb">
            		<i class="fa fa-home"></i>
        		</a>
        		<span class="geekybot-breadcrumbs-slash">/</span>
        		<span class="active-breadcrumb">
		            '. esc_html(__('Variables', 'geeky-bot')) .'
		        </span>';
			break;
            case 'formslots':
                $html.='
                <a href="'. esc_url(wp_nonce_url('admin.php?page=geekybot','geeky-bot')) .'" title="'. esc_html(__('Dashboard', 'geeky-bot')) .'" class="breadcrumb">
            		<i class="fa fa-home"></i>
        		</a>
        		<span class="geekybot-breadcrumbs-slash">/</span>
        		<a href="'. esc_url(wp_nonce_url('admin.php?page=geekybot_slots','geeky-bot')) .'" title="'. esc_html(__('Dashboard', 'geeky-bot')) .'" class="breadcrumb">
            		'. esc_html(__('Variables', 'geeky-bot')) .'
        		</a>
        		<span class="geekybot-breadcrumbs-slash">/</span>
        		<span class="active-breadcrumb">';
	                if (isset(geekybot::$_data[0]->id)) {
	                    $html.= esc_html(__('Edit Variable', 'geeky-bot'));
	                } else {
	                    $html.= esc_html(__('Add Variable', 'geeky-bot'));
	                }
	                $html.='
                </span>';
			break;
			case 'websearch':
				$html.='
				<a href="'. esc_url(wp_nonce_url('admin.php?page=geekybot','geeky-bot')) .'" title="'. esc_html(__('Dashboard', 'geeky-bot')) .'" class="breadcrumb">
            		<i class="fa fa-home"></i>
        		</a>
        		<span class="geekybot-breadcrumbs-slash">/</span>
        		<span class="active-breadcrumb">
		            '. esc_html(__('AI Web Search', 'geeky-bot')) .'
		        </span>';
			break;
            case 'formwebsearch':
                $html.='
                <a href="'. esc_url(wp_nonce_url('admin.php?page=geekybot','geeky-bot')) .'" title="'. esc_html(__('Dashboard', 'geeky-bot')) .'" class="breadcrumb">
            		<i class="fa fa-home"></i>
        		</a>
        		<span class="geekybot-breadcrumbs-slash">/</span>
        		<a href="'. esc_url(wp_nonce_url('admin.php?page=geekybot_websearch','geeky-bot')) .'" title="'. esc_html(__('Dashboard', 'geeky-bot')) .'" class="breadcrumb">
            		'. esc_html(__('AI Web Search', 'geeky-bot')) .'
        		</a>
        		<span class="geekybot-breadcrumbs-slash">/</span>
        		<span class="active-breadcrumb">';
	                if (isset(geekybot::$_data[0]->id)) {
	                    $html.= esc_html(__('Edit Post Type', 'geeky-bot'));
	                }
	                $html.='
                </span>';
			break;
			case 'step1':
				$html.='
				<a href="'. esc_url(wp_nonce_url('admin.php?page=geekybot','geeky-bot')) .'" title="'. esc_html(__('Dashboard', 'geeky-bot')) .'" class="breadcrumb">
            		<i class="fa fa-home"></i>
        		</a>
        		<span class="geekybot-breadcrumbs-slash">/</span>
        		<span class="active-breadcrumb">
		            '. esc_html(__('Install Add-ons', 'geeky-bot')) .'
		        </span>';
			break;
			case 'addonstatus':
				$html.='
				<a href="'. esc_url(wp_nonce_url('admin.php?page=geekybot','geeky-bot')) .'" title="'. esc_html(__('Dashboard', 'geeky-bot')) .'" class="breadcrumb">
            		<i class="fa fa-home"></i>
        		</a>
        		<span class="geekybot-breadcrumbs-slash">/</span>
        		<span class="active-breadcrumb">
		            '. esc_html(__('Add-ons Status', 'geeky-bot')) .'
		        </span>';
			break;
			case 'formstory':
				$html.='
				<a href="'. esc_url(wp_nonce_url('admin.php?page=geekybot','geeky-bot')) .'" title="'. esc_html(__('Dashboard', 'geeky-bot')) .'" class="breadcrumb">
            		<i class="fa fa-home"></i>
        		</a>
        		<span class="geekybot-breadcrumbs-slash">/</span>
        		<a href="'. esc_url(wp_nonce_url('admin.php?page=geekybot_stories&geekybotlt=stories','geeky-bot')) .'" title="'. esc_html(__('Dashboard', 'geeky-bot')) .'" class="breadcrumb">
		            '. esc_html(__('Stories', 'geeky-bot')) .'
		        </a>
		        <span class="geekybot-breadcrumbs-slash">/</span>
		        <span class="active-breadcrumb">
		            '. esc_html(__('Edit Story', 'geeky-bot')) .'
		        </span>';
			break;
			case 'stories':
				$html.='
				<a href="'. esc_url(wp_nonce_url('admin.php?page=geekybot','geeky-bot')) .'" title="'. esc_html(__('Dashboard', 'geeky-bot')) .'" class="breadcrumb">
            		<i class="fa fa-home"></i>
        		</a>
        		<span class="geekybot-breadcrumbs-slash">/</span>
        		<span class="active-breadcrumb">
	                '. esc_html(__('Stories', 'geeky-bot')) .'
	            </span>';
			break;
			case 'chathistory':
				$html.='
				<a href="'. esc_url(wp_nonce_url('admin.php?page=geekybot','geeky-bot')) .'" title="'. esc_html(__('Dashboard', 'geeky-bot')) .'" class="breadcrumb">
            		<i class="fa fa-home"></i>
        		</a>
        		<span class="geekybot-breadcrumbs-slash">/</span>
        		<span class="active-breadcrumb">
            		'. esc_html(__('Chat History', 'geeky-bot')) .'
        		</span>';
			break;
		}
	$html.=  '</div>';
	echo wp_kses($html, GEEKYBOT_ALLOWED_TAGS);
}
?>

