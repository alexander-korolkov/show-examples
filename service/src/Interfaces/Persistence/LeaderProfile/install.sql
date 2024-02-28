USE `copy_trading`;

DROP TABLE IF EXISTS `leader_profiles`;
CREATE TABLE `leader_profiles` (
    `leader_id`     INT UNSIGNED  NOT NULL UNIQUE,
    `avatar`        INT UNSIGNED  NULL DEFAULT NULL,
    `nickname`      VARCHAR(15)   NULL DEFAULT NULL UNIQUE,
    `use_nickname`  BOOLEAN       NOT NULL DEFAULT FALSE,
    `show_name`     BOOLEAN       NOT NULL DEFAULT FALSE,
    `show_country`  BOOLEAN       NOT NULL DEFAULT FALSE,
    `updated_at`    DATETIME      NOT NULL
) ENGINE=InnoDB;
