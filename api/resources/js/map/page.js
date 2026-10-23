import { fromLonLat, toLonLat } from 'ol/proj.js';
import { listMapLayers } from '../datasets/api.js';
import { escapeHtml, formatBadge, statusBadge, LIFECYCLE } from '../datasets/format.js';
import { showToast } from '../ui/toast.js';
import { DEFAULT_EXTENT, createMap, fitExtents } from './core.js';
import { createInspector } from './inspect.js';
import { legendParts } from './legend.js';
import { RAMP } from './ramp.js';
import { loadVectorLayer } from './vector.js';

export const MAX_LAYERS = 8;
export const MAX_RASTER_LAYERS = 3;

const RASTER_LIMIT_MESSAGE = 'Raster layer limit reached. Disable or remove another raster layer first.';

// Deterministic per dataset id, so a layer keeps its colour across reloads and shared links.
const PALETTE = [
    [4, 120, 87], [37, 99, 235], [192, 38, 211], [234, 88, 12],
    [124, 58, 237], [8, 145, 178], [190, 18, 60], [101, 163, 13],
];

function colorFor(id) {
    let hash = 0;
    for (const char of id) hash = (hash * 31 + char.charCodeAt(0)) >>> 0;
    return PALETTE[hash % PALETTE.length];
}

const $ = (id) => document.getElementById(id);
const isRaster = (item) => item.format === 'geotiff';

function readUrlState() {
    const params = new URLSearchParams(window.location.search);
    const number = (key) => {
        const value = Number.parseFloat(params.get(key));
        return Number.isFinite(value) ? value : null;
    };

    return {
        layers: (params.get('layers') ?? '').split(',').map((id) => id.trim()).filter(Boolean),
        basemap: params.get('basemap'),
        x: number('x'),
        y: number('y'),
        z: number('z'),
    };
}

function summary(item) {
    if (isRaster(item)) {
        return item.band_count ? `${item.band_count} ${item.band_count === 1 ? 'band' : 'bands'}` : '';
    }

    return [item.geometry_type, item.feature_count == null ? null : `${Number(item.feature_count).toLocaleString('en-US')} features`]
        .filter(Boolean)
        .join(' · ');
}

const pendingBadge = (item) => (item.curation_status === 'pending' ? statusBadge('pending', LIFECYCLE) : '');

/**
 * /map: catalogue of approved + pending datasets, active layer stack
 * (visibility, opacity, order, zoom, remove), shared inspector popup and
 * URL state (?layers=top,…,bottom&basemap=&x=&y=&z=).
 */
