USE `copy_trading`;

DROP TABLE IF EXISTS `leader_accounts_news`;
CREATE TABLE `leader_accounts_news` (
    `id`           INT            NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `acc_no`       INT UNSIGNED   NOT NULL,
    `title`        VARCHAR(100)   NOT NULL,
    `text`         VARCHAR(1000)  NOT NULL,
    `status`       TINYINT        NOT NULL,
    `submitted_at` DATETIME       NOT NULL,
    `updated_at`   DATETIME       NOT NULL,
    `reviewed_at`  DATETIME       NULL DEFAULT NULL,

    INDEX `acc_no`       (`acc_no`),
    INDEX `status`       (`status`),
    INDEX `submitted_at` (`submitted_at`),
    INDEX `updated_at`   (`updated_at`),
    INDEX `reviewed_at`  (`reviewed_at`)
) ENGINE=InnoDB;
