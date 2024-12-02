<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTuploads {

    private $uploadfor;
    private $intentid;
    private $userid;

    function geekybot_upload_dir( $dir ) {
        $form_request = GEEKYBOTrequest::GEEKYBOT_getVar('form_request');
        if($form_request == 'geekybot' || $this->uploadfor=='profile'){
            $datadirectory = GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->getConfigValue('data_directory');
            // test code
            if ( ! function_exists( 'WP_Filesystem' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            global $wp_filesystem;
            if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base') ) {
                $creds = request_filesystem_credentials( site_url() );
                wp_filesystem( $creds );
            }

            $file = $dir['basedir'].'/'.$datadirectory."/index.html";
            if (@ $wp_filesystem->put_contents( $file, '', 0755 ) ) {
            }
            
            $path = $datadirectory . '/data';
            // // test code
            $file = $dir['basedir'].'/'.$path."/index.html";
            if (@ $wp_filesystem->put_contents( $file, '', 0755 ) ) {
            }
            //
            $apath = $path;
            if($this->uploadfor == 'bot'){
                $path = $path . '/bot';
            }
            elseif($this->uploadfor == 'user'){
                $path = $path . '/user';
            }else{

            }
            // // test code
            $file = $path."/index.html";
            if (@ $wp_filesystem->put_contents( $file, '', 0755 ) ) {
            }
            //

            $userpath = $path;
            $array = array(
                'path'   => $dir['basedir'] . '/' . $userpath,
                'url'    => $dir['baseurl'] . '/' . $userpath,
                'subdir' => '/'. $userpath,
            ) + $dir;
            return $array;
        }else{
            return $dir;
        }
    }

    function geekybot_uploadImage($file){
        $allowed_types = GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->getConfigValue('image_file_type');
        $allowed_size = GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->getConfigValue('image_file_size');
        return $this->geekybot_uploadFile($file, $allowed_types, $allowed_size);
    }

    function geekybot_uploadFile($file, $allowed_types, $allowed_size){
        $filetyperesult = wp_check_filetype($file['name']);
        $allowed_types  = array_map('strtolower', geekybotphplib::GEEKYBOT_explode(',', $allowed_types));
        if( !in_array(geekybotphplib::GEEKYBOT_strtolower($filetyperesult['ext']), $allowed_types) ){
            return array('error'=>__('File ext. is mismatched', 'geeky-bot'));
        }
        $filesize = $file['size'] / 1024;
        if( $filesize > $allowed_size ){
            return array('error'=>__('File size is greater then allowed file size', 'geeky-bot'));
        }
        if (!function_exists('wp_handle_upload')) {
            do_action('geekyboot_load_wp_file');
        }
        $result = wp_handle_upload($file, array('test_form' => false));
        if(!($result && !isset($result['error']))) {
            return $result;
        }
        $result['filename'] = geekybotphplib::GEEKYBOT_basename($result['file']);
        $result['ischanged'] = $result['filename'] == $file['name'] ? 0 : 1;
        $dir = wp_upload_dir();
        $dirstr = geekybotphplib::GEEKYBOT_str_replace('/'.$result['filename'], '', $result['file']);
        $i=0;
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        global $wp_filesystem;
        if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base') ) {
            $creds = request_filesystem_credentials( site_url() );
            wp_filesystem( $creds );
        }
        do{
            $file = $dirstr."/index.html";
            if (@ $wp_filesystem->put_contents( $file, '', 0755 ) ) {
            }
            $dirstr = geekybotphplib::GEEKYBOT_preg_replace('/\/[^\/]+$/', '', $dirstr);
            $i++;
        }while( $dirstr !== $dir['basedir'] && $i<20);

        return $result;
    }
}

?>