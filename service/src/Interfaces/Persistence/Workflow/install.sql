USE `copy_trading`;

DROP TABLE IF EXISTS `workflows`;
CREATE TABLE `workflows` (
    `id`            INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `parent_id`     INT,
    `type`          VARCHAR(64) NOT NULL,
    `corr_id`       INT,
    `state`         TINYINT     NOT NULL,
    `tries`         TINYINT     NOT NULL,
    `created_at`    DATETIME    NOT NULL,
    `scheduled_at`  DATETIME    NOT NULL,
    `started_at`    DATETIME,
    `finished_at`   DATETIME,
    `context`       VARCHAR(2048),

    INDEX `parent_id`     (`parent_id`),
    INDEX `type`          (`type`),
    INDEX `corr_id_state` (`corr_id`, `state`),
    INDEX `created_at`    (`created_at`)
    INDEX `scheduled_at`  (`scheduled_at`)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `activities`;
CREATE TABLE `activities` (
    `id`          INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `workflow_id` INT         NOT NULL,
    `name`        VARCHAR(64) NOT NULL,
    `state`       TINYINT     NOT NULL,
    `tries`       TINYINT     NOT NULL,
    `started_at`  DATETIME,
    `finished_at` DATETIME,
    `context`     VARCHAR(128),

    INDEX `workflow_id` (`workflow_id`)
) ENGINE=InnoDB;
