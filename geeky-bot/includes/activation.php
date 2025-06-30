<?php

if (!defined('ABSPATH'))
    die('Restricted Access');
class GEEKYBOTactivation {

    static function GEEKYBOT_activate() {
        // Install Database
        GEEKYBOTactivation::GEEKYBOT_runSQL();
        GEEKYBOTactivation::GEEKYBOT_checkUpdates();
        GEEKYBOTactivation::GEEKYBOT_addCapabilites();
    }

    static private function GEEKYBOT_checkUpdates() {
        include_once GEEKYBOT_PLUGIN_PATH . 'includes/updates/updates.php';
        GEEKYBOTupdates::GEEKYBOT_checkUpdates();
    }

    static private function GEEKYBOT_addCapabilites() {

        $role = get_role( 'administrator' );
        $role->add_cap( 'geekybot' );
        $role->add_cap( 'geekybot_cart' );
    }

    static private function GEEKYBOT_runSQL() {
        $query = "CREATE TABLE IF NOT EXISTS `".geekybot::$_db->prefix."geekybot_config` (
                  `configname` varchar(100) NOT NULL DEFAULT '',
                  `configvalue` varchar(255) NOT NULL DEFAULT '',
                  `configfor` varchar(50) DEFAULT NULL,
                  `addon` varchar(100) DEFAULT NULL,
                  PRIMARY KEY (`configname`),
                  FULLTEXT KEY `config_name` (`configname`),
                  FULLTEXT KEY `config_for` (`configfor`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
        geekybot::$_db->query($query);
        
        $runConfig = geekybot::$_db->get_var("SELECT COUNT(configname) FROM `" . geekybot::$_db->prefix . "geekybot_config`");
        if ($runConfig == 0) {
            $query = "CREATE TABLE IF NOT EXISTS `" . geekybot::$_db->prefix . "geekybot_intents` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(100) NOT NULL,
            `user_messages` varchar(1000) NOT NULL,
            `user_messages_text` varchar(1000) NOT NULL,
            `group_id` int(11) NOT NULL,
            `story_id` int(11) DEFAULT NULL,
            `created` datetime NOT NULL,
            PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
            geekybot::$_db->query($query);

            $query = "ALTER TABLE `" . geekybot::$_db->prefix . "geekybot_intents` ADD FULLtext(user_messages)";
            geekybot::$_db->query($query);

            $query = "ALTER TABLE `" . geekybot::$_db->prefix . "geekybot_intents` ADD FULLtext(user_messages_text)";
            geekybot::$_db->query($query);

            $query = "CREATE TABLE IF NOT EXISTS `" . geekybot::$_db->prefix . "geekybot_intents_fallback` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `group_id` int(11) NOT NULL,
            `story_id` int(11) NOT NULL,
            `default_fallback` text NOT NULL,
            `default_fallback_buttons` text NOT NULL,
            PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
            geekybot::$_db->query($query);

            $query = "CREATE TABLE IF NOT EXISTS `" . geekybot::$_db->prefix . "geekybot_slots` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `name` varchar(100) NOT NULL,
              `type` varchar(255) NOT NULL,
              `possible_values` text NOT NULL,
              `variable_for` varchar(255) NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
            geekybot::$_db->query($query);

            $query = "CREATE TABLE IF NOT EXISTS `" . geekybot::$_db->prefix . "geekybot_actions` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `function_name` varchar(255) NOT NULL,
                `parameters` text NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
            geekybot::$_db->query($query);

            $query = "CREATE TABLE IF NOT EXISTS `" . geekybot::$_db->prefix . "geekybot_chat_history_sessions` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` text NOT NULL,
                `chat_id` varchar(250) NOT NULL,
                `created` datetime NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
            geekybot::$_db->query($query);

            $query = "CREATE TABLE IF NOT EXISTS `" . geekybot::$_db->prefix . "geekybot_chat_history_messages` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `response_id` int(11) NOT NULL,
                `intent_id` int(11) NOT NULL,
                `subject` text NOT NULL,
                `message` text NOT NULL,
                `sender` varchar(50) NOT NULL,
                `confidence` float NOT NULL,
                `type` varchar(100) NOT NULL,
                `post_type` varchar(255) NULL,
                `buttons` varchar(1000) NULL,
                `created` datetime NOT NULL,
                `session_id` int(11) NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
            geekybot::$_db->query($query);

            $query = "CREATE TABLE IF NOT EXISTS `" . geekybot::$_db->prefix . "geekybot_stories` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `intents_ordering` varchar(255) NOT NULL,
                `intent_ids` MEDIUMTEXT NOT NULL,
                `is_form` int(11) NOT NULL,
                `form_ids` varchar(255) NOT NULL,
                `story_mode` int(11) NOT NULL,
                `default_fallback` TEXT NULL,
                `default_fallback_buttons` TEXT NULL,
                `positions_array` MEDIUMTEXT NULL,
                `story_type` int(11) DEFAULT '1',
                `status` tinyint(1) DEFAULT '1',
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
            geekybot::$_db->query($query);

            $query = "CREATE TABLE IF NOT EXISTS `" . geekybot::$_db->prefix . "geekybot_active_chat` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `chat_id` varchar(250) NOT NULL,
                `created` datetime NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
            geekybot::$_db->query($query);

            $query = "INSERT INTO `" . geekybot::$_db->prefix . "geekybot_active_chat` (`id`, `chat_id`) VALUES
                (1, '');";
            geekybot::$_db->query($query);

            $query = "INSERT INTO  `" . geekybot::$_db->prefix . "geekybot_config` (`configname`, `configvalue`, `configfor`, `addon`) VALUES
            ('offline',  '2', 'default', NULL),
            ('data_directory',  'geekybotdata', 'default', NULL),
            ('title',   'GeekyBot',   'default',  NULL),
            ('pagination_default_page_size',    '10',   'default',  NULL),
            ('pagination_product_page_size',    '3',   'default',  NULL),
            ('versioncode', '1.1.6',    'default',  NULL),
            ('last_version',    '101',  'default',  NULL),
            ('image_file_type', 'png,jpeg,gif,jpg', 'default', NULL),
            ('bot_custom_img',  '0',    'default',  NULL),
            ('user_custom_img', '0',    'default',  NULL),
            ('welcome_message_img', '0',    'default',  NULL),
            ('ai_search_type',  '1',    'default',  NULL),
            ('ai_search',   '0',    'default',  NULL),
            ('default_message', 'Hi, I am Chatbot. I do not have specific knowledge.',    'default',  NULL),
            ('default_message_buttons', '',    'default',  NULL),
            ('customer_token',  '', 'default',  NULL),
            ('bot_name',    '', 'default',  NULL),
            ('server_ip',   '', 'default',  NULL),
            ('server_name', '', 'default',  NULL),
            ('welcome_screen', '2', 'default',  NULL),
            ('is_posts_enable', '0', 'default',  NULL),
            ('is_new_post_type_enable', '1', 'default',  NULL),
            ('auto_chat_start', '1', 'default',  NULL),
            ('auto_chat_type', '1', 'default',  NULL),
            ('auto_chat_start_time', '60', 'default',  NULL),
            ('welcome_message', 'Welcome to GeekyBot! Let me know how I can assist you today.', 'default',  NULL),
            ('show_support_link', '2', 'default',  NULL),
            ('support_link_url', '', 'default',  NULL),
            ('default_pageid',  '5',    'default',  NULL),
            ('show_abandonment_message_on_smart_popup',  '1',    'default',  NULL),
            ('show_abandonment_message_on_standard_popup',  '1',    'default',  NULL),
            ('ai_provider',  '1',    'default',  NULL),
            ('geekybot_openai_api_key',  '',    'default',  NULL),
            ('geekybot_dialogflow_project_id',  '',    'default',  NULL),
            ('geekybot_openai_model',  'gpt-3.5-turbo',    'default',  NULL),
            ('geekybot_openai_temperature',  '0.7',    'default',  NULL),
            ('geekybot_openai_max_tokens',  '150',    'default',  NULL),
            ('geekybot_sync_method',  '3',    'default',  NULL),
            ('geekybot_openrouter_api_key',  '',    'default',  NULL),
            ('geekybot_openrouter_model',  'openai/gpt-3.5-turbo',    'default',  NULL),
            ('geekybot_openrouter_temperature',  '0.7',    'default',  NULL),
            ('geekybot_openrouter_max_tokens',  '150',    'default',  NULL);";
            geekybot::$_db->query($query);

            $query = "CREATE TABLE IF NOT EXISTS `" . geekybot::$_db->prefix . "geekybot_forms` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `form_name` varchar(255) NOT NULL,
              `variables` text NOT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
            geekybot::$_db->query($query);

            $query = "CREATE TABLE IF NOT EXISTS `" . geekybot::$_db->prefix . "geekybot_intents_ranking` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `story_id` int(11) NOT NULL,
              `intent_id` int(11) NOT NULL,
              `ranking` int(11) NOT NULL,
              `intent_index` int(11) NOT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
            geekybot::$_db->query($query);

            $query = "CREATE TABLE IF NOT EXISTS `" . geekybot::$_db->prefix . "geekybot_stack` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `user_id` int(11) DEFAULT NULL,
              `chat_id` varchar(255) DEFAULT NULL,
              `intent_id` int(11) DEFAULT NULL,
              `response_id` int(11) DEFAULT NULL,
              `story_id` int(11) DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
            geekybot::$_db->query($query);

            $query = "CREATE TABLE IF NOT EXISTS `" . geekybot::$_db->prefix . "geekybot_responses` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `name` varchar(255) NOT NULL,
              `response_type` int(11) NOT NULL,
              `bot_response` varchar(1000) DEFAULT NULL,
              `response_button` varchar(1000) DEFAULT NULL,
              `form_id` int DEFAULT NULL,
              `action_id` int DEFAULT NULL,
              `function_id` int DEFAULT NULL,
              `story_id` int(11) DEFAULT NULL,
              `created` datetime NOT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
            geekybot::$_db->query($query);

            $query = "ALTER TABLE `" . geekybot::$_db->prefix . "geekybot_responses` ADD FULLtext(bot_response)";
            geekybot::$_db->query($query);

            $query = "CREATE TABLE IF NOT EXISTS `" . geekybot::$_db->prefix . "geekybot_functions` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `name` varchar(255) NOT NULL,
              `heading` text DEFAULT NULL,
              `custom_heading` text DEFAULT NULL,
              `type` tinyint(1) DEFAULT '1',
              `params` text DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
            geekybot::$_db->query($query);

            $query = "INSERT INTO `" . geekybot::$_db->prefix . "geekybot_functions` (`name`, `heading`, `custom_heading`) VALUES
                ('showAllProducts', 'Here are some suggestions.', 'Here are some suggestions.'),
                ('searchProduct', 'Here are some suggestions.', 'Here are some suggestions.'),
                ('viewCart', 'Cart Details', 'Cart Details'),
                ('checkOut', '', ''),
                ('resetPassword', '', ''),
                ('SendChatToAdmin', '', ''),
                ('showAllSaleProducts', 'Discounted Items: Shop the Best Deals Today!', 'Discounted Items: Shop the Best Deals Today!'),
                ('showAllTrendingProducts', 'Trending Now: Top Picks Just for You!', 'Trending Now: Top Picks Just for You!'),
                ('showAllLatestProducts', 'Latest Items: Discover Our Newest Additions!', 'Latest Items: Discover Our Newest Additions!'),
                ('showAllHighestRatedProducts', 'Loved by Many: Here Are Our Best-Reviewed Products!', 'Loved by Many: Here Are Our Best-Reviewed Products!'),
                ('viewOrders', 'Order Details: See What You’ve Ordered!', 'Order Details: See What You’ve Ordered!'),
                ('viewAccountDetails', 'Here are the details of your account.', 'Here are the details of your account.'),
                ('getProductsUnderPrice', 'Here are some great products that fit perfectly within your budget!', 'Here are some great products that fit perfectly within your budget!'),
                ('getProductsBetweenPrice', 'Here are some great products that fit perfectly within your budget!', 'Here are some great products that fit perfectly within your budget!'),
                ('getProductsAbovePrice', 'Here are some great products that fit perfectly within your budget!', 'Here are some great products that fit perfectly within your budget!'),
                ('orderTracking', 'Order Tracking', 'Order Tracking');";
            geekybot::$_db->query($query);


            $query = "CREATE TABLE IF NOT EXISTS `" . geekybot::$_db->prefix . "geekybot_sessiondata` (
                      `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
                      `usersessionid` char(64) NOT NULL,
                      `sessionmsgkey` text CHARACTER SET utf8 NOT NULL,
                      `sessionmsgvalue` LONGTEXT CHARACTER SET utf8 NOT NULL,
                      `sessionexpire` bigint(32) NOT NULL,
                      `productid` int(11) NULL,
                      `status` tinyint(1) DEFAULT '1'
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
            geekybot::$_db->query($query);

            
            $query = "CREATE TABLE IF NOT EXISTS `" . geekybot::$_db->prefix . "geekybot_session` (
                      `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
                      `usersessionid` char(64) NOT NULL,
                      `sessionmsg` text CHARACTER SET utf8 NOT NULL,
                      `sessionexpire` bigint(32) NOT NULL,
                      `sessionfor` varchar(125) NOT NULL,
                      `msgkey`varchar(125) NOT NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
            geekybot::$_db->query($query);

            $query = "CREATE TABLE IF NOT EXISTS `" . geekybot::$_db->prefix . "geekybot_posts` (
                      `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
                      `title` text DEFAULT NULL,
                      `taxonomy` text DEFAULT NULL,
                      `content` LONGTEXT DEFAULT NULL,
                      `post_text` LONGTEXT DEFAULT NULL,
                      `post_id` int(11) NOT NULL,
                      `post_type` varchar(20) DEFAULT NULL,
                      `status` varchar(20) NOT NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
            geekybot::$_db->query($query);

            $query = "ALTER TABLE `" . geekybot::$_db->prefix . "geekybot_posts` ADD FULLtext(post_text)";
            geekybot::$_db->query($query);

            $query = "CREATE TABLE IF NOT EXISTS `" . geekybot::$_db->prefix . "geekybot_post_types` (
                    `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
                    `post_type` varchar(100) DEFAULT NULL,
                    `post_label` varchar(100) DEFAULT NULL,
                    `plugin_name` varchar(100) DEFAULT NULL,
                    `status` tinyint(1) DEFAULT '1'
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
            geekybot::$_db->query($query);

            $query = "CREATE TABLE IF NOT EXISTS `" . geekybot::$_db->prefix . "geekybot_products` (
                    `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
                    `product_title` varchar(2000) DEFAULT NULL,
                    `product_taxonomy` varchar(5000) DEFAULT NULL,
                    `product_text` text DEFAULT NULL,
                    `product_description` text DEFAULT NULL,
                    `product_id` int(11) NOT NULL,
                    `status` varchar(20) NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
            geekybot::$_db->query($query);

            $query = "ALTER TABLE `" . geekybot::$_db->prefix . "geekybot_products` ADD FULLtext(product_text)";
            geekybot::$_db->query($query);

            $query = "ALTER TABLE `" . geekybot::$_db->prefix . "geekybot_products` ADD FULLtext(product_description)";
            geekybot::$_db->query($query);
        }
    }
}
?>
