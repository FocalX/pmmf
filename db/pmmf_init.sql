CREATE DATABASE `pmmf_local`;

USE `pmmf_local`;



CREATE TABLE `auths` (
  `user_id` int(8) unsigned NOT NULL,
  `area` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `user_type` tinyint(1) unsigned NOT NULL,
  `auth_token` varchar(128) DEFAULT NULL,
  `auth_token_expiry` datetime DEFAULT NULL,
  `this_logged_datetime` datetime DEFAULT NULL,
  `last_logged_datetime` datetime DEFAULT NULL,
  `current_refresh_token` varchar(128) DEFAULT NULL,
  `previous_refresh_token` varchar(128) DEFAULT NULL,
  `refresh_token_expiry` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`,`area`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `users` (
  `id` int(8) unsigned NOT NULL AUTO_INCREMENT,
  `password` blob NOT NULL,
  `email` varchar(64) DEFAULT NULL,
  `handle` varchar(64) DEFAULT NULL,
  `type` tinyint(1) unsigned NOT NULL,
  `status` tinyint(1) unsigned NOT NULL,
  `created_datetime` datetime NOT NULL,
  `last_updated_datetime` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `handle` (`handle`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
