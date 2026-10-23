import { createPopup } from './popup.js';
import { SELECTED_STYLE } from './vector.js';

/**
 * Click-to-inspect for one or many vector layers. Every feature under the
 * click is highlighted and listed, grouped by layer (topmost first).
 *
 * describe(layer) returns { title } for inspectable layers, or null to ignore
 * the layer. A null title omits layer names (single-dataset preview).
 */
export function createInspector(map, popupElement, { describe }) {
    let selected = [];

    const popup = createPopup(map, popupElement, {
        onClose() {
            selected.forEach((feature) => feature.setStyle(undefined));
            selected = [];
        },
    });

    const isInspectable = (layer) => Boolean(layer?.getVisible() && describe(layer));

    function hitsAt(pixel) {
        const hits = [];

        map.forEachFeatureAtPixel(
            pixel,
            (feature, layer) => {
                hits.push({ feature, info: describe(layer) });
            },
            { layerFilter: isInspectable, hitTolerance: 2 },
        );

        return hits;
    }

    map.on('singleclick', (event) => {
        popup.close();

        const hits = hitsAt(event.pixel);

        if (hits.length === 0) return;

        selected = hits.map(({ feature }) => feature);
        selected.forEach((feature) => feature.setStyle(SELECTED_STYLE));

        const sections = hits.map(({ feature, info }) => {
            const { geometry, ...properties } = feature.getProperties();
            const id = feature.getId();
            const featureLabel = id === undefined ? 'Feature' : `Feature ${id}`;

            return {
                heading: info.title ? `${info.title} · ${featureLabel}` : (hits.length > 1 ? featureLabel : null),
                properties,
            };
        });

        const first = hits[0];
        const firstId = first.feature.getId();
        const title = hits.length > 1
            ? `${hits.length} features`
            : (first.info.title ?? (firstId === undefined ? 'Feature' : `Feature ${firstId}`));

        if (hits.length === 1 && first.info.title) {
            sections[0].heading = firstId === undefined ? null : `Feature ${firstId}`;
        }

        popup.open(event.coordinate, title, sections);
    });

    map.on('pointermove', (event) => {
        if (event.dragging) return;
        const hit = map.hasFeatureAtPixel(event.pixel, { layerFilter: isInspectable, hitTolerance: 2 });
        map.getTargetElement().style.cursor = hit ? 'pointer' : '';
    });

    return popup;
}
