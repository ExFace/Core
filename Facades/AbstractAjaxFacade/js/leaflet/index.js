/**
 * Exposes the same Turf subset as the original Leaflet browser bundle.
 * See UPGRADE.md for rebuilding and compatibility checks.
 */
import * as oHelpers from '@turf/helpers';
import * as oInvariant from '@turf/invariant';
import fnBooleanPointInPolygon from '@turf/boolean-point-in-polygon';

if (typeof window !== 'undefined') {
    window.turf = {
        booleanPointInPolygon: fnBooleanPointInPolygon,
        ...oHelpers,
        ...oInvariant
    };
}
