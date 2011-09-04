DROP TABLE IF EXISTS `auth_users`;
DROP TABLE IF EXISTS `auth_login`;

/**
 * Available users for login.
 *
 * @working 2011/AUG/27 00:00
 */
CREATE TABLE `auth_users`(
  `id`      INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user` VARCHAR(8) NOT NULL,
  `pass`   CHAR(40) NOT NULL,
  `date`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE `user` (`user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;

/**
 * Logged in UUID's
 *
 * @working 2011/AUG/27 23:59
 */
CREATE TABLE `auth_login`(
  `id`        INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_user`   INT(10) UNSIGNED     NULL,
  `logged` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `uuid`     CHAR(32) NOT NULL,
  `date`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE `id_user` (`id_user`),
  UNIQUE `uuid` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;

