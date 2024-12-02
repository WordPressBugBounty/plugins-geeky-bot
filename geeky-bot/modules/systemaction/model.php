<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTsystemactionModel {
    function getMessagekey(){
        $key = 'systemaction'; if(is_admin()){$key = 'admin_'.$key;}return $key;
    }

    function __construct(){
        add_filter( 'wp_ajax_resetPassword',array( $this,'geekybot_resetPassword'), 10, 2);
        add_filter( 'wp_ajax_SendChatToAdmin',array( $this,'geekybot_SendChatToAdmin'));
    }

    function geekybot_resetPassword($msg, $data) {
        $post_name = $data;
        reset($post_name);
        $first_key = key($post_name);
        if (isset($post_name[$first_key])) {
            $first_value = $post_name[$first_key];
        } else {
            $first_value = '';
        }
        
        $category_name = $first_value;
        $username_or_email = $first_value; // Replace with the username or email
        $user_data = get_user_by( 'login', $username_or_email ); // Get user by username
        if ( ! $user_data ) {
            $user_data = get_user_by( 'email', $username_or_email ); // Get user by email
            if ( ! $user_data ) {
                $emailFound = $this->checkIsMessageContainEmail($msg);
                if (isset($emailFound) && $emailFound != '') {
                    $user_data = get_user_by( 'email', $emailFound ); // Get user by email
                }
            }
        }
        if ( $user_data ) {
            $returnData = $this->sendRestLinkToUserThroughEmail($user_data);
            // if user found then send reset link
            return $returnData;
            die();
        } else {
            return __("Invalid username or email.", "geeky-bot");
            die();
        }
    }

    function sendRestLinkToUserThroughEmail($user_data) {
        $user_login = $user_data->user_login; // Ensure correct case for email
        $key = get_password_reset_key( $user_data );  // Generate reset key
        // Trigger the email notification using the key and login
        $result = do_action( 'retrieve_password', $user_login, $key );
        if ( is_wp_error( $result ) ) {
            $errors = $result->get_error_messages(); // Grab the error messages
            $error_message = $errors[0]; // Assuming there's only one error
            error_log( "Password reset error for user '$user_login': $error_message" );
            return __("Looks like there was a problem resetting your password:", "geeky-bot")." ".$error_message;
            die();
        } else {
            $user = get_user_by( 'login', $user_login );
            if ( ! $user ) {
                return  __("User not found!", "geeky-bot");
            }

            $email = $user->user_email;
            $reset_url = network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user_login ), 'login' );

            $message = "Hello,\n\n";
            $message .= "You asked us to reset your password for your account using the email address $email.\n\n";
            $message .= "To reset your password, click the following link:\n\n";
            $message .= "$reset_url\n\n";
            $message .= "If you didn't request this change, you can safely ignore this email.\n\n";
            $message .= "Thanks!\n";
            wp_mail( $email, 'Password Reset Request', $message );

            return __("Okay, I have sent the recovery email. Thank you for your patience.", "geeky-bot");
            die();
        }
    }

    function checkIsMessageContainEmail($message) {
        $email = '';
        $pattern = '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/';
        // Use preg_match_all to find all email addresses in the sentence
        if (preg_match_all($pattern, $message, $matches)) {
            // $matches[0] will contain an array of all matched email addresses
            if (isset($matches[0][0])) {
                $email = $matches[0][0]; // Get user by email
            }
        }
        return $email;
    }

    function geekybot_SendChatToAdmin() {
        if(isset($_COOKIE['geekybot_chat_id'])){
            $chatId = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();  
            $query = "SELECT sessionmsgvalue  FROM `" . geekybot::$_db->prefix . "geekybot_sessiondata` WHERE usersessionid = '".esc_sql($chatId)."' and sessionmsgkey = 'chathistory'";
            $conversion = geekybotdb::GEEKYBOT_get_var($query);
            if ($conversion != null) {
                // Prepare the email details
                $message = html_entity_decode($conversion);
                
                // (Optional) Add attachments if any
                $attachments = array(); // Add file paths if needed

                // Send the email
				$subject = "GeekyBot chat notification";
                // Get the admin email address
                $admin_email = get_option('admin_email');
				$site_title = get_bloginfo( 'name' );
				$to = $admin_email;
				$senderEmail = $admin_email;
				$senderName = $site_title;
                $headers[] = 'From: ' . $senderName . ' <' . $senderEmail . '>' . "\r\n";
              	$attachments = array();
              	if(!wp_mail($to, $subject, $message, $headers)){
                }

                return __("Your query is sent to the admin.", "geeky-bot");
                die();
            }
        }
    }
}
?>
