REPLACE INTO `#__geekybot_config` (`configname`, `configvalue`, `configfor`) VALUES ('versioncode','1.1.8','default');


ALTER TABLE `#__geekybot_zywrap_categories` 
    DROP PRIMARY KEY, 
    ADD `id` int(11) NOT NULL AUTO_INCREMENT FIRST, 
    ADD PRIMARY KEY (`id`), 
    ADD UNIQUE KEY `code` (`code`), 
    ADD `status` tinyint(1) DEFAULT 1;

ALTER TABLE `#__geekybot_zywrap_languages` 
    DROP PRIMARY KEY, 
    ADD `id` int(11) NOT NULL AUTO_INCREMENT FIRST, 
    ADD PRIMARY KEY (`id`), 
    ADD UNIQUE KEY `code` (`code`), 
    ADD `status` tinyint(1) DEFAULT 1;

ALTER TABLE `#__geekybot_zywrap_ai_models` 
    DROP PRIMARY KEY, 
    ADD `id` int(11) NOT NULL AUTO_INCREMENT FIRST, 
    ADD PRIMARY KEY (`id`), 
    ADD UNIQUE KEY `code` (`code`), 
    ADD `status` tinyint(1) DEFAULT 1;

ALTER TABLE `#__geekybot_zywrap_block_templates` 
    DROP PRIMARY KEY, 
    ADD `id` int(11) NOT NULL AUTO_INCREMENT FIRST, 
    ADD PRIMARY KEY (`id`), 
    ADD UNIQUE KEY `type_code` (`type`,`code`), 
    ADD `status` tinyint(1) DEFAULT 1;

ALTER TABLE `#__geekybot_zywrap_wrappers` 
    DROP PRIMARY KEY, 
    ADD `id` int(11) NOT NULL AUTO_INCREMENT FIRST, 
    ADD PRIMARY KEY (`id`), 
    ADD UNIQUE KEY `code` (`code`), 
    ADD `status` tinyint(1) DEFAULT 1;
