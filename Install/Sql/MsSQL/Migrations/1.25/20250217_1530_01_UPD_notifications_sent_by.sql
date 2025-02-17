-- UP

UPDATE n set n.sent_by = (CASE 
  WHEN (u.first_name IS NOT NULL AND u.first_name <> '') OR (u.last_name IS NOT NULL AND u.last_name <> '') THEN LTRIM(RTRIM(CONCAT(u.first_name, ' ', u.last_name)))
  ELSE n.sent_by
  END)
FROM dbo.exf_notification n
LEFT JOIN dbo.exf_user u on u.username = n.sent_by
WHERE u.username IS NOT NULL

-- DOWN

/*
No DOWN-Skript as going back from FULL_NAME to username is not very reliable
*/