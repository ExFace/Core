# Recommendations for SQL migrations

When writing migration scripts it is very important to make them as fail-safe as possible. Keep in mind, that a previous migratino might have been run partially or with errors, so any migration should always check if the current database state is what it expects it to be: i.e. always check if tables or columns really exist, etc.

Depending on the DBMS used writing fail-safe SQL might be not an easy task. Here are some reusable SQL snippets for different databases:

- [Microsoft SQL Server](MS_SQL_migrations.md)
- [MySQL & MariaDB](MySQL_migrations.md)