import TileLayer from 'ol/layer/Tile.js';
import OSM from 'ol/source/OSM.js';
import XYZ from 'ol/source/XYZ.js';

/**
 * OSM and Esri World Imagery, one visible at a time.
 * Buttons are matched by their data-basemap attribute ("osm" | "imagery").
 */
export function createBasemaps(buttons) {
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

    function select(name) {
        Object.entries(layers).forEach(([key, layer]) => layer.setVisible(key === name));
        buttons.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.basemap === name)));
    }

    buttons.forEach((button) => {
        button.addEventListener('click', () => select(button.dataset.basemap));
    });

    select('osm');

    return Object.values(layers);
}
