# Upgrading the Leaflet Turf bundle

This folder contains a custom **Turf.js 6.5.0** browser build, the latest stable
6.x release when rebuilt. It exposes `window.turf` with `booleanPointInPolygon`
and all exports from `@turf/helpers` and `@turf/invariant`, preserving the original
bundle's API. Leaflet uses it to highlight polygon drop targets and identify the
target row when data is dropped.

The original bundle's exact version was unknown and its build sources were not
retained. This build has pinned dependencies and a version and license banner.

## Rebuild

Use Node.js 20.9 or newer and npm. Run in this folder:

```powershell
npm ci --ignore-scripts
npm run build
npm test
```

`index.js` defines the API; `webpack.config.js` creates the standalone
`turf.min.js`. `package-lock.json` (npm's standard lockfile name) pins the
dependency tree. The runtime needs only `turf.min.js`, not Node.js or
`node_modules`. Keep `LICENSE.txt` with the bundle.

## Upgrade

1. Check available 6.x versions:
   ```powershell
   npm view @turf/helpers@6 version
   npm view @turf/invariant@6 version
   npm view @turf/boolean-point-in-polygon@6 version
   ```
2. Keep a copy of the current bundle outside this folder for comparison.
3. Set exact versions for all three Turf dependencies in `package.json`.
   Update the package's own `version` to the Turf release being built.
   Do not switch to 7.x without a separate compatibility review.
4. Run `npm install --ignore-scripts` to update `package-lock.json`, then rebuild
   and test using the commands above.
5. Run the same tests against the saved bundle:
   ```powershell
   $env:TURF_BUNDLE_FILE = 'C:\path\to\previous-turf.min.js'
   npm test
   Remove-Item Env:TURF_BUNDLE_FILE
   ```
   Tests cover browser loading, coordinate order, edges, holes, concave polygons,
   multipolygons and invalid inputs. Compare the full `window.turf` export list
   too if changing the entry source or Turf's major version.
6. Check the installed Turf packages' licenses and update `LICENSE.txt` if needed.
   The build embeds it in the banner. Update all three Turf component entries in
   the Core [`includes.json`](../../../../includes.json). When upgrading Webpack,
   also update its generated-runtime entry; build-only dependencies are not bundled.
7. In a running app, check highlighting during a drag, dropping inside and
   outside a polygon, and target-column mappings. Automated bundle tests do not
   verify the complete Leaflet UI workflow.
8. Commit the bundle, build source/configuration, package manifest and lockfile,
   tests, license and this guide together. Do not commit `node_modules`.
