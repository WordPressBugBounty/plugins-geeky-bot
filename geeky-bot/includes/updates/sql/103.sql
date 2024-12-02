REPLACE INTO `#__geekybot_config` (`configname`, `configvalue`, `configfor`) VALUES ('versioncode','1.0.3','default');

ALTER TABLE `#__geekybot_stories` MODIFY COLUMN `intent_ids` MEDIUMTEXT, MODIFY COLUMN `positions_array` MEDIUMTEXT;

INSERT INTO `#__geekybot_supported_plugins` (`post_type`, `post_label`, `plugin_name`) VALUES ('auto-listing', 'Autos', 'auto-listings'),('properties', 'Properties', 'estatik'),('forum', 'Forums', 'bbpress'),('listings', 'Motors', 'motors-car-dealership-classified-listings');
