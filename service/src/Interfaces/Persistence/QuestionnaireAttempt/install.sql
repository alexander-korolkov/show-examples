USE `copy_trading`;

DROP TABLE IF EXISTS `questionnaire_attempts`;
CREATE TABLE `questionnaire_attempts` (
    `id`               INT      NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `client_id`        INT      NOT NULL,
    `questionnaire_id` INT      NOT NULL,
    `submitted_at`     DATETIME NOT NULL,
    `points`           INT      NOT NULL,
    `result`           INT      NOT NULL
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `questionnaire_attempts_answers`;
CREATE TABLE `questionnaire_attempts_answers` (
    `attempt_id`  INT NOT NULL,
    `question_no` INT NOT NULL,
    `choice_no`   INT NULL
) ENGINE=InnoDB;
