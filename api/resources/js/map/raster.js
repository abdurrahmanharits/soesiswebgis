import WebGLTileLayer from 'ol/layer/WebGLTile.js';
import { get as getProjection, transformExtent } from 'ol/proj.js';
import { register } from 'ol/proj/proj4.js';
import GeoTIFF from 'ol/source/GeoTIFF.js';
import proj4 from 'proj4';
import { getRasterInfo } from '../datasets/api.js';

// Low → high values; reads as a hypsometric (terrain) ramp for DEMs.
export const RAMP = [
    [43, 122, 61],
    [143, 191, 90],
    [232, 217, 138],
    [185, 138, 82],
    [140, 90, 60],
    [242, 242, 242],
];

/**
 * Makes the dataset CRS known to OpenLayers using the proj4 definition from
 * PostGIS spatial_ref_sys, so the COG can be reprojected to the map view.
 */
function ensureProjection(srid, definition) {
    const code = `EPSG:${srid}`;

    if (!getProjection(code)) {
        proj4.defs(code, definition);
        register(proj4);
    }

    return code;
}

function rampStyle({ minimum, maximum }, hasNodata) {
    const max = maximum > minimum ? maximum : minimum + 1;
    const stops = RAMP.flatMap((rgb, i) => [minimum + ((max - minimum) * i) / (RAMP.length - 1), [...rgb, 1]]);
    const ramp = ['interpolate', ['linear'], ['band', 1], ...stops];

    // With nodata configured, GeoTIFF adds an alpha band (0 = nodata, 255 = valid).
    return {
        color: hasNodata ? ['case', ['<', ['band', 2], 128], [0, 0, 0, 0], ramp] : ramp,
    };
}

/**
 * Loads the private COG through the Range-enabled preview endpoint.
 * Resolves to { layer, extent (EPSG:4326 or null), notices, legend }.
 */
export async function loadRasterLayer(map, dataset) {
    const info = await getRasterInfo(dataset.id);

    ensureProjection(info.srid, info.proj4);

    const nodata = Array.isArray(info.nodata) ? info.nodata[0] : info.nodata;
    const hasNodata = nodata !== null && nodata !== undefined;
    const statistics = info.statistics;

    const source = new GeoTIFF({
        sources: [{ url: info.url, ...(hasNodata ? { nodata } : {}) }],
        // Raw values are needed to map real min/max onto the ramp. Without
        // statistics, fall back to OpenLayers' default normalized grayscale.
        normalize: !statistics,
    });

    const layer = new WebGLTileLayer({
        source,
        opacity: 0.85,
        ...(statistics ? { style: rampStyle(statistics, hasNodata) } : {}),
    });

    map.addLayer(layer);

    const notices = [];
    let extent = info.extent;

    if (!extent) {
        try {
            const view = await source.getView();
            extent = transformExtent(view.extent, view.projection, 'EPSG:4326');
        } catch {
            notices.push('Raster loaded, but dataset extent is unavailable.');
        }
    }

    if (!statistics) {
        notices.push('Value statistics unavailable. Showing grayscale.');
    }

    return {
        layer,
        source,
        extent,
        notices,
        legend: statistics ? { ...statistics, ramp: RAMP } : null,
    };
}
