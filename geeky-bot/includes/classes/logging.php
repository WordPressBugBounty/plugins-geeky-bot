<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTlogging{
    // define log file
    // private $GEEKYBOTlog_file = '../geekybot_log/chatserver.log';
	private $GEEKYBOTlogdir_path = ABSPATH.'wp-content/plugins/geeky-bot/geekybot_log/';
    private $GEEKYBOTlog_file =  ABSPATH.'wp-content/plugins/geeky-bot/geekybot_log/chatserver.log';
    // private $GEEKYBOTlog_file = 'chatserver.log';
    
    private $GEEKYBOTfp = null;
    public function GEEKYBOTlwrite($message){
        
    }
    // open log file
    private function GEEKYBOTlopen(){
        
    }
}

$log = new GEEKYBOTlogging();
// $log->GEEKYBOTlwrite($message);


?>
