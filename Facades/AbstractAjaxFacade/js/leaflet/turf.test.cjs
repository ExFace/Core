/**
 * Checks the built browser bundle's polygon hit testing used by Leaflet drops.
 * TURF_BUNDLE_FILE can select a previous bundle for the same regression checks.
 */
const oAssert = require('node:assert/strict');
const oFs = require('node:fs');
const oPath = require('node:path');
const oVm = require('node:vm');
const { test: fnTest } = require('node:test');

const oContext = oVm.createContext({});
oVm.runInContext('window = globalThis; self = globalThis;', oContext);
oVm.runInContext(oFs.readFileSync(
    process.env.TURF_BUNDLE_FILE || oPath.join(__dirname, 'turf.min.js'), 'utf8'
), oContext);
const oTurf = oContext.turf;
const aOuterRing = [[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]];
const aHole = [[3, 3], [7, 3], [7, 7], [3, 7], [3, 3]];

fnTest('standalone browser bundle exposes helpers and invariant functions', () => {
    oAssert.equal(typeof oTurf.point, 'function');
    oAssert.equal(typeof oTurf.polygon, 'function');
    oAssert.equal(typeof oTurf.booleanPointInPolygon, 'function');
    oAssert.deepEqual(Array.from(oTurf.getCoord(oTurf.point([8, 2]))), [8, 2]);
    oAssert.equal(oTurf.getGeom(oTurf.polygon([aOuterRing])).type, 'Polygon');
});

fnTest('drop hit testing uses longitude, latitude and detects outside points', () => {
    const oPolygon = oTurf.polygon([[[7, 1], [9, 1], [9, 3], [7, 3], [7, 1]]]);
    oAssert.equal(oTurf.booleanPointInPolygon(oTurf.point([8, 2]), oPolygon), true);
    oAssert.equal(oTurf.booleanPointInPolygon(oTurf.point([2, 8]), oPolygon), false);
});

fnTest('polygon edges and vertices count as hits unless explicitly ignored', () => {
    const oPolygon = oTurf.polygon([aOuterRing]);
    for (const aCoordinates of [[0, 5], [0, 0]]) {
        const oPoint = oTurf.point(aCoordinates);
        oAssert.equal(oTurf.booleanPointInPolygon(oPoint, oPolygon), true);
        oAssert.equal(oTurf.booleanPointInPolygon(oPoint, oPolygon, {
            ignoreBoundary: true
        }), false);
    }
});

fnTest('polygon holes exclude their interiors and respect boundary options', () => {
    const oPolygon = oTurf.polygon([aOuterRing, aHole]);
    oAssert.equal(oTurf.booleanPointInPolygon(oTurf.point([1, 1]), oPolygon), true);
    oAssert.equal(oTurf.booleanPointInPolygon(oTurf.point([5, 5]), oPolygon), false);
    oAssert.equal(oTurf.booleanPointInPolygon(oTurf.point([3, 5]), oPolygon), true);
    oAssert.equal(oTurf.booleanPointInPolygon(oTurf.point([3, 5]), oPolygon, {
        ignoreBoundary: true
    }), false);
});

fnTest('concave polygons do not select points merely inside their bounding box', () => {
    const oPolygon = oTurf.polygon([
        [[0, 0], [4, 0], [4, 1], [1, 1], [1, 4], [0, 4], [0, 0]]
    ]);
    oAssert.equal(oTurf.booleanPointInPolygon(oTurf.point([0.5, 3]), oPolygon), true);
    oAssert.equal(oTurf.booleanPointInPolygon(oTurf.point([3, 3]), oPolygon), false);
});

fnTest('multipolygon geometry and feature inputs remain supported', () => {
    const oMultiPolygon = oTurf.multiPolygon([
        [aOuterRing, aHole],
        [[[20, 20], [30, 20], [30, 30], [20, 30], [20, 20]]]
    ]);
    oAssert.equal(oTurf.booleanPointInPolygon(oTurf.point([25, 25]), oMultiPolygon), true);
    oAssert.equal(oTurf.booleanPointInPolygon([25, 25], oMultiPolygon.geometry), true);
    oAssert.equal(oTurf.booleanPointInPolygon(oTurf.point([5, 5]), oMultiPolygon), false);
    oAssert.equal(oTurf.booleanPointInPolygon(oTurf.point([15, 15]), oMultiPolygon), false);
});

fnTest('invalid points and unclosed polygons still surface errors', () => {
    oAssert.throws(() => oTurf.point([1]), /at least 2 numbers/);
    oAssert.throws(() => oTurf.polygon([
        [[0, 0], [10, 0], [10, 10], [0, 10]]
    ]), /First and last Position are not equivalent/);
    oAssert.throws(() => oTurf.booleanPointInPolygon(null, oTurf.polygon([aOuterRing])),
        /point is required/);
});
