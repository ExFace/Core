# Troubleshooting installation issues

## Composer-driven installations

### Installation does not start or finish (hangs)

#### DB connectivity issues

If the initial core installer ran fine, but subsequent app installers do not start or hang, this may be due to 
timeouts in the DB connectors. This behavior was observed on some MS SQL Servers where the installer failed to open 
the second connection to the DB. You can try to force all installers to use the same workbench and, thus, the same 
DB connection by setting `COMPOSER.USE_NEW_WORKBENCH_FOR_EVERY_APP` to `false` in `axenox.PackageManager.config.
json` in your config folder.

#### Child process for app installers

When Composer calls the installers (e.g. after `composer install` or `composer update`), they may run in a separate 
PHP process. This is required if Composer bundles libraries, that break the workbench in another major version
(currently psr/log: Composer bundles psr/log 1, while Monolog 3 in the workbench needs psr/log 3): a PHP process can
only load one version of a class.

You can control this via `COMPOSER.CHILD_PROCESS_MODE` in `axenox.PackageManager.config.json` in your config folder:

- `migration` (default) - use a child process only if such a conflict is detected. The conflicting packages are
printed to the console.
- `always` - always use a child process for Composer calls.
- `false` - always run the installers inside Composer. Use this if the child process causes problems. A warning is
printed if a conflict is detected - in this case the installers will most likely fail.

If the child process hangs, set `COMPOSER.CHILD_PROCESS_TIMEOUT_SECONDS` to stop it after the given number of
seconds (default `0` - no timeout). Running the installers manually is never affected by these options:

```
php -r "require 'vendor/autoload.php'; axenox\PackageManager\StaticInstaller::composerFinishInstall();"
```