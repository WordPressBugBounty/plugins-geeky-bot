REPLACE INTO `#__geekybot_config` (`configname`, `configvalue`, `configfor`) VALUES ('versioncode','1.2.4','default');

CREATE TABLE IF NOT EXISTS `#__geekybot_zywrap_use_cases` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `code` varchar(255) NOT NULL,
              `name` varchar(255) NOT NULL,
              `description` text,
              `category_code` varchar(255) DEFAULT NULL,
              `schema_data` json DEFAULT NULL,
              `ordering` int(11) DEFAULT NULL,
              `status` tinyint(1) DEFAULT 1,
              UNIQUE KEY `code` (`code`),
              KEY `category_code` (`category_code`),
              PRIMARY KEY (`id`),
              CONSTRAINT `fk_zywrap_uc_cat`
                  FOREIGN KEY (`category_code`)
                  REFERENCES `#__geekybot_zywrap_categories` (`code`)
                    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `#__geekybot_zywrap_wrappers` DROP FOREIGN KEY `fk_zywrap_wrappers_cat`;

ALTER TABLE `#__geekybot_zywrap_wrappers` ADD `use_case_code` varchar(255) DEFAULT NULL AFTER `description`;

ALTER TABLE `#__geekybot_zywrap_wrappers` DROP COLUMN `category_code`;

ALTER TABLE `#__geekybot_zywrap_wrappers` ADD CONSTRAINT `fk_zywrap_wrappers_uc` FOREIGN KEY (`use_case_code`) REFERENCES `#__geekybot_zywrap_use_cases` (`code`) ON DELETE SET NULL;

ALTER TABLE `#__geekybot_zywrap_logs` ADD `trace_id` varchar(255) DEFAULT NULL AFTER `id`;
ALTER TABLE `#__geekybot_zywrap_logs` ADD `credits_used` bigint(20) DEFAULT 0 AFTER `total_tokens`;
ALTER TABLE `#__geekybot_zywrap_logs` ADD `latency_ms` int(11) DEFAULT 0 AFTER `credits_used`;
