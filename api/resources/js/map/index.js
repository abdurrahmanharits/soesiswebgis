import { DATASET_PREVIEW_BASE } from '../datasets/api.js';
import { t } from '../i18n.js';
import { createMap, fitExtents } from './core.js';
import { legendParts } from './legend.js';

const $ = (id) => document.getElementById(id);

function renderLegend(element, legend) {
    if (!legend) {
        element.hidden = true;
        return;
    }

    const { gradient, min, max } = legendParts(legend);

    element.querySelector('[data-legend-bar]').style.background = gradient;
    element.querySelector('[data-legend-min]').textContent = min;
    element.querySelector('[data-legend-max]').textContent = max;
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
 * Single-dataset preview inside the static markup of datasets/partials/preview.blade.php.
 * Called once per page, after the dataset reaches ready_for_curation.
 * The public catalogue passes previewBase '/api/catalogue' (published datasets only).
 */
export async function initDatasetPreview(dataset, { previewBase = DATASET_PREVIEW_BASE } = {}) {
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

    const { map } = createMap(ui.map, { basemapButtons: ui.basemapButtons });

    function showError(message) {
        ui.loading.hidden = true;
        ui.error.hidden = false;
        ui.error.querySelector('[data-message]').textContent = message || t('Preview could not be loaded.');
    }

    ui.loading.hidden = false;
    ui.error.hidden = true;

    let result;

    try {
        // Loaded per format so vector pages don't pull in the GeoTIFF decoder.
        if (dataset.format === 'geotiff') {
            const { loadRasterLayer } = await import('./raster.js');
            result = await loadRasterLayer(dataset, { previewBase });
            map.addLayer(result.layer);
        } else {
            const [{ loadVectorLayer }, { createInspector }] = await Promise.all([import('./vector.js'), import('./inspect.js')]);
            result = await loadVectorLayer(dataset, { projection: map.getView().getProjection(), previewBase });
            map.addLayer(result.layer);
            createInspector(map, ui.popup, { describe: (layer) => (layer === result.layer ? { title: null } : null) });
        }
    } catch (error) {
        showError(error.message);
        return map;
    }

    const { layer, extent, notices } = result;

    if (extent) {
        fitExtents(map, [extent]);
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
                showError(`${t('Preview could not be loaded.')} ${result.source.getError()?.message ?? ''}`.trim());
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
