<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTupdates {

    static function GEEKYBOT_checkUpdates($cversion=null) {
        if (is_null($cversion)) {
            $cversion = geekybot::$_currentversion;
        }
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        global $wp_filesystem;
        if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base') ) {
            $creds = request_filesystem_credentials( site_url() );
            wp_filesystem( $creds );
        }
        $installedversion = GEEKYBOTupdates::geekybot_getInstalledVersion();
        if ($installedversion != $cversion) {
            $query = "REPLACE INTO `".geekybot::$_db->prefix."geekybot_config` (`configname`, `configvalue`, `configfor`) VALUES ('last_version','','default');";
            geekybot::$_db->query($query); //old actual
            $query = "SELECT configvalue FROM `".geekybot::$_db->prefix."geekybot_config` WHERE configname='versioncode'";
            $versioncode = geekybot::$_db->get_var($query);
            $versioncode = geekybotphplib::GEEKYBOT_str_replace('.','',$versioncode);
            $query = "UPDATE `".geekybot::$_db->prefix."geekybot_config` SET configvalue = '".esc_sql($versioncode)."' WHERE configname = 'last_version';";
            geekybot::$_db->query($query);
            $from = $installedversion + 1;
            $to = $cversion;
            if ($from != "" && $to != "") {
                for ($i = $from; $i <= $to; $i++) {
                    $installfile = GEEKYBOT_PLUGIN_PATH . 'includes/updates/sql/' . $i . '.sql';

                    // Check if the file exists
                    if ($wp_filesystem->exists($installfile)) {
                        $delimiter = ';';
                        // Get the file contents
                        $file_contents = $wp_filesystem->get_contents($installfile);
                        if ($file_contents !== false) {
                            $lines = explode("\n", $file_contents);
                            $query = array();

                            foreach ($lines as $line) {
                                $query[] = $line;
                                if (geekybotphplib::GEEKYBOT_preg_match('~' . preg_quote($delimiter, '~') . '\s*$~iS', end($query)) === 1) {
                                    $query_string = geekybotphplib::GEEKYBOT_trim(implode('', $query));
                                    $query_string = geekybotphplib::GEEKYBOT_str_replace("#__", geekybot::$_db->prefix, $query_string);
                                    if (!empty($query_string)) {
                                        geekybot::$_db->query($query_string);
                                    }
                                    $query = array();
                                }
                            }
                        } else {
                            // echo 'Failed to open file.';
                        }
                    } else {
                        // echo 'File does not exist.';
                    }
                }
            }

        }
    }

    static function geekybot_getInstalledVersion() {
        $query = "SELECT configvalue FROM `" . geekybot::$_db->prefix . "geekybot_config` WHERE configname = 'versioncode'";
        $version = geekybot::$_db->get_var($query);
        if (!$version)
            $version = '100';
        else
            $version = geekybotphplib::GEEKYBOT_str_replace('.', '', $version);
        return $version;
    }

}

?>
