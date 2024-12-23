REPLACE INTO `#__geekybot_config` (`configname`, `configvalue`, `configfor`) VALUES ('versioncode','1.0.5','default');

ALTER TABLE `#__geekybot_chat_history_messages` ADD `post_type` varchar(255) NULL;

CREATE TABLE IF NOT EXISTS `#__geekybot_intents_fallback` (
    `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT, 
    `group_id` int(11) NOT NULL,
    `story_id` int(11) NOT NULL,
    `default_fallback` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


ALTER TABLE `#__geekybot_posts` MODIFY COLUMN `content` LONGTEXT, MODIFY COLUMN `post_text` LONGTEXT;
