/**
 * Builds the standalone Leaflet Turf bundle with version and license metadata.
 */
const oFs = require('node:fs');
const oPath = require('node:path');
const oWebpack = require('webpack');
const oPackage = require('./package.json');

module.exports = {
    mode: 'production',
    context: __dirname,
    entry: './index.js',
    devtool: false,
    output: {
        path: __dirname,
        filename: 'turf.min.js',
        library: {
            name: 'turf-point',
            type: 'umd'
        },
        globalObject: 'self'
    },
    plugins: [
        new oWebpack.BannerPlugin({
            banner: 'Turf.js ' + oPackage.version + ' - custom Leaflet build\n' +
                oFs.readFileSync(oPath.join(__dirname, 'LICENSE.txt'), 'utf8'),
            entryOnly: true,
            stage: oWebpack.Compilation.PROCESS_ASSETS_STAGE_REPORT
        })
    ]
};
