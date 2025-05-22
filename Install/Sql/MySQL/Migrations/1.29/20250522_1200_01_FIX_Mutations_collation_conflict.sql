-- UP

ALTER TABLE `exf_mutation_set`
    MODIFY COLUMN `name` varchar(128) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
    MODIFY COLUMN `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;

ALTER TABLE `exf_mutation_target`
    MODIFY COLUMN `name` varchar(128) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
    MODIFY COLUMN `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;

ALTER TABLE `exf_mutation_type`
    MODIFY COLUMN `name` varchar(128) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
    MODIFY COLUMN `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;

ALTER TABLE `exf_mutation`
    MODIFY COLUMN `name` varchar(128) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
    MODIFY COLUMN `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
    MODIFY COLUMN `config_uxon` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
    MODIFY COLUMN `targets_json` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;

-- DOWN