export function initMapPage() {
    const ui = {
        panel: $('map-panel'),
        panelOpen: $('map-panel-open'),
        panelClose: $('map-panel-close'),
        count: $('map-layer-count'),
        fit: $('map-fit'),
        active: $('map-active'),
        activeEmpty: $('map-active-empty'),
        search: $('map-search'),
        format: $('map-format'),
        catalogue: $('map-catalogue'),
        catalogueLoading: $('map-catalogue-loading'),
        catalogueError: $('map-catalogue-error'),
        catalogueRetry: $('map-catalogue-retry'),
        catalogueEmpty: $('map-catalogue-empty'),
        catalogueNoResults: $('map-catalogue-no-results'),
    };

    const initial = readUrlState();
    let catalogue = [];

    // URL writes start only after the startup restore has finished.
    let urlReady = false;

    /** Index 0 is the top of the stack. */
    const entries = [];

    const { map } = createMap($('map-canvas'), {
        basemapButtons: [...document.querySelectorAll('[data-basemap]')],
        basemap: initial.basemap ?? undefined,
        onBasemapChange: () => updateUrl(),
    });

    createInspector(map, $('map-popup'), {
        describe(layer) {
            const entry = entries.find((e) => e.layer === layer);
            return entry && !isRaster(entry.item) ? { title: entry.item.title } : null;
        },
    });

    // ---- URL state -------------------------------------------------------

    function updateUrl() {
        if (!urlReady) return;

        const view = map.getView();
        const center = view.getCenter();
        const params = new URLSearchParams();

        if (entries.length) params.set('layers', entries.map((e) => e.item.id).join(','));
        params.set('basemap', document.querySelector('[data-basemap][aria-pressed="true"]')?.dataset.basemap ?? 'osm');

        if (center) {
            const [x, y] = toLonLat(center, view.getProjection());
            params.set('x', x.toFixed(5));
            params.set('y', y.toFixed(5));
            params.set('z', view.getZoom().toFixed(2));
        }

        // Keep commas readable in shared links.
        history.replaceState(null, '', `${window.location.pathname}?${params.toString().replaceAll('%2C', ',')}`);
    }

    map.on('moveend', updateUrl);

    // ---- Layer stack -----------------------------------------------------

    function applyOrder() {
        entries.forEach((entry, index) => entry.layer?.setZIndex(100 + entries.length - index));
    }

    function canAdd(item) {
        if (entries.some((e) => e.item.id === item.id)) return { ok: false, reason: 'Already on the map.' };
        if (entries.length >= MAX_LAYERS) return { ok: false, reason: `Layer limit reached (${MAX_LAYERS}). Remove a layer first.` };
        if (isRaster(item) && entries.filter((e) => isRaster(e.item)).length >= MAX_RASTER_LAYERS) {
            return { ok: false, reason: RASTER_LIMIT_MESSAGE };
        }

        return { ok: true };
    }

    function addLayer(item, { fit = true, append = false } = {}) {
        const check = canAdd(item);

        if (!check.ok) {
            showToast(check.reason, 'error');
            return;
        }

        const entry = {
            item,
            layer: null,
            source: null,
            status: 'loading',
            message: '',
            notices: [],
            legend: null,
            extent: item.extent,
            visible: true,
            opacity: isRaster(item) ? 0.85 : 1,
            color: colorFor(item.id),
        };

        // Default stacking: vectors on top, rasters directly beneath the vectors.
        let index = 0;
        if (append) {
            index = entries.length;
        } else if (isRaster(item)) {
            const firstRaster = entries.findIndex((e) => isRaster(e.item));
            index = firstRaster === -1 ? entries.length : firstRaster;
        }

        entries.splice(index, 0, entry);
        render();
        updateUrl();
        loadEntry(entry, { fit });
    }

    async function loadEntry(entry, { fit }) {
        entry.status = 'loading';
        entry.message = '';
        render();

        try {
            const result = isRaster(entry.item)
                ? await import('./raster.js').then((m) => m.loadRasterLayer(entry.item))
                : await loadVectorLayer(entry.item, { projection: map.getView().getProjection(), color: entry.color });

            // Removed while loading.
            if (!entries.includes(entry)) return;

            Object.assign(entry, {
                layer: result.layer,
                source: result.source ?? null,
                notices: result.notices,
                legend: result.legend ?? null,
                extent: result.extent ?? entry.item.extent,
                status: 'ready',
            });

            result.layer.setVisible(entry.visible);
            result.layer.setOpacity(entry.opacity);
            map.addLayer(result.layer);
            applyOrder();

            // A COG can fail after the header loads (Range requests); keep it per layer.
            entry.source?.on('change', () => {
                if (entry.source.getState() === 'error') {
                    entry.status = 'error';
                    entry.message = entry.source.getError()?.message || 'Raster could not be loaded.';
                    render();
                }
            });

            if (fit) fitExtents(map, [entry.extent]);
        } catch (error) {
            if (!entries.includes(entry)) return;
            entry.status = 'error';
            entry.message = error.message || 'Layer could not be loaded.';
        }

        render();
    }

    function removeLayer(entry) {
        const index = entries.indexOf(entry);
        if (index === -1) return;

        entries.splice(index, 1);
        if (entry.layer) map.removeLayer(entry.layer);
        applyOrder();
        render();
        updateUrl();
    }

    function retry(entry) {
        if (entry.layer) map.removeLayer(entry.layer);
        entry.layer = null;
        entry.source = null;
        loadEntry(entry, { fit: false });
    }

    function move(entry, delta) {
        const from = entries.indexOf(entry);
        const to = from + delta;
        if (to < 0 || to >= entries.length) return;

        entries.splice(from, 1);
        entries.splice(to, 0, entry);
        applyOrder();
        render();
        updateUrl();
    }

    function zoomTo(entry) {
        if (!fitExtents(map, [entry.extent])) showToast('Extent of this layer is unknown.', 'info');
    }

    function fitActive() {
        const extents = entries.filter((e) => e.visible && e.status === 'ready').map((e) => e.extent);
        if (!fitExtents(map, extents)) showToast('No visible layers with a known extent.', 'info');
    }

    // ---- Rendering -------------------------------------------------------

    const iconButton = (action, label, glyph, disabled = false) => `<button type="button" data-action="${action}" aria-label="${label}" title="${label}"
        class="grid size-7 place-items-center rounded text-slate-500 hover:bg-slate-100 hover:text-slate-900 disabled:opacity-30 disabled:hover:bg-transparent" ${disabled ? 'disabled' : ''}>${glyph}</button>`;

    function renderEntry(entry, index) {
        const { item } = entry;
        const rgb = entry.color.join(' ');
        const swatch = isRaster(item)
            ? `<span class="size-3 shrink-0 rounded-sm" style="background: ${legendParts({ minimum: 0, maximum: 1, ramp: RAMP }).gradient}; box-shadow: inset 0 0 0 1px rgb(0 0 0 / 0.15)" aria-hidden="true"></span>`
            : `<span class="size-3 shrink-0 rounded-sm" style="background: rgb(${rgb} / 0.2); box-shadow: inset 0 0 0 2px rgb(${rgb})" aria-hidden="true"></span>`;

        let status = '';
        if (entry.status === 'loading') {
            status = '<p class="mt-1 flex items-center gap-1.5 text-xs text-slate-500"><span class="size-3 animate-spin rounded-full border-2 border-slate-300 border-t-emerald-700" aria-hidden="true"></span>Loading…</p>';
        } else if (entry.status === 'error') {
            status = `<p class="mt-1 text-xs text-rose-700">Failed to load: ${escapeHtml(entry.message)}</p>
                <button type="button" data-action="retry" class="mt-1 text-xs font-medium text-slate-700 underline">Retry</button>`;
        } else if (entry.notices.length) {
            status = entry.notices.map((n) => `<p class="mt-1 text-xs text-amber-800">${escapeHtml(n)}</p>`).join('');
        }

        let legend = '';
        if (entry.legend && entry.status === 'ready') {
            const { gradient, min, max } = legendParts(entry.legend);
            legend = `<div class="mt-2">
                <div class="h-2 rounded-sm ring-1 ring-slate-200" style="background: ${gradient}"></div>
                <div class="mt-0.5 flex justify-between text-[11px] text-slate-500 tabular-nums"><span>${min}</span><span>Value (band 1)</span><span>${max}</span></div>
            </div>`;
        }

        const opacity = Math.round(entry.opacity * 100);

        return `<li data-index="${index}" class="rounded-md p-2 ring-1 ring-slate-200 ${entry.status === 'error' ? 'bg-rose-50/50' : 'bg-white'}">
            <div class="flex items-start gap-2">
                <input type="checkbox" data-action="visible" ${entry.visible ? 'checked' : ''} class="mt-0.5 size-4 shrink-0 accent-emerald-700" aria-label="Show ${escapeHtml(item.title)}">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">${swatch}<p class="truncate text-sm font-medium text-slate-900" title="${escapeHtml(item.title)}">${escapeHtml(item.title)}</p></div>
                    <div class="mt-1 flex flex-wrap items-center gap-1">${formatBadge(item.format)} ${pendingBadge(item)}</div>
                    ${status}
                </div>
            </div>
            <div class="mt-2 flex items-center gap-1">
                <label class="flex min-w-0 flex-1 items-center gap-1.5 text-xs text-slate-500">
                    <span class="sr-only">Opacity</span>
                    <input type="range" data-action="opacity" min="0" max="100" step="5" value="${opacity}" class="min-w-0 flex-1 accent-emerald-700">
                    <span data-opacity-value class="w-8 tabular-nums">${opacity}%</span>
                </label>
                ${iconButton('up', 'Move up', '↑', index === 0)}
                ${iconButton('down', 'Move down', '↓', index === entries.length - 1)}
                ${iconButton('zoom', 'Zoom to layer', '⤢', !entry.extent)}
                ${iconButton('remove', 'Remove layer', '✕')}
            </div>
            ${legend}
        </li>`;
    }

    function renderCatalogue() {
        const term = ui.search.value.trim().toLowerCase();
        const format = ui.format.value;
        const items = catalogue.filter((item) => (!format || item.format === format)
            && (!term || item.title.toLowerCase().includes(term)));

        ui.catalogueEmpty.hidden = catalogue.length > 0;
        ui.catalogueNoResults.hidden = catalogue.length === 0 || items.length > 0;

        ui.catalogue.innerHTML = items.map((item) => {
            const check = canAdd(item);
            const active = entries.some((e) => e.item.id === item.id);

            return `<li class="flex items-start gap-2 py-2" data-id="${escapeHtml(item.id)}">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm text-slate-900" title="${escapeHtml(item.title)}">${escapeHtml(item.title)}</p>
                    <div class="mt-1 flex flex-wrap items-center gap-1">${formatBadge(item.format)} ${pendingBadge(item)}</div>
                    <p class="mt-0.5 text-xs text-slate-500">${escapeHtml(summary(item))}</p>
                </div>
                <button type="button" data-action="add" ${check.ok ? '' : `disabled title="${escapeHtml(check.reason)}"`}
                        class="shrink-0 rounded-md px-2.5 py-1 text-xs font-medium ${active ? 'text-slate-400' : 'text-emerald-700 ring-1 ring-emerald-200 ring-inset hover:bg-emerald-50'} disabled:cursor-not-allowed disabled:opacity-60">
                    ${active ? 'Added' : 'Add'}
                </button>
            </li>`;
        }).join('');
    }

    function render() {
        ui.active.innerHTML = entries.map(renderEntry).join('');
        ui.activeEmpty.hidden = entries.length > 0;
        ui.count.textContent = `${entries.length} of ${MAX_LAYERS} active`;
        ui.fit.disabled = !entries.some((e) => e.status === 'ready' && e.extent);
        renderCatalogue();
    }

    // ---- Events ----------------------------------------------------------

    ui.active.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-action]');
        const entry = entries[Number(event.target.closest('li[data-index]')?.dataset.index)];
        if (!button || !entry) return;

        ({
            up: () => move(entry, -1),
            down: () => move(entry, 1),
            zoom: () => zoomTo(entry),
            remove: () => removeLayer(entry),
            retry: () => retry(entry),
        })[button.dataset.action]?.();
    });

    ui.active.addEventListener('change', (event) => {
        if (event.target.dataset.action !== 'visible') return;
        const entry = entries[Number(event.target.closest('li[data-index]').dataset.index)];

        entry.visible = event.target.checked;
        entry.layer?.setVisible(entry.visible);
        ui.fit.disabled = !entries.some((e) => e.status === 'ready' && e.extent);
    });

    // Update opacity in place: re-rendering would interrupt dragging the slider.
    ui.active.addEventListener('input', (event) => {
        if (event.target.dataset.action !== 'opacity') return;
        const li = event.target.closest('li[data-index]');
        const entry = entries[Number(li.dataset.index)];

        entry.opacity = Number(event.target.value) / 100;
        entry.layer?.setOpacity(entry.opacity);
        li.querySelector('[data-opacity-value]').textContent = `${event.target.value}%`;
    });

    ui.catalogue.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-action="add"]');
        const id = event.target.closest('li[data-id]')?.dataset.id;
        const item = catalogue.find((c) => c.id === id);
        if (button && item) addLayer(item);
    });

    ui.search.addEventListener('input', renderCatalogue);
    ui.format.addEventListener('change', renderCatalogue);
    ui.fit.addEventListener('click', fitActive);
    ui.panelOpen.addEventListener('click', () => ui.panel.setAttribute('data-open', ''));
    ui.panelClose.addEventListener('click', () => ui.panel.removeAttribute('data-open'));

    // ---- Startup ---------------------------------------------------------

    const hasView = initial.x !== null && initial.y !== null && initial.z !== null;

    if (hasView) {
        map.getView().setCenter(fromLonLat([initial.x, initial.y], map.getView().getProjection()));
        map.getView().setZoom(initial.z);
    } else {
        fitExtents(map, [DEFAULT_EXTENT]);
    }

    async function loadCatalogue() {
        ui.catalogueLoading.hidden = false;
        ui.catalogueError.hidden = true;

        try {
            catalogue = await listMapLayers();
        } catch (error) {
            ui.catalogueError.hidden = false;
            ui.catalogueError.querySelector('[data-message]').textContent = error.message;
            return false;
        } finally {
            ui.catalogueLoading.hidden = true;
        }

        render();
        return true;
    }

    ui.catalogueRetry.addEventListener('click', loadCatalogue);

    loadCatalogue().then((loaded) => {
        if (loaded && initial.layers.length) {
            const items = initial.layers.map((id) => catalogue.find((c) => c.id === id)).filter(Boolean);
            const missing = initial.layers.length - items.length;

            items.forEach((item) => addLayer(item, { fit: false, append: true }));

            if (missing > 0) {
                showToast(`${missing} layer${missing === 1 ? ' is' : 's are'} not available on the map.`, 'info');
            }

            if (!hasView) fitExtents(map, items.map((item) => item.extent));
        }

        urlReady = true;
        updateUrl();
    });
}
