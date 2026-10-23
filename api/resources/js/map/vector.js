import GeoJSON from 'ol/format/GeoJSON.js';
import VectorLayer from 'ol/layer/Vector.js';
import { transformExtent } from 'ol/proj.js';
import VectorSource from 'ol/source/Vector.js';
import { Circle, Fill, Stroke, Style } from 'ol/style.js';
import { getVectorPreview } from '../datasets/api.js';

export const DEFAULT_COLOR = [4, 120, 87];

function style(fill, stroke, width) {
    return new Style({
        fill: new Fill({ color: fill }),
        stroke: new Stroke({ color: stroke, width }),
        image: new Circle({
            radius: 5,
            fill: new Fill({ color: stroke }),
            stroke: new Stroke({ color: '#ffffff', width: 1.5 }),
        }),
    });
}

/** Outline + translucent fill in the given [r, g, b]. */
export function vectorStyle([r, g, b] = DEFAULT_COLOR) {
    return style(`rgba(${r}, ${g}, ${b}, 0.18)`, `rgb(${r}, ${g}, ${b})`, 2);
}

export const SELECTED_STYLE = style('rgba(245, 158, 11, 0.3)', '#b45309', 3);

/**
 * Fetches the GeoJSON preview and builds a vector layer (not added to a map).
 * Resolves to { layer, extent (EPSG:4326 or null), notices }.
 */
export async function loadVectorLayer(dataset, { projection = 'EPSG:3857', color = DEFAULT_COLOR } = {}) {
    const collection = await getVectorPreview(dataset.id);

    const features = new GeoJSON().readFeatures(collection, {
        dataProjection: 'EPSG:4326',
        featureProjection: projection,
    });

    const source = new VectorSource({ features });
    const layer = new VectorLayer({ source, style: vectorStyle(color) });

    const notices = [];
    const { preview } = collection;

    if (features.length === 0) {
        notices.push('No features available for preview.');
    } else if (preview?.truncated) {
        notices.push(`Preview shows the first ${preview.limit.toLocaleString('en-US')} of ${preview.total.toLocaleString('en-US')} features.`);
    }

    let extent = Array.isArray(collection.bbox) ? collection.bbox : null;

    if (!extent && features.length > 0) {
        extent = transformExtent(source.getExtent(), projection, 'EPSG:4326');
    }

    return { layer, extent, notices };
}
