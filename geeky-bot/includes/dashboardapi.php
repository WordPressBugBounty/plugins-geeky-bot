<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

//total stats widget start
function GEEKYBOT_dashboard_widgets_totalstats() {

    wp_add_dashboard_widget(
            'geekybot_dashboard_widgets_totalstats', // Widget slug.
            __('Total Stats', 'geeky-bot'), // Title.
            'geekybot_dashboard_widget_function_totalstats' // Display function.
    );
}

add_action('wp_dashboard_setup', 'geekybot_dashboard_widgets_totalstats');

function GEEKYBOT_dashboard_widget_function_totalstats() {
    geekybot::addStyleSheets();
    $data = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->widgetTotalStatsData();
    if ($data == true) {
        $html = '<div id="geeky-bot-widget-wrapper">
					<div class="total-stats-widget-data">
						<img class="total-intent" src="' . esc_url(GEEKYBOT_PLUGIN_URL) . '/includes/images/control_panel/admin-widgets/companies.png"/>
						<div class="widget-data-right">
							<div class="data-number">
								' . esc_html(geekybot::$_data['widget']['totalintents']->totalintents) . '
							</div>
							<div class="data-text">
								' . esc_html(__('Intents', 'geeky-bot')) . '
							</div>
						</div>
					</div>
				</div>';
        echo wp_kses($html, GEEKYBOT_ALLOWED_TAGS);
    } else {
    	$msg = esc_html(__('No record found','geeky-bot'));
        GEEKYBOTlayout::GEEKYBOT_getNoRecordFound($msg);
    }
}

//total stats widge end;
//
?>