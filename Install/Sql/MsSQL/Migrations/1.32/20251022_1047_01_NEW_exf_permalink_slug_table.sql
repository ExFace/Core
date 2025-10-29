-- UP
CREATE TABLE exf_permalink_slug (
                                    [oid] BINARY(16) NOT NULL,
                                    [created_on] DATETIME2(0) NOT NULL,
                                    [modified_on] DATETIME2(0) NOT NULL,
                                    [created_by_user_oid] BINARY(16) NOT NULL,
                                    [modified_by_user_oid] BINARY(16) NOT NULL,
                                    [permalink_oid] BINARY(16) NOT NULL,
                                    [slug] NVARCHAR(200) NOT NULL,
                                    [data_uxon] NVARCHAR(MAX) NOT NULL,
                                
                                    CONSTRAINT [PK_exf_permalink_slug] PRIMARY KEY ([oid]),
                                    CONSTRAINT [UQ_exf_permalink_slug_slug] UNIQUE ([permalink_oid], [slug]),
                                    CONSTRAINT [FK_exf_permalink_slug_perm] FOREIGN KEY ([permalink_oid]) REFERENCES dbo.exf_permalink ([oid])
                                    );

-- DOWN
IF OBJECT_ID('exf_permalink_slug', 'U') IS NOT NULL
DROP TABLE exf_permalink_slug;