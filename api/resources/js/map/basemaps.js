import TileLayer from 'ol/layer/Tile.js';
import OSM from 'ol/source/OSM.js';
import XYZ from 'ol/source/XYZ.js';

export const BASEMAP_NAMES = ['osm', 'imagery'];

/**
 * OSM and Esri World Imagery, one visible at a time.
 * Buttons are matched by their data-basemap attribute ("osm" | "imagery").
 * Resolves to { layers, select(name), current() }.
 */
export function createBasemaps(buttons, { initial = 'osm', onChange } = {}) {
    const layers = {
        osm: new TileLayer({ source: new OSM() }),
        imagery: new TileLayer({
            visible: false,
            source: new XYZ({
                url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
                attributions: 'Imagery © Esri, Maxar, Earthstar Geographics, and the GIS User Community',
                maxZoom: 19,
            }),
        }),
    };

    let current = null;

    function select(name, { silent = false } = {}) {
        if (!layers[name] || name === current) return;

        current = name;
        Object.entries(layers).forEach(([key, layer]) => layer.setVisible(key === name));
        buttons.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.basemap === name)));
        if (!silent) onChange?.(name);
    }

    buttons.forEach((button) => {
        button.addEventListener('click', () => select(button.dataset.basemap));
    });

    // Initial choice is not a user change; callers may not be ready to react yet.
    select(BASEMAP_NAMES.includes(initial) ? initial : 'osm', { silent: true });

    return { layers: Object.values(layers), select, current: () => current };
}
