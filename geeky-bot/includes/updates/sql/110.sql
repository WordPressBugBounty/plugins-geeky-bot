REPLACE INTO `#__geekybot_config` (`configname`, `configvalue`, `configfor`) VALUES ('versioncode','1.1.0','default');

ALTER TABLE `#__geekybot_responses` ADD FULLtext(`bot_response`);
