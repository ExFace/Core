---
description: "Use when creating or modifying SQL migration files for ExFace SQL Database Installer"
name: "SQL migrations"
applyTo: "Install/**/*.sql"
---
# SQL database migrations

Use these rules when writing SQL migrations to keep the DBs of apps up-to-date.

## Core migration workflow

- Preparation
  - Make sure, the app has a `Install/Sql` folder with subfolders for each 
    supported SQL database type (e.g. `MySQL`, `PostgreSQL`, `MsSql`).
  - Make sure the app container file called `<app_name>App.php` in the root of 
    the app folder has all supported DB installers registered in `getInstaller
    ()` method.
- When setting up SQL installers for the app the first time, create a 
  `InitDB` folder with baseline migration files creating the initial tables.
- When writing migrations for DB changes, create SQL files in the 
  `Migrations/<version>` subfolder. Make sure you know the version (or ask 
  for it).
- When writing views, stored procedures, or other non-table objects, create 
  SQL files in correspondings subfolders (e.g. `Views`, `StoredProcedures`). 
  Make sure, they are completely recreated with each install. In contrast to 
  migrations, these scripts are called "static SQL" and are not tracked in 
  the migration log table.

## Migration file content and execution principles

The folder `%app%/Install/Sql/%SqlDbType%/` contains all SQL needed to 
create the required DB from scratch and to upgrade from any previous version.

All files are pure SQL files with `.sql` extension. Additional control 
structures may exist depending on the installer, that runs the files, but these
are always placed in SQL comments.

There are two types of SQL files placed in different folders:

- Migrations
  - A migration is a plain SQL file with `-- UP` and `-- DOWN` sections and 
    optional other controls, that depend on the specific installer for a DB type.
  - Migration files are identified by **file name only** (not subfolder path).
  - Installer compares source migrations with rows in the migration log table (default name `_migrations`).
  - If a migration file exists in source but is not currently up in DB, it runs **UP**.
  - If a migration is up in DB but no longer exists in source, it runs **DOWN**.
- Static SQL files - e.g. views, stored procedures, etc. These files 
  recreate their objects objects with each install. They are not tracked as 
  migrations.

Each `Install/Sql` folder can have multiple subfolders for migrations and 
static SQL. These must be registered when the installer is initialized in 
the app PHP class. 

Order of execution:

- Migrations are executed before static SQL
- Folders are executed in the order of registration in the installer configuration (not alphabetically).
- Files are executed in alphabetical order 

## Folder and naming conventions

- Use folder structure .
- Typical migration folders: `InitDB`, `DemoData`, `Migrations`.
- Recommended migration order in installer configuration:
  - `setFoldersWithMigrations(['InitDB', 'DemoData', 'Migrations'])`
- Migration file names should follow:
  - `YYYYMMDD_HHMM_#_INFO.sql`
  - Example: `20190101_1200_1_NEW_column3_and_column4.sql`
- Migration file names must be unique across the app.
- Using version subfolders helps avoid big folders, that are hard to overview.

## Required migration script structure

Each migration file must contain explicit UP and DOWN sections:

```sql
-- UP
ALTER TABLE tablename
    ADD columnname3 datetime DEFAULT NULL,
    ADD columnname4 datetime DEFAULT NULL;

-- DOWN
ALTER TABLE tablename
    DROP columnname3,
    DROP columnname4;
```
 
Rules:
- Use `-- UP` and `-- DOWN` markers exactly (with the space).
- DOWN must reliably revert UP.
- Keep migrations idempotent where possible, especially in `InitDB` and `DemoData`.

## Global principles for writing migration SQL

- Describe a migration in a comment header with the following information:
  - Short description of the change
  - Any special notes (e.g. non-reversible, demo data, etc.)
  - Names of people, who created/changed the migration
- Comment the intent of every statement in the script, especially if it is 
  not self-explanatory.
- Always follow naming conventions for tables, views, etc. If app already 
  has migrations, analyse them to understand the existing naming patterns.
- Use named objects where possible: e.g. named contraints, indexes, etc. 
  This makes DOWN scripts more reliable.
- Avoid (not)exists errors: When adding or removing objects, check if they 
  exist first.
- Validate scripts thoroughly! Errors during installation can corrupt the DB 
  and are often hard to analyze and fix.

**CRITICAL:** NEVER delete existing data in a migration script unless it is 
already marked as `trash_` or you are explictily orderd to delete!

- When removing columns or tables, always check if they are empty first.
- If tables and column are NOT empty, rename them instead of dropping. 
  - Remove all constraints or other dependencies
  - Add a `trash_` prefix to the name and add a comment with the original 
    name and reason for renaming. This allows to keep the data for later 
    analysis and possible recovery. 
- Trash data can be removed with a separate migration at a later point in time.

## Batch delimiter behavior

By default, each UP or DOWN section is executed as one batch.

Use `-- BATCH-DELIMITER` to split a section into multiple statements:

```sql
-- UP
-- BATCH-DELIMITER /;\R/
INSERT INTO t (a) VALUES (1);
INSERT INTO t (a) VALUES (2);
```
 
Notes:
- Delimiter may be a plain string or regex.
- `MsSqlDatabaseInstaller` additionally supports `GO` as default batch separator.

## DB-specific behavior and transaction expectations

- **MySQL**:
  - DDL is not rollback-safe; installer wraps script execution in transactions to keep DML behavior consistent.
  - Utility procedures referenced via `CALL <name>(...)` may be auto-loaded from `QueryBuilders/SqlFunctions/MySQL/<name>.sql` when not created in script.
- **PostgreSQL**:
  - Supports DDL rollback.
  - Installer uses PostgreSQL-specific migration table schema and `RETURNING id` for insert logging.
  - Utility cleanup uses `DROP FUNCTION IF EXISTS <name>();`.
- **MS SQL Server**:
  - Supports DDL rollback in many scenarios; installer still runs migrations in explicit transactions.
  - Supports schema-qualified migration log table naming (`[schema].[_migrations]`).
  - Uses `SELECT SCOPE_IDENTITY()` to retrieve inserted migration id.

## Migration logging expectations

Installers log each migration execution with:
- migration name
- UP/DOWN timestamps
- stored UP/DOWN script content
- UP/DOWN execution results
- failure flag/message
- optional log id
- skip flag

When migration execution fails:
- Failure state is persisted to migration table.
- Installer may attempt utility function/procedure cleanup.
- Write clear SQL that makes failures diagnosable from stored script/results.

## Demo data migrations

- `DemoData` should be treated as migrations (tracked and reversible where needed).
- Demo data must match the final schema after all structural migrations.
- If structural schema changes require demo updates, modify existing demo 
  migration SQL content without renaming files unless intentional migration 
  replacement is required.

## Do / do not

Do:
- Keep UP/DOWN small, focused, and reversible.
- Prefer explicit column lists and deterministic statements.
- Use the narrowest compatible SQL syntax for the target DB installer.
- Validate scripts with correct encoding (UTF-8 expected by installers).

Do not:
- Remove or rename migration files casually (this triggers DOWN behavior).
- Depend on subfolder names for migration identity.
- Omit DOWN unless. Instead leave a comment there explaining, why this 
  particular script should not be downed.
- Mix unrelated schema/data concerns in one migration when rollback clarity matters.