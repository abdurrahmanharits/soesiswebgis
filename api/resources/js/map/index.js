import 'ol/ol.css';
import Map from 'ol/Map.js';
import View from 'ol/View.js';
import { ScaleLine, defaults as defaultControls } from 'ol/control.js';
import { transformExtent } from 'ol/proj.js';
import { createBasemaps } from './basemaps.js';

const $ = (id) => document.getElementById(id);

const FIT_OPTIONS = { padding: [40, 40, 40, 40], maxZoom: 18, duration: 0 };

function formatValue(value) {
    return Number(value).toLocaleString('en-US', { maximumFractionDigits: 2 });
}

function renderLegend(element, legend) {
    if (!legend) {
        element.hidden = true;
        return;
    }

    const gradient = legend.ramp.map((rgb) => `rgb(${rgb.join(' ')})`).join(', ');

    element.querySelector('[data-legend-bar]').style.background = `linear-gradient(to right, ${gradient})`;
    element.querySelector('[data-legend-min]').textContent = formatValue(legend.minimum);
    element.querySelector('[data-legend-max]').textContent = formatValue(legend.maximum);
    element.hidden = false;
}

function renderNotices(element, notices) {
    element.replaceChildren(
        ...notices.map((text) => {
            const p = document.createElement('p');
            p.textContent = text;
            return p;
        }),
    );
    element.hidden = notices.length === 0;
}

/**
 * Builds the preview map inside the static markup of datasets/show.blade.php.
 * Called once per page, after the dataset reaches ready_for_curation.
 */
export async function initDatasetPreview(dataset) {
    const ui = {
        map: $('preview-map'),
        loading: $('preview-loading'),
        error: $('preview-error'),
        popup: $('preview-popup'),
        toggle: $('preview-layer-toggle'),
        opacityWrap: $('preview-opacity-wrap'),
        opacity: $('preview-opacity'),
        opacityValue: $('preview-opacity-value'),
        legend: $('preview-legend'),
        notices: $('preview-notices'),
        basemapButtons: [...document.querySelectorAll('[data-basemap]')],
    };

    const map = new Map({
        target: ui.map,
        layers: createBasemaps(ui.basemapButtons),
        view: new View({ center: [0, 0], zoom: 2 }),
        controls: defaultControls().extend([new ScaleLine()]),
    });

    function showError(message) {
        ui.loading.hidden = true;
        ui.error.hidden = false;
        ui.error.querySelector('[data-message]').textContent = message || 'Preview could not be loaded.';
    }

    ui.loading.hidden = false;
    ui.error.hidden = true;

    let result;

    try {
        // Loaded per format so vector pages don't pull in the GeoTIFF decoder.
        result = dataset.format === 'geotiff'
            ? await import('./raster.js').then((m) => m.loadRasterLayer(map, dataset))
            : await import('./vector.js').then((m) => m.loadVectorLayer(map, dataset, ui));
    } catch (error) {
        showError(error.message);
        return map;
    }

    const { layer, extent, notices } = result;

    if (extent) {
        map.getView().fit(transformExtent(extent, 'EPSG:4326', map.getView().getProjection()), FIT_OPTIONS);
    }

    ui.toggle.disabled = false;
    ui.toggle.checked = true;
    ui.toggle.addEventListener('change', () => layer.setVisible(ui.toggle.checked));

    if (dataset.format === 'geotiff') {
        ui.opacityWrap.hidden = false;
        ui.opacity.value = String(Math.round(layer.getOpacity() * 100));
        ui.opacityValue.textContent = `${ui.opacity.value}%`;
        ui.opacity.addEventListener('input', () => {
            layer.setOpacity(Number(ui.opacity.value) / 100);
            ui.opacityValue.textContent = `${ui.opacity.value}%`;
        });

        // COG tiles arrive through several Range requests; surface a failed source.
        result.source.on('change', () => {
            if (result.source.getState() === 'error') {
                showError(`Preview could not be loaded. ${result.source.getError()?.message ?? ''}`.trim());
            }
        });
    }

    renderLegend(ui.legend, result.legend);
    renderNotices(ui.notices, notices);

    // Keep the loading overlay until the first full render (all visible tiles loaded).
    map.once('rendercomplete', () => {
        ui.loading.hidden = true;
    });

    return map;
}
