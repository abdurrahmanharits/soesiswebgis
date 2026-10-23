import GeoJSON from 'ol/format/GeoJSON.js';
import VectorLayer from 'ol/layer/Vector.js';
import { transformExtent } from 'ol/proj.js';
import VectorSource from 'ol/source/Vector.js';
import { Circle, Fill, Stroke, Style } from 'ol/style.js';
import { getVectorPreview } from '../datasets/api.js';
import { createPopup } from './popup.js';

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

const DEFAULT_STYLE = style('rgba(4, 120, 87, 0.18)', '#047857', 2);
const SELECTED_STYLE = style('rgba(245, 158, 11, 0.3)', '#b45309', 3);

/**
 * Loads the GeoJSON preview into a vector layer with click-to-inspect attributes.
 * Resolves to { layer, extent (EPSG:4326 or null), notices }.
 */
export async function loadVectorLayer(map, dataset, ui) {
    const collection = await getVectorPreview(dataset.id);

    const features = new GeoJSON().readFeatures(collection, {
        dataProjection: 'EPSG:4326',
        featureProjection: map.getView().getProjection(),
    });

    const source = new VectorSource({ features });
    const layer = new VectorLayer({ source, style: DEFAULT_STYLE });

    map.addLayer(layer);

    let selected = null;

    const popup = createPopup(map, ui.popup, {
        onClose() {
            selected?.setStyle(undefined);
            selected = null;
        },
    });

    map.on('singleclick', (event) => {
        const feature = layer.getVisible()
            ? map.forEachFeatureAtPixel(event.pixel, (f) => f, { layerFilter: (l) => l === layer })
            : null;

        popup.close();

        if (!feature) return;

        selected = feature;
        feature.setStyle(SELECTED_STYLE);

        const { geometry, ...properties } = feature.getProperties();
        const id = feature.getId();

        popup.open(event.coordinate, id === undefined ? 'Feature' : `Feature ${id}`, properties);
    });

    map.on('pointermove', (event) => {
        if (event.dragging) return;
        const hit = layer.getVisible() && map.hasFeatureAtPixel(event.pixel, { layerFilter: (l) => l === layer });
        map.getTargetElement().style.cursor = hit ? 'pointer' : '';
    });

    const notices = [];
    const { preview } = collection;

    if (features.length === 0) {
        notices.push('No spatial features available for preview.');
    } else if (preview?.truncated) {
        notices.push(`Preview shows the first ${preview.limit.toLocaleString('en-US')} of ${preview.total.toLocaleString('en-US')} features.`);
    }

    let extent = Array.isArray(collection.bbox) ? collection.bbox : null;

    if (!extent && features.length > 0) {
        extent = transformExtent(source.getExtent(), map.getView().getProjection(), 'EPSG:4326');
    }

    return { layer, extent, notices };
}
