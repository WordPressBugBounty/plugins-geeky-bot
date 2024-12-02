REPLACE INTO `#__geekybot_config` (`configname`, `configvalue`, `configfor`) VALUES ('versioncode','1.0.2','default');

CREATE TABLE IF NOT EXISTS `#__geekybot_supported_plugins` (`id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT, `post_type` varchar(100) DEFAULT NULL, `post_label` varchar(100) DEFAULT NULL, `plugin_name` varchar(100) DEFAULT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8;
INSERT INTO `#__geekybot_supported_plugins` (`post_type`, `post_label`, `plugin_name`) VALUES ('post', 'Posts', ''),('page', 'Pages', ''),('job_listing', 'Jobs', 'wp-job-manager'),('lp_course', 'Courses', 'learnpress'),('courses', 'Courses', 'tutor'),('epkb_post_type_1', 'Documents', 'echo-knowledge-base'),('docs', 'Documents', 'betterdocs');
