-- UP
CREATE TABLE `exf_permalink_slug` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) NOT NULL,
  `modified_by_user_oid` binary(16) NOT NULL,
  `permalink_oid` binary(16) NOT NULL,
  `slug` varchar(200) COLLATE 'utf8mb3_general_ci' NOT NULL,
  `data_uxon` longtext COLLATE 'utf8mb3_general_ci' NOT NULL,
  CONSTRAINT PK_exf_permalink_slug PRIMARY KEY (oid),
  CONSTRAINT UQ_exf_permalink_slug_slug UNIQUE (permalink_oid, slug),
  CONSTRAINT FK_exf_permalink_slug_perm
      FOREIGN KEY (permalink_oid) REFERENCES exf_permalink(oid)
);

-- DOWN
DROP TABLE IF EXISTS `exf_permalink_slug`; 