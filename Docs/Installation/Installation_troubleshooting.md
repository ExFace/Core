# Troubleshooting installation issues

## Composer-driven installations

### Installation does not start or finish (hangs)

#### DB connectivity issues

If the initial core installer ran fine, but subsequent app installers do not start or hang, this may be due to 
timeouts in the DB connectors. This behavior was observed on some MS SQL Servers where the installer failed to open 
the second connection to the DB. You can try to force all installers to use the same workbench and, thus, the same 
DB connection by setting `COMPOSER.USE_NEW_WORKBENCH_FOR_EVERY_APP` to `false` in `axenox.PackageManager.config.
json` in your config folder.