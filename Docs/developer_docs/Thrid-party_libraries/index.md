# Working with third-party libraries

As most software today, this platform includes relies on many third-party party libraries. It is extremely important to keep track of all these dependencies, to honor their license terms and make sure all dependencies are kept up to date.

## Quick links

- Properly including a public PHP or NPM package - see below.
- [Updating a third-party library used by an app](Update_a_third-party_library.md)
- [Bundling libraries and supplementing missing license metadata in includes.json](Bundling_third_party_libs.md)

## Package manager overview

We use our [PackageManager](https://github.com/axenox/PackageManager/blob/1.x-dev/Docs/index.md) app to manage dependencies. That, in-turn, uses PHP Composer and NPM via [Asset-Packagist](https://asset-packagist.org/). The package manager includes various tools to manage licenses properly, but they rely on developers following certain rules:

- To include a **public PHP package** available via [Composer Packagist](https://packagist.org/), simply add it to the `composer.json` of your app - just like in any other PHPapp
- To include a **private PHP package**, make sure your root `composer.json` has a `repositories` entry
- To include a **JavaScript library** available via NPM or Bower, use Asset-Packagist includes in your `composer.json`: `npm-asset/` and `bower-asset/`
- To **ship a library directly** with your app, follow [How to bundle third-party libraries properly](Bundling_third_party_libs.md). Preserve its original license, README and required notices, and register the package in your app's `includes.json`.

Following these rules ensures, that the package manager will include all third-party libraries in the auto-generated SBOM and in the `axenox.PackageManager.Audit` action when it is used to scan dependencies for vulnerabilities.