import 'ol/ol.css';
import Map from 'ol/Map.js';
import View from 'ol/View.js';
import { ScaleLine, defaults as defaultControls } from 'ol/control.js';
import { createEmpty, extend, isEmpty } from 'ol/extent.js';
import { transformExtent } from 'ol/proj.js';
import { createBasemaps } from './basemaps.js';

export const FIT_OPTIONS = { padding: [40, 40, 40, 40], maxZoom: 18, duration: 0 };

/** Indonesia, used when nothing else defines the view. */
export const DEFAULT_EXTENT = [94.5, -11.5, 141.5, 6.5];

/**
 * Map with EPSG:3857 view, zoom + scale line, and the OSM/Imagery basemaps
 * wired to the given toggle buttons. Resolves to { map, basemaps }.
 */
export function createMap(target, { basemapButtons = [], basemap, onBasemapChange } = {}) {
    const basemaps = createBasemaps(basemapButtons, { initial: basemap, onChange: onBasemapChange });

    const map = new Map({
        target,
        layers: basemaps.layers,
        view: new View({ center: [0, 0], zoom: 2 }),
        controls: defaultControls().extend([new ScaleLine()]),
    });

    return { map, basemaps };
}

const isValidExtent = (extent) => Array.isArray(extent) && extent.length === 4 && extent.every(Number.isFinite);

/** Fits the view to one or more [minLon, minLat, maxLon, maxLat] extents. Returns false if none is valid. */
export function fitExtents(map, extents, options = {}) {
    const union = createEmpty();

    extents.filter(isValidExtent).forEach((extent) => extend(union, extent));

    if (isEmpty(union)) return false;

    const projection = map.getView().getProjection();
    map.getView().fit(transformExtent(union, 'EPSG:4326', projection), { ...FIT_OPTIONS, ...options });

    return true;
}
