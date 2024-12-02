REPLACE INTO `#__geekybot_config` (`configname`, `configvalue`, `configfor`) VALUES ('versioncode','1.0.4','default');
INSERT INTO `#__geekybot_config` (`configname`, `configvalue`, `configfor`) VALUES ('is_new_post_type_enable', '1', 'default');
INSERT INTO `#__geekybot_config` (`configname`, `configvalue`, `configfor`) VALUES ('welcome_message_img', '0', 'default');
INSERT INTO `#__options` (`option_name`, `option_value`, `autoload`) VALUES ('geekybot_synchronize_available', '1', 'yes');

ALTER TABLE `#__geekybot_supported_plugins` RENAME TO `#__geekybot_post_types`;
ALTER TABLE `#__geekybot_post_types` ADD `status` tinyint(1) DEFAULT 1;
UPDATE `#__geekybot_stories` SET status = 0 WHERE story_type = 3;
