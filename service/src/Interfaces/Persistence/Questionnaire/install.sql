USE `copy_trading`;

DROP TABLE IF EXISTS `questionnaire`;
CREATE TABLE `questionnaire` (
    `id`           INT      NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `status`       INT      NOT NULL,
    `created_at`   DATETIME NOT NULL,
    `published_at` DATETIME NULL
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `questionnaire_questions`;
CREATE TABLE `questionnaire_questions` (
    `id`               INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `questionnaire_id` INT          NOT NULL,
    `no`               INT          NOT NULL,
    `parent_no`        INT          NULL,
    `text`             VARCHAR(512) NOT NULL,

    UNIQUE KEY `questionnaire_question` (`questionnaire_id`, `no`)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `questionnaire_questions_choices`;
CREATE TABLE `questionnaire_questions_choices` (
    `question_id` INT          NOT NULL,
    `no`          INT          NOT NULL,
    `text`        VARCHAR(512) NOT NULL,
    `points`      INT          NOT NULL,

    UNIQUE KEY `question_choice` (`question_id`, `no`)
) ENGINE=InnoDB;
