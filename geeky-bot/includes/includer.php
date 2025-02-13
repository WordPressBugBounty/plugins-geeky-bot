<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTincluder {

    function __construct() {

    }

    /*
     * Includes files
     */

    public static function GEEKYBOT_include_file($filename, $module_name = null) {
        $module_name = geekybotphplib::GEEKYBOT_clean_file_path($module_name);
        $filename = geekybotphplib::GEEKYBOT_clean_file_path($filename);

        if ($module_name != null) {
            $file_path = self::GEEKYBOT_getPluginPath($module_name,'file',$filename);
            if (file_exists(GEEKYBOT_PLUGIN_PATH . 'includes/css/inc-css/' . $module_name . '-' . $filename . '.css.php')) {
                require_once(GEEKYBOT_PLUGIN_PATH . 'includes/css/inc-css/' . $module_name . '-' . $filename . '.css.php');
            }
            if(is_array($file_path) && file_exists($file_path['tmpl_file'])){
                if (file_exists($file_path['inc_file'])) {
                    require_once($file_path['inc_file']);
                }
                include_once $file_path['tmpl_file'];
            }else if(file_exists($file_path)){
                $incfilepath = geekybotphplib::GEEKYBOT_explode('.php', $file_path);
                $incfilename = $incfilepath[0].'.inc.php';
                if (file_exists($incfilename)) {
                    require_once($incfilename);
                }
                include_once $file_path; //
            }
        } else {
            $file_path = self::GEEKYBOT_getPluginPath($filename,'file');
            if(file_exists($file_path)){
                include_once $file_path; //
            }
        }
        return;
    }

    /*
     * Static function to handle the page slugs
     */

    public static function GEEKYBOT_include_slug($page_slug) {
        include_once GEEKYBOT_PLUGIN_PATH . 'modules/geeky-bot-controller.php';
    }

    /*
     * Static function for the model object
     */

    public static function GEEKYBOT_getModel($modelname) {
        $file_path = self::GEEKYBOT_getPluginPath($modelname,'model');
        include_once $file_path;
        $classname = "GEEKYBOT" . $modelname . 'Model';
        $obj = new $classname();
        return $obj;
    }

    /*
     * Static function for the classes objects
     */

    public static function GEEKYBOT_getObjectClass($classname) {
        $file_path = self::GEEKYBOT_getPluginPath($classname,'class');
        include_once $file_path;
        $classname = "GEEKYBOT" . $classname ;
        $obj = new $classname();
        return $obj;
    }

    /*
     * Static function for the classes not objects
     */

    public static function GEEKYBOT_getClassesInclude($classname) {
        $file_path = self::GEEKYBOT_getPluginPath($classname,'class');
        include_once $file_path;
    }

    /*
     * Static function for the table object
     */

    public static function GEEKYBOT_getTable($tableclass) {
        $file_path = self::GEEKYBOT_getPluginPath($tableclass,'table');
        require_once GEEKYBOT_PLUGIN_PATH . 'includes/tables/table.php';
        include_once $file_path;
        $classname = "GEEKYBOT" . $tableclass . 'Table';
        $obj = new $classname();
        return $obj;
    }

    /*
     * Static function for the controller object
     */

    public static function GEEKYBOT_getController($controllername) {
        $file_path = self::GEEKYBOT_getPluginPath($controllername,'controller');
        include_once $file_path;
        $classname = "GEEKYBOT".$controllername . "Controller";
        $obj = new $classname();
        return $obj;
    }

    public static function GEEKYBOT_getTemplate($template_name, $args = array()){
        $template = self::GEEKYBOT_locateTemplate($template_name,$args);
        if(!empty($args) && is_array($args)) {
            extract($args);
        }
        return include $template;
    }

    public static function GEEKYBOT_getTemplateHtml($template_name, $args = array()){
        ob_start();
        self::GEEKYBOT_getTemplate($template_name, $args);
        return ob_get_clean();
    }

    public static function GEEKYBOT_locateTemplate($template_name,$args= array()){
        $module = geekybotphplib::GEEKYBOT_substr($template_name, 0, geekybotphplib::GEEKYBOT_strpos($template_name, '/'));
        $template_name = geekybotphplib::GEEKYBOT_substr($template_name, geekybotphplib::GEEKYBOT_strpos($template_name, '/')+1);
        $module_name = isset($args['module_name']) ? $args['module_name'] : null;
        /* ADDONS PLUGIN DIR FOR TEMPLATE => module_name  */
        if($module_name!=null && $module_name!=""){
            //To Manage Template Working IN Addons
            if(in_array($args['module_name'], geekybot::$_active_addons)){
                $path = WP_PLUGIN_DIR.'/'.'geeky-bot-'.$args['module_name'].'/';
                $template = $path.'module/tmpl/views/'.$template_name.'.php';
            }
        }else{
            if($module == 'templates'){
                $template = GEEKYBOT_PLUGIN_PATH.'templates/'.$template_name.'.php';
            }else{
                $template = GEEKYBOT_PLUGIN_PATH.'modules/'.$module.'/tmpl/'.$template_name.'.php';
            }
        }

       return $template;
    }

    public static function GEEKYBOT_getPluginPath($module,$type,$file_name = '') {
        $module = geekybotphplib::GEEKYBOT_clean_file_path($module);
        $file_name = geekybotphplib::GEEKYBOT_clean_file_path($file_name);
        $addons_secondry = array('rating');
        if(in_array($module, geekybot::$_active_addons)){

            $path = WP_PLUGIN_DIR.'/'.'geeky-bot-'.$module.'/';
            switch ($type) {
                case 'file':
                    if($file_name != ''){
                        if (locate_template('geeky-bot/' . $module . '-' . $file_name . '.php', 0, 1)) {
                            $file_path['inc_file'] = $path . 'module/tmpl/' . $file_name . '.inc.php';
                            $file_path['tmpl_file'] = locate_template('geeky-bot/' . $module . '-' . $file_name . '.php', 0, 1);
                        }else{
                            $file_path = $path . 'module/tmpl/' . $file_name . '.php';

                        }
                    }else{
                        $file_path = $path . 'module/controller.php';
                    }
                    break;
                case 'model':
                    $file_path = $path . 'module/model.php';
                    break;
                case 'class':
                    $file_path = $path . 'classes/' . $module . '.php';
                    break;
                case 'controller':
                    $file_path = $path . 'module/controller.php';
                    break;
                case 'table':
                    $file_path = $path . 'includes/' . $module . '-table.php';
                    break;
            }

        }elseif(in_array($module, $addons_secondry)){ // to handle the case of modules that are submodules for some addon
            $parent_module = '';
            switch ($module) {// to identify addon for submodules.
                case 'rating':
                    $parent_module = 'resumeaction';
                break;
            }
                if($parent_module == "customfield" && !in_array('customfield', geekybot::$_active_addons)){
                  $path = WP_PLUGIN_DIR.'/'.'geeky-bot/includes/';
                }else{
                    $path = WP_PLUGIN_DIR.'/'.'geeky-bot-'.$parent_module.'/';

                }
            if(in_array($parent_module, geekybot::$_active_addons) || $parent_module == "customfield"){
                switch ($type) {
                    case 'file':
                        if($file_name != ''){
                            if (locate_template('geeky-bot/' . $module . '-' . $file_name . '.php', 0, 1)) {
                                $file_path['inc_file'] = $path . $module.'/tmpl/' . $file_name . '.inc.php';
                                $file_path['tmpl_file'] = locate_template('geeky-bot/' . $module . '-' . $file_name . '.php', 0, 1);
                            }else{
                                $file_path = $path . $module.'/tmpl/' . $file_name . '.php';
                            }
                        }else{
                            $file_path = $path . $module.'/controller.php';
                        }
                        break;
                    case 'model':
                        $file_path = $path . $module.'/model.php';
                        break;

                    case 'class':
                        $file_path = $path . 'classes/' . $module . '.php';
                        break;
                    case 'controller':
                        $file_path = $path . $module.'/controller.php';
                        break;
                    case 'table':
                        $file_path = $path . 'includes/' . $module . '-table.php';
                        break;
                }
            }else{
               // $file_path = self::getPluginPath('premiumplugin','file');
                }
            }else{
            $path = GEEKYBOT_PLUGIN_PATH;
            switch ($type) {
                case 'file':
                    if($file_name != ''){
                        if (locate_template('geeky-bot/' . $module . '-' . $file_name . '.php', 0, 1)) {
                            $file_path['inc_file'] = $path . 'modules/' . $module . '/tmpl/' . $file_name . '.inc.php';
                            $file_path['tmpl_file'] = locate_template('geeky-bot/' . $module . '-' . $file_name . '.php', 0, 1);
                        }else{
                            $file_path = $path . 'modules/' . $module . '/tmpl/' . $file_name . '.php';
                        }
                    }else{
                        $file_path = $path . 'modules/' . $module . '/controller.php';
                    }
                    break;
                case 'model':
                        $file_path = $path . 'modules/' . $module . '/model.php';
                    break;

                case 'class':
                    $file_path = $path . 'includes/classes/' . $module . '.php';
                    break;
                case 'controller':
                        $file_path = $path . 'modules/' . $module . '/controller.php';
                    break;
                case 'table':
                    $file_path = $path . 'includes/tables/' . $module . '.php';;
                    break;
            }
        }
        return $file_path;
    }
}

    

$includer = new GEEKYBOTincluder();
?>
