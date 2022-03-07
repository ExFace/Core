-- UP

ALTER TABLE exf_user_authenticator
	DROP CONSTRAINT [exf_user_authenticator$Authenticator unique per user];

	
-- DOWN
	
ALTER TABLE exf_user_authenticator
	ADD CONSTRAINT [exf_user_authenticator$Authenticator unique per user] UNIQUE NONCLUSTERED 
(
	[user_oid] ASC,
	[authenticator_id] ASC
);