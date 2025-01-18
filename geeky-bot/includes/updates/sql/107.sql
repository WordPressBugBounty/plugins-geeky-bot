REPLACE INTO `#__geekybot_config` (`configname`, `configvalue`, `configfor`) VALUES ('versioncode','1.0.7','default');
REPLACE INTO `#__geekybot_config` (`configname`, `configvalue`, `configfor`) VALUES ('default_message_buttons','','default');

INSERT INTO `#__options` (`option_name`, `option_value`, `autoload`) VALUES ('geekybot_woocommerce_synchronize_available', '1', 'yes');

ALTER TABLE `#__geekybot_posts` ADD `taxonomy` text DEFAULT NULL;
ALTER TABLE `#__geekybot_products` ADD `product_title` varchar(2000) DEFAULT NULL;
ALTER TABLE `#__geekybot_products` ADD `product_taxonomy` varchar(5000) DEFAULT NULL;
ALTER TABLE `#__geekybot_stories` ADD `default_fallback_buttons` text DEFAULT NULL AFTER `default_fallback`;
ALTER TABLE `#__geekybot_intents_fallback` ADD `default_fallback_buttons` text DEFAULT NULL AFTER `default_fallback`;
