<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTpagination {

    static $_limit;
    static $_offset;
    static $_currentpage;

    static function GEEKYBOT_setLimit($limit){
        if(is_numeric($limit))
            self::$_limit = $limit;
    }

    static function GEEKYBOT_getLimit(){
        return (int) self::$_limit;
    }

    static function GEEKYBOT_setOffset($offset){
        if(is_numeric($offset))
            self::$_offset = $offset;
    }

    static function GEEKYBOT_getOffset(){
        return (int) self::$_offset;
    }


    static function GEEKYBOT_getPagination($total,$searchlayout = null){
        if(!is_numeric($total)) return false;
            $pagenum = absint(geekybot::GEEKYBOT_sanitizeData(GEEKYBOTrequest::GEEKYBOT_getVar('pagenum','get',1)));
        if(!self::GEEKYBOT_getLimit()){
            //dont know whats the problem but need to be check why it is not showing data.       die(geekybot::$_configuration['pagination_default_page_size']);
            $limit2 = 0;
            $limit = geekybot::$_db->get_var("SELECT configvalue FROM `".geekybot::$_db->prefix."geekybot_config` WHERE configname = 'pagination_default_page_size'");
            if ($limit)
                $limit2 = $limit;
            self::GEEKYBOT_setLimit($limit2); // number of rows in page
        }
        self::$_offset = ( $pagenum - 1 ) * self::$_limit;
        self::$_currentpage = $pagenum;
        $num_of_pages = 1;
        if (self::$_limit > 0) {
            $num_of_pages = ceil($total / self::$_limit);
        }
        $result = '';
        if(is_admin()){
            $result = paginate_links(array(
                'base' => add_query_arg('pagenum', '%#%'),
                'format' => '',
                'prev_next' => true,
                'prev_text' => '<img class="geeky-pagnumber-previcon" src="' . esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/previous.png" title="'.esc_attr(__("Previous", "geeky-bot")).'" alt="'.esc_attr(__("Previous", "geeky-bot")).'" />',
                'next_text' => '<img class="geeky-pagnumber-nexticon" src="' . esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/next.png" title="'.esc_attr(__("Next", "geeky-bot")).'" alt="'.esc_attr(__("Next", "geeky-bot")).'" />',
                'total' => $num_of_pages,
                'current' => $pagenum,
                'add_args' => false,
            ));
        } else {            
            if($searchlayout != null && get_option( 'permalink_structure' ) != ""){
                $layargs = add_query_arg(array('pagenum'=>'%#%' , 'geekybotlay'=>$searchlayout));
            }else{
                $layargs = add_query_arg(array('pagenum'=>'%#%'));
            }
            $result = paginate_links(array(
                        'base' => $layargs,
                        'format' => '',
                        'prev_next' => true,
                        'prev_text' => '<img class="geeky-pagnumber-previcon" src="' . esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/previous.png "title="'.esc_attr(__("Previous", "geeky-bot")).'" alt="'.esc_attr(__("Previous", "geeky-bot")).'" />',
                        'next_text' => '<img class="geeky-pagnumber-nexticon" src="' . esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/next.png "title="'.esc_attr(__("Next", "geeky-bot")).'" alt="'.esc_attr(__("Next", "geeky-bot")).'" />',
                        'total' => $num_of_pages,
                        'current' => $pagenum,
                        'add_args' => false,
                    ));
        }
        return $result;
    }

    static function GEEKYBOT_isLastOrdering($total, $pagenum) {
        $maxrecord = $pagenum * geekybot::$_configuration['pagination_default_page_size'];
        if ($maxrecord >= $total)
            return false;
        else
            return true;
    }

}

?>
