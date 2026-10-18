# Licensing apps properly

As every open source project, this platform includes relies on many third-party party libraries. It is extremely important to honor license terms of these libraries and make sure new ones are registered properly.

We use our [PackageManager](https://github.com/axenox/PackageManager/blob/1.x-dev/Docs/index.md) app to manage dependencies. That, in-turn, uses PHP Composer and NPM via [Asset-Packagist](https://asset-packagist.org/). The package manager includes various tools to manage licenses properly, but they rely on developers following certain rules:

- To include a PHP package available via packagist.org, simply add it to the `composer.json` of your app - just like in any other PHPapp
- To include a private PHP package, make sure your root `composer.json` has a `repositories` entry
- To include a JavaScript library available via NPM or Bower, use Asset-Packagist includes in your `composer.json`: `npm-asset/` and `bower-asset/`
- To ship a library directly with your app, follow [How to bundle third-party libraries properly](Bundling_third_party_libs.md). Preserve its original license, README and required notices, and register the package in your app's `includes.json`.

Following these rules ensures, that the package manager will include all third-party libraries in the auto-generated SBOM and in the `axenox.PackageManager.Audit` action when it is used to scan dependencies for vulnerabilities.