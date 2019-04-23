# SQL migrations with the SqlDatabaseInstaller

TODO

## Transaction handling

Transaction handling is different depending on the concrete installer implementation.

### DDL Transactions in major DBMS

- PostgreSQL - yes
- MySQL - no; DDL causes an implicit commit
- Oracle Database 11g Release 2 and above - by default, no, but an alternative called edition-based redefinition exists
- Older versions of Oracle - no; DDL causes an implicit commit
- SQL Server - yes
- Sybase Adaptive Server - yes
- DB2 - yes
- Informix - yes
- Firebird (Interbase) - yes