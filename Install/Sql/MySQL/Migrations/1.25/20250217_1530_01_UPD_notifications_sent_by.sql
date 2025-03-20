-- UP

UPDATE `exf_notification` n
LEFT JOIN `exf_user` u ON u.username = n.sent_by
SET n.sent_by = CASE 
  WHEN (u.first_name IS NOT NULL AND u.first_name <> '') OR (u.last_name IS NOT NULL AND u.last_name <> '') THEN TRIM(CONCAT(u.first_name, ' ', u.last_name))
  ELSE n.sent_by
  END
WHERE u.username IS NOT NULL;

-- DOWN

/*
No DOWN-Skript as going back from FULL_NAME to username is not very reliable
*/