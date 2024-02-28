USE `copy_trading`;



DROP TABLE IF EXISTS `follower_accounts`;
CREATE TABLE `follower_accounts` (
    `id`              INT                     NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `acc_no`          INT UNSIGNED            NOT NULL UNIQUE,
    `lead_acc_no`     INT UNSIGNED            NOT NULL,
    `owner_id`        INT UNSIGNED            NOT NULL,
    `acc_curr`        CHAR(3)                 NOT NULL,
    `copy_coef`       DECIMAL(3, 2)           NOT NULL,
    `lock_copy_coef`  BOOLEAN                 NOT NULL DEFAULT FALSE,
    `stoploss_level`  INT UNSIGNED            NOT NULL,
    `stoploss_equity` DECIMAL(16, 2) UNSIGNED NOT NULL,
    `stoploss_action` TINYINT                 NOT NULL,
    `pay_fee`         INT UNSIGNED            NOT NULL,
    `balance`         DECIMAL(16, 2)          NOT NULL DEFAULT 0.00,
    `equity`          DECIMAL(16, 4)          NOT NULL DEFAULT 0.0000,
    `status`          TINYINT                 NOT NULL,
    `state`           TINYINT                 NOT NULL,
    `is_copying`      BOOLEAN                 NOT NULL,
    `lock_copying`    BOOLEAN                 NOT NULL DEFAULT FALSE,
    `opened_at`       DATETIME                NOT NULL,
    `closed_at`       DATETIME                NULL     DEFAULT NULL,
    `activated_at`    DATETIME                NULL     DEFAULT NULL,
    `settled_at`      DATETIME                NOT NULL,
    `settling_equity` DECIMAL(15, 2),

    INDEX `lead_acc_no`  (`lead_acc_no`),
    INDEX `owner_id`     (`owner_id`),
    INDEX `opened_at`    (`opened_at`),
    INDEX `closed_at`    (`closed_at`),
    INDEX `activated_at` (`activated_at`),
    INDEX `settled_at`   (`settled_at`)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `follower_stoploss_history`;
CREATE TABLE `follower_stoploss_history` (
    `id`              INT                     NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `acc_no`          INT UNSIGNED            NOT NULL,
    `stoploss_level`  INT UNSIGNED            NOT NULL,
    `stoploss_equity` DECIMAL(15, 2) UNSIGNED NOT NULL,
    `stoploss_action` TINYINT                 NOT NULL,
    `changed_at`      DATETIME                NOT NULL,

    INDEX `acc_no` (`acc_no`)
) ENGINE=InnoDB;


DELIMITER //
DROP TRIGGER IF EXISTS `follower_save_stoploss_history`//
CREATE DEFINER = 'ct_exec'@localhost TRIGGER `follower_save_stoploss_history` BEFORE UPDATE ON `follower_accounts` FOR EACH ROW
BEGIN
    IF (NEW.stoploss_level != OLD.stoploss_level) THEN
        INSERT INTO `follower_stoploss_history` (
            `acc_no`,
            `stoploss_level`,
            `stoploss_equity`,
            `stoploss_action`,
            `changed_at`
        ) VALUES (
            OLD.`acc_no`,
            OLD.`stoploss_level`,
            OLD.`stoploss_equity`,
            OLD.`stoploss_action`,
            NOW()
        );
    END IF;
END;//
DELIMITER ;



DROP TABLE IF EXISTS `commission`;
CREATE TABLE `commission` (
    `id`             INT UNSIGNED   NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `workflow_id`    INT UNSIGNED   NOT NULL,
    `trans_id`       INT UNSIGNED,
    `acc_no`         INT UNSIGNED   NOT NULL,
    `created_at`     DATETIME       NOT NULL,
    `amount`         DECIMAL(20, 2) NOT NULL,
    `type`           TINYINT        NOT NULL DEFAULT 0,
    `prev_equity`    DECIMAL(15, 2) NOT NULL,
    `prev_fee_level` DECIMAL(15, 2) NOT NULL,
    `comment`        VARCHAR(100)   NOT NULL,

    INDEX `workflow_id` (`workflow_id`),
    INDEX `trans_id`    (`trans_id`),
    INDEX `acc_no`      (`acc_no`),
    INDEX `created_at`  (`created_at`)
) ENGINE=InnoDB;



DROP TABLE IF EXISTS `follower_loss_notifications`;
CREATE TABLE `follower_loss_notifications` (
    `acc_no`      INT UNSIGNED  NOT NULL UNIQUE,
    `loss_level`  TINYINT       NOT NULL
) ENGINE=InnoDB;
