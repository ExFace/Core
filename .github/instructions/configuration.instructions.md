---
name: "App and workbench configuration"
description: "Use when configuring app and workbench settings"
applyTo: "Config/*.json"
---

Apps running on the workbench keep their configuration in JSON files with JSON arrays inside. These JSON files are read from different locations (called configuration scopes) and merged at runtime into an instance of the `exface\Core\CommonLogic\Configuration` class.

For the default app config, there are three scopes (folders):

1. application scope (`%workbech%/vendor/my/app/Config`) - the files here define the default configuration
2. installation scope (`%workbech%/config`) - overrides certain config keys for the current installation
3. user scope (`%workbech%/data/users/<username>/.config`) - Rarely used user-specific configurations

Each scope overwrites the previous one if it has the same config key. The application scope should have **all** available keys.

By default, the app config is stored in a file named `app.Alias.config.json` - e.g. `exface.Core.config.json`. In the installation and user scopes, the folder can contain files for several apps.

Some apps also have additional config files. For example, the Core has a `System.config.json`, which cannot be overridden on user-level. 