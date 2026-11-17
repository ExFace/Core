# Upgrading on significant changes

## Major version upgrades

TODO

## Minor version upgrades

### Monolog upgrade in core version 1.44

1. Change to your install directory (e.g. `C:\EDF\wamp\www\exface\exface`) and open a command prompt there or use
   ```
   cd C:\EDF\wamp\www\exface\exface
   ```

2. Run `php composer.phar update` as usual. This should fail with a fatal error and thats okay
(e.g. `Declaration of Monolog\Logger::... must be compatible with Psr\Log\LoggerInterface::...`). 
The packages and the autoloader are already updated at this point, 
and the apps to install are kept in `vendor/axenox/packagemanager/LastInstall.temp.json`.

2. Run this command from the installation folder (i.e. "C:\EDF\wamp\www\exface\exface"):
   ```
   php vendor/axenox/packagemanager/bin/composer-script.php composerFinishInstall
   ```
   If you had the legacy callbacks in your root `composer.json`, this replaces them with the new `@php` scripts and prints a notice 
for every replaced entry:
   ```
   MIGRATION: replaced legacy Composer callback "axenox\PackageManager\StaticInstaller::composerFinishInstall" 
   in "scripts.post-update-cmd" of the root composer.json by "@php vendor/axenox/packagemanager/bin/composer-script.php composerFinishInstall"
   ```
   
3. All later `composer install/update` runs work without manual steps.

**Important** - Do not use `composer update --no-scripts` for this migration: while it would avoid the crash it also skips
all the scripts in your `composer.json` - and you would have to run them manually.

## Why crash and manual run?

Composer reads the scripts of the root `composer.json` once, when it starts. It also loads the old `StaticInstaller` class 
during the first package event, before the new version of the package manager is installed. So during the first update, 
nothing in the new package manager can replace the legacy callback before it starts the workbench inside the Composer process.

Consequently, if you migrate `composer.json` by hand before the first update (see the snippet above), no manual run is needed.