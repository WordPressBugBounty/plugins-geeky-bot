REPLACE INTO `#__geekybot_config` (`configname`, `configvalue`, `configfor`) VALUES ('versioncode','1.0.1','default');
INSERT INTO `#__geekybot_config` (`configname`, `configvalue`, `configfor`) VALUES ('offline', '2', 'default'), ('auto_chat_type', '1', 'default');

ALTER TABLE `#__geekybot_posts` ADD `post_type` varchar(20) DEFAULT NULL AFTER `post_id`;
ALTER TABLE `#__geekybot_stories` ADD `status` tinyint(1) DEFAULT 1 AFTER `story_type`;
