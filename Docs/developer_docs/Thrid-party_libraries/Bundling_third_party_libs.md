# How to bundle third-party libraries properly

Prefer declaring dependencies in your app's `composer.json`, using Asset-Packagist for NPM or Bower packages. Bundle a library directly in your app only when the managed dependency is not suitable, for example when you need a particular browser distribution or a modified version.

Bundled libraries are not automatically registered as installed Composer packages. You must preserve their licensing information and register them in your app's `includes.json` so PackageManager can include them in the installation's license inventory.

## Download and organize the files

Download the library from an official, versioned release or published package. Use an exact version rather than a moving branch or a URL pointing to the latest release.

Give each library its own subfolder, for example `Facades/js/example-library/`. Keep the files needed to use the library together with their original supporting files:

- The JavaScript, CSS, fonts, images or other runtime assets required by the selected distribution. Preserve relative paths between these files.
- The original `LICENSE`, `LICENCE`, `COPYING` or equivalent files containing the applicable license terms.
- Any upstream `NOTICE`, third-party notices, copyright notices and other attribution files required for redistribution.
- The original `README` and any accompanying instructions needed to understand or use the distribution.
- The published `package.json`, if available, as a record of the package's identity, version and declared license.

Preserve upstream filenames and contents where possible. Do not strip license or copyright comments from source files or minified bundles. A README or a link to a license is not a replacement for the required license text and notices.

Keep downloaded originals in this folder. If local modifications are necessary, record what was changed and why. An optional, locally maintained `UPSTREAM.md` can record the exact version, download URLs, update procedure, checksums and local modifications.

## Register a dependency-free library

Add an entry to the `includes.json` in the root of the app that ships the library. The following fictional example assumes that the upstream package is named `example-library`, version `1.2.3`, and is licensed under MIT. Replace these values with the actual upstream metadata.
 
```
 
[
	{
		"name": "npm-asset/example-library",
		"version": "1.2.3",
		"license": ["MIT"],
		"license_file": "acme/myapp/Facades/js/example-library/LICENSE",
		"description": "Description of the upstream library"
	}
]
 
```
 
If your app already has an `includes.json`, add the entry to its existing package list rather than replacing the other entries.

- Use the original package identity. For an NPM package, use its Asset-Packagist name, such as `npm-asset/example-library`.
- Set `version` to the exact version of the files being distributed.
- Copy the upstream license declaration, preferably using its SPDX identifier. Do not assume that an open-source library uses MIT or select a license without checking its terms.
- Set `license_file` to the actual local license file. The path is relative to the installation's `vendor/` directory, not to `includes.json` or the library's subfolder. Use forward slashes.
- Keep the complete original license text, including copyright and attribution information.

The existing license-file enricher reads `license_file` and attaches its contents to the package's effective license. For this single-license example, that is MIT. Downloading `package.json` into a custom subfolder does not currently make PackageManager read it automatically: the entry must still provide the package name, version and license declaration.

The `license_file` field supplies license text to the inventory; it does not replace any additional attribution or notice requirements in the upstream license. Preserve and distribute those notices too.

## Bundles containing dependencies

A single distributed file can contain code from multiple third-party packages. The main library's license alone is not sufficient for such a bundle.

Include all applicable license information for the embedded third-party code, including complete license texts, copyright and attribution information, and required third-party notices. Preserve any notices already supplied with the upstream distribution. If the distribution does not supply all required information, obtain it before redistributing the bundle.

## Verify and maintain the bundle

After adding or updating a bundled library:

1. Check that the downloaded files and the version recorded in `includes.json` match.
2. Check that all required runtime assets, license files, README files and notices are present.
3. Update references in your app if a file or directory has moved.
4. Run PackageManager's `GenerateLicenseBOM` action and inspect the generated license document and inventory. Confirm that the package appears with the correct version and actual license text.
5. Test the affected application features using the locally bundled files.

Repeat these checks for every update. Licensing and notice requirements can change between releases. Successful BOM generation is not, by itself, proof that every redistribution obligation has been met.

See [Licensing apps properly](index.md) for the general dependency-management rules.
