CREATE TABLE IF NOT EXISTS `matrix_procedures` (
    `ID`            int(11)         NOT NULL AUTO_INCREMENT,
    `MATRIX_ID`     int(11)         NOT NULL,
    `NAME`          varchar(255)    NOT NULL,
    `DURATION`      int(11)         NOT NULL COMMENT 'Продолжительность в минутах',
    `COST`          decimal(10, 2)  DEFAULT NULL,
    `ONLINE`        tinyint(1)      NOT NULL DEFAULT 0,
    `DOCTOR_IDS`    text            DEFAULT NULL COMMENT 'JSON-массив ID врачей Matrix',
    `CABINET_IDS`   text            DEFAULT NULL COMMENT 'JSON-массив ID кабинетов',
    `SPECIALTY_IDS` text            DEFAULT NULL COMMENT 'JSON-массив ID специальностей',
    `DATE_CREATE`   datetime        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `DATE_UPDATE`   datetime        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`ID`),
    UNIQUE KEY `ux_matrix_procedures_matrix_id` (`MATRIX_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `matrix_records` (
    `ID`                  int(11)      NOT NULL AUTO_INCREMENT,
    `MATRIX_RECORD_ID`    int(11)      NOT NULL,
    `MATRIX_PATIENT_ID`   int(11)      NOT NULL,
    `BITRIX_CONTACT_ID`   int(11)      DEFAULT NULL,
    `MATRIX_DOCTOR_ID`    int(11)      DEFAULT NULL,
    `BITRIX_USER_ID`      int(11)      DEFAULT NULL,
    `MATRIX_PROCEDURE_ID` int(11)      DEFAULT NULL,
    `DATETIME`            datetime     NOT NULL,
    `STATUS`              varchar(50)  DEFAULT NULL,
    `CANCELED`            tinyint(1)   NOT NULL DEFAULT 0,
    `IS_ONLINE`           tinyint(1)   NOT NULL DEFAULT 0,
    `DATE_CREATE`         datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `DATE_UPDATE`         datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`ID`),
    UNIQUE KEY `ux_matrix_records_record_id` (`MATRIX_RECORD_ID`),
    KEY `ix_matrix_records_patient` (`MATRIX_PATIENT_ID`),
    KEY `ix_matrix_records_contact` (`BITRIX_CONTACT_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `matrix_balance` (
    `ID`                int(11)        NOT NULL AUTO_INCREMENT,
    `MATRIX_PATIENT_ID` int(11)        NOT NULL,
    `BITRIX_CONTACT_ID` int(11)        DEFAULT NULL,
    `BALANCE`           decimal(10, 2) NOT NULL DEFAULT 0.00,
    `DATE_UPDATE`       datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`ID`),
    UNIQUE KEY `ux_matrix_balance_patient` (`MATRIX_PATIENT_ID`),
    KEY `ix_matrix_balance_contact` (`BITRIX_CONTACT_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `matrix_bonus` (
    `ID`                int(11)        NOT NULL AUTO_INCREMENT,
    `MATRIX_PATIENT_ID` int(11)        NOT NULL,
    `BITRIX_CONTACT_ID` int(11)        DEFAULT NULL,
    `BONUS_BALANCE`     decimal(10, 2) NOT NULL DEFAULT 0.00,
    `DATE_UPDATE`       datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`ID`),
    UNIQUE KEY `ux_matrix_bonus_patient` (`MATRIX_PATIENT_ID`),
    KEY `ix_matrix_bonus_contact` (`BITRIX_CONTACT_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `matrix_bonus_log` (
    `ID`                int(11)        NOT NULL AUTO_INCREMENT,
    `MATRIX_LOG_ID`     int(11)        DEFAULT NULL,
    `MATRIX_PATIENT_ID` int(11)        NOT NULL,
    `BITRIX_CONTACT_ID` int(11)        DEFAULT NULL,
    `TYPE`              varchar(100)   DEFAULT NULL,
    `AMOUNT`            decimal(10, 2) DEFAULT NULL,
    `BALANCE`           decimal(10, 2) DEFAULT NULL,
    `ACTIVATED_AT`      datetime       DEFAULT NULL,
    `DEACTIVATED_AT`    datetime       DEFAULT NULL,
    `STATUS`            varchar(50)    DEFAULT NULL,
    `NOTIFIED`          tinyint(1)     NOT NULL DEFAULT 0,
    `DATE_CREATE`       datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`ID`),
    KEY `ix_matrix_bonus_log_patient` (`MATRIX_PATIENT_ID`),
    KEY `ix_matrix_bonus_log_deactivated` (`DEACTIVATED_AT`),
    KEY `ix_matrix_bonus_log_notified` (`NOTIFIED`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `matrix_control_turnouts` (
    `ID`                  int(11)  NOT NULL AUTO_INCREMENT,
    `MATRIX_ID`           int(11)  NOT NULL,
    `MATRIX_PATIENT_ID`   int(11)  NOT NULL,
    `BITRIX_CONTACT_ID`   int(11)  DEFAULT NULL,
    `MATRIX_DOCTOR_ID`    int(11)  DEFAULT NULL,
    `BITRIX_USER_ID`      int(11)  DEFAULT NULL,
    `DATE`                date     NOT NULL,
    `NOTIFIED`            tinyint(1) NOT NULL DEFAULT 0,
    `BITRIX_ACTIVITY_ID`  int(11)  DEFAULT NULL,
    `DATE_CREATE`         datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`ID`),
    UNIQUE KEY `ux_matrix_turnouts_matrix_id` (`MATRIX_ID`),
    KEY `ix_matrix_turnouts_patient` (`MATRIX_PATIENT_ID`),
    KEY `ix_matrix_turnouts_date` (`DATE`),
    KEY `ix_matrix_turnouts_notified` (`NOTIFIED`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `matrix_receipts` (
    `ID`                int(11)        NOT NULL AUTO_INCREMENT,
    `MATRIX_RECEIPT_ID` int(11)        NOT NULL,
    `MATRIX_PATIENT_ID` int(11)        NOT NULL,
    `BITRIX_CONTACT_ID` int(11)        DEFAULT NULL,
    `BITRIX_DEAL_ID`    int(11)        DEFAULT NULL,
    `TYPE`              int(11)        DEFAULT NULL,
    `SUM`               decimal(10, 2) DEFAULT NULL,
    `CHECKED_AT`        datetime       DEFAULT NULL,
    `ITEMS`             text           DEFAULT NULL COMMENT 'JSON: состав квитанции',
    `DATE_CREATE`       datetime       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`ID`),
    UNIQUE KEY `ux_matrix_receipts_receipt_id` (`MATRIX_RECEIPT_ID`),
    KEY `ix_matrix_receipts_patient` (`MATRIX_PATIENT_ID`),
    KEY `ix_matrix_receipts_contact` (`BITRIX_CONTACT_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;