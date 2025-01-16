-- UP

ALTER TABLE `exf_user`
ADD disable_date DATETIME NULL;

UPDATE `exf_user`
SET disable_date = NOW()
WHERE disabled_flag = 1;

ALTER TABLE `exf_user`
DROP COLUMN disabled_flag;

-- DOWN

ALTER TABLE `exf_user`
ADD disabled_flag TINYINT NOT NULL;

UPDATE `exf_user`
SET disabled_flag = 1
WHERE disable_date < NOW();

ALTER TABLE `exf_user`
DROP COLUMN disable_date;
