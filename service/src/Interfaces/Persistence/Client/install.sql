USE `copy_trading`;

DROP TABLE IF EXISTS `clients`;
CREATE TABLE `clients` (
    `id`               INT NOT NULL,
    `quest_attempt_id` INT NULL,

    UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB;
