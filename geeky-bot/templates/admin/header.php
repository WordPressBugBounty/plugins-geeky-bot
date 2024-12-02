<?php
/**
 * @param module 		module name - optional
 */

if (!defined('ABSPATH'))
    die('Restricted Access');
if (!isset($module)) {
	// if module name is not passed than pick from url
	$module = GEEKYBOTrequest::GEEKYBOT_getVar('geekybotme');
}


/*
show module wise flash messages
*/
if ($module) {
	$model = GEEKYBOTincluder::GEEKYBOT_getModel($module);
	if ($model) {
		$msgkey = $model->getMessagekey();
		GEEKYBOTMessages::GEEKYBOT_getLayoutMessage($msgkey);
	}
}