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

## Supplement installed package metadata

Use `includes.json` also when a Composer-installed dependency has missing or incomplete license metadata. Do not edit the installed dependency or `composer.lock` to repair its license information: those changes will be lost on installation or update.

### Find the owning app

- Start with the package identity and installed version in `composer.lock`. Check the dependency's distributed license files, source headers and published metadata before declaring its license.
- Find the app that introduces the dependency through its Composer requirements, bundled assets or a transitive dependency chain. Confirm actual use in the app's asset configuration and implementation; a shared Core trait can load libraries on behalf of several facades.
- Add the supplement to that app's root `includes.json`. If multiple apps independently introduce the same dependency, maintain the relevant supplement in each app rather than relying on another app being installed.
- Keep runtime dependencies in each consuming app's `composer.json`. An inclusion registry supplies inventory metadata; it does not install a library or replace a missing Composer requirement.
- If an app's own license metadata is missing, supplement that app under its own package identity, using its actual distributed terms.

### Choose the identity and version

Use the original Composer identity, including `npm-asset/` or `bower-asset/` for Asset-Packagist dependencies. Do not create a new identity for the same dependency.

For a supplement to an installed package, normally omit `version`. The BOM merger applies the metadata to matching installed versions while retaining their versions from Composer. Only use such a versionless supplement when the supplied terms apply to those versions. A versioned entry is matched separately from other nonempty versions, so do not copy a bundled-library example's version into a general supplement.

For software actually bundled in the app, retain an exact `version` as described above. If several versions are bundled, register each separately and verify its terms.

Generation registers versioned bundles from all manifests before applying versionless supplements.
If no matching installed or bundled package exists, the supplement is excluded from the generated
BOM with a warning. A supplement does not declare that software is installed. Regenerate older
inventories to apply this rule; existing archived build BOMs are not changed automatically.

### Supply declarations and terms

Set `license` to an array of verified license identifiers. Preserve upstream alternative licenses; use `license_used` only when intentionally selecting an applicable license, not to hide missing evidence or change upstream licensing.

Provide the terms through one of these fields:

- `license_file`: a local path relative to the installation's `vendor/` folder. This is preferred when the license file is shipped locally. For a single license, a string is sufficient; a map keyed by license identifier can identify files for different licenses.
- `license_text`: an object keyed by license identifier, with the complete original text, including copyright and attribution information, as each value. Encode newlines as `\n` in JSON strings.
- `license_link`: an object keyed by license identifier, with a URL to its terms as each value. Use a raw downloadable text URL supported by BOM generation, not a numeric array of URLs or an HTML repository page.

For example, this fictional supplement deliberately omits the version. Replace the identity, license and URL with verified upstream values:
 
```
 
[
	{
		"name": "npm-asset/example-library",
		"license": ["MIT"],
		"license_link": {
			"MIT": "https://raw.githubusercontent.com/example-org/example-library/v1.2.3/LICENSE"
		}
	}
]
 
```
 
The current GitHub license enricher downloads `license_link` values containing `raw.githubusercontent.com`; ordinary GitHub `blob` links and other license websites are not downloaded by that enricher. Verify the raw URL, exact tag spelling and file contents. Prefer a verified release tag or commit over a moving branch. A generic SPDX text can supply standard terms for a verified declaration, but it does not replace package-specific copyright notices or additional terms.

Keep text and links keyed by the exact license identifier, such as `MIT` or `Apache-2.0`. For multiple declared licenses, preserve the relevant terms for each rather than associating one license's text with another. Do not use a generic MIT template to guess a package's license or attribution.

### Generated inclusion registries

If an app generates `includes.json`, change the generator's maintained inputs and regenerate the file instead of hand-editing generated entries. Otherwise the next build will erase the repair. When upstream omits a license file, keep reviewed fallback text tied to the exact package and version; preserve its provenance and original attribution. Keep build tools and dependencies not actually bundled out of the generated bundle registry.

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

After repairing metadata, regenerate the full BOM before rerunning a license audit. The license scanner reads the generated inventory, not the app's current `includes.json`. A URL alone does not satisfy its missing-license-text check: confirm that BOM generation resolved actual text through a local file, inline text or a supported download. Check that the original installed versions remain unchanged and that every targeted package is present with the expected license and text. An old inventory or archived build BOM is not repaired by editing a registry.

Repeat these checks for every update. Licensing and notice requirements can change between releases. Successful BOM generation is not, by itself, proof that every redistribution obligation has been met.

See [Licensing apps properly](index.md) for the general dependency-management rules.
