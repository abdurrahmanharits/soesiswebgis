import { getDataset } from './api.js';
import {
    DASH,
    datasetTitle,
    escapeHtml,
    formatBadge,
    formatDate,
    formatNumber,
    isPending,
    LIFECYCLE,
    lifecycleBadge,
    orDash,
    statusBadge,
} from './format.js';
import { createCurationPanel } from './curation.js';
import { createStatusPoller } from './poller.js';
import { showToast } from '../ui/toast.js';

const $ = (id) => document.getElementById(id);

function row(label, value, { mono = false } = {}) {
    return `<div class="grid grid-cols-1 gap-1 px-5 py-3 sm:grid-cols-3 sm:gap-4">
        <dt class="text-sm text-slate-500">${escapeHtml(label)}</dt>
        <dd class="text-sm break-all text-slate-900 sm:col-span-2 ${mono ? 'font-mono text-[13px]' : ''}">${value}</dd>
    </div>`;
}

function section(title, rows, extra = '') {
    return `<section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <h2 class="border-b border-slate-200 px-5 py-3 text-sm font-semibold text-slate-900">${escapeHtml(title)}</h2>
        <dl class="divide-y divide-slate-100">${rows.join('')}</dl>
        ${extra}
    </section>`;
}

const coord = (value) => formatNumber(value, { maximumFractionDigits: 8 });

/** ogrinfo extent: [minX, minY, maxX, maxY]. */
function formatBbox(bbox) {
    if (!Array.isArray(bbox) || bbox.length !== 4) return DASH;

    const [minX, minY, maxX, maxY] = bbox;

    return `<span class="grid grid-cols-[auto_1fr] gap-x-3 font-mono text-[13px]">
        <span class="text-slate-500">min X</span><span>${coord(minX)}</span>
        <span class="text-slate-500">min Y</span><span>${coord(minY)}</span>
        <span class="text-slate-500">max X</span><span>${coord(maxX)}</span>
        <span class="text-slate-500">max Y</span><span>${coord(maxY)}</span>
    </span>`;
}

function formatNodata(nodata) {
    if (nodata === null || nodata === undefined) return DASH;

    const values = Array.isArray(nodata) ? nodata : [nodata];

    return values.length ? values.map((v) => escapeHtml(v ?? 'none')).join(', ') : DASH;
}

function attributeSchema(fields) {
    if (!Array.isArray(fields) || fields.length === 0) return '';

    const rows = fields.map((f) => `<tr>
        <td class="px-5 py-2 font-mono text-[13px] text-slate-900">${escapeHtml(f.name)}</td>
        <td class="px-5 py-2 text-slate-700">${escapeHtml(f.type)}${f.subType ? ` <span class="text-slate-400">(${escapeHtml(f.subType)})</span>` : ''}</td>
        <td class="px-5 py-2 text-slate-600">${f.width ?? DASH}${f.precision ? `.${escapeHtml(f.precision)}` : ''}</td>
    </tr>`).join('');

    return `<details class="group border-t border-slate-200">
        <summary class="flex cursor-pointer list-none items-center justify-between px-5 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Attribute schema (${fields.length} ${fields.length === 1 ? 'field' : 'fields'})
            <span class="text-slate-400 transition group-open:rotate-90" aria-hidden="true">›</span>
        </summary>
        <div class="overflow-x-auto border-t border-slate-100">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 uppercase">
                    <tr><th class="px-5 py-2 font-medium">Field</th><th class="px-5 py-2 font-medium">Type</th><th class="px-5 py-2 font-medium">Width</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">${rows}</tbody>
            </table>
        </div>
    </details>`;
}

function rawMetadata(metadata) {
    if (!metadata) return '';

    return `<details class="group overflow-hidden rounded-lg border border-slate-200 bg-white">
        <summary class="flex cursor-pointer list-none items-center justify-between px-5 py-3 text-sm font-semibold text-slate-900 hover:bg-slate-50">
            Raw GDAL/OGR metadata
            <span class="text-slate-400 transition group-open:rotate-90" aria-hidden="true">›</span>
        </summary>
        <pre class="max-h-96 overflow-auto border-t border-slate-200 bg-slate-50 px-5 py-4 text-xs leading-5 text-slate-700">${escapeHtml(JSON.stringify(metadata, null, 2))}</pre>
    </details>`;
}

function statusNotice(d) {
    if (d.status === 'failed') {
        return `<div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 px-5 py-4">
            <p class="text-sm font-semibold text-rose-900">Processing failed</p>
            <p class="mt-1 text-sm break-words text-rose-800">${orDash(d.error_message)}</p>
        </div>`;
    }

    if (isPending(d)) {
        return `<div class="flex items-center gap-3 rounded-lg border border-sky-200 bg-sky-50 px-5 py-4 text-sm text-sky-900">
            <span class="size-4 shrink-0 animate-spin rounded-full border-2 border-sky-300 border-t-sky-700" aria-hidden="true"></span>
            ${d.status === 'queued' ? 'Waiting in the processing queue.' : 'Processing in the background.'}
            This page updates automatically.
        </div>`;
    }

    return '';
}

function render(d) {
    const common = section('General', [
        row('Title', orDash(d.title)),
        row('Original filename', orDash(d.original_filename), { mono: true }),
        row('Format', formatBadge(d.format)),
        row('Processing status', statusBadge(d.status)),
        row('Review status', d.status === 'ready_for_curation' ? statusBadge(d.curation_status, LIFECYCLE) : DASH),
        row('CRS', orDash(d.crs), { mono: true }),
        row('SRID', orDash(d.srid), { mono: true }),
        row('Created at', formatDate(d.created_at)),
        row('Updated at', formatDate(d.updated_at)),
        row('Dataset ID', orDash(d.id), { mono: true }),
    ]);

    let specific = '';

    if (d.format === 'shapefile') {
        specific = section('Vector', [
            row('Geometry type', orDash(d.geometry_type)),
            row('Feature count', formatNumber(d.feature_count)),
            row('PostGIS schema', orDash(d.postgis_schema), { mono: true }),
            row('PostGIS table', orDash(d.postgis_table), { mono: true }),
            row('Bounding box', formatBbox(d.bbox)),
        ], attributeSchema(d.attribute_schema));
    } else if (d.format === 'geotiff') {
        specific = section('Raster', [
            row('Raster width', d.raster_width == null ? DASH : `${formatNumber(d.raster_width)} px`),
            row('Raster height', d.raster_height == null ? DASH : `${formatNumber(d.raster_height)} px`),
            row('Band count', formatNumber(d.band_count)),
            row('Pixel size X', orDash(d.pixel_size_x), { mono: true }),
            row('Pixel size Y', orDash(d.pixel_size_y), { mono: true }),
            row('NoData', formatNodata(d.nodata), { mono: true }),
            row('Processed path (COG)', orDash(d.processed_path), { mono: true }),
        ]);
    }

    $('detail-title').textContent = datasetTitle(d);
    $('detail-subtitle').textContent = d.original_filename ?? '';
    $('detail-badges').innerHTML = `${formatBadge(d.format)} ${lifecycleBadge(d)}`;
    $('detail-notice').innerHTML = statusNotice(d);
    $('detail-body').innerHTML = `
        <div class="grid gap-6 lg:grid-cols-2 lg:items-start">${common}${specific}</div>
        ${rawMetadata(d.metadata)}`;

    document.title = `${datasetTitle(d)} · SoESIS WebGIS`;
}

const PREVIEW_MESSAGES = {
    queued: 'Dataset is queued for processing. Preview will be available when processing is complete.',
    processing: 'Dataset is being processed. Preview will load automatically when the dataset is ready.',
    failed: 'Preview unavailable because dataset processing failed.',
};

/**
 * Shows the map section for ready datasets and lazy-loads OpenLayers once;
 * later polls never rebuild the map.
 */
function createPreviewController() {
    let started = false;

    return function sync(d) {
        const previewable = ['shapefile', 'geotiff'].includes(d.format);

        $('preview-section').hidden = !previewable;
        if (!previewable || started) return;

        const ready = d.status === 'ready_for_curation';
        const message = $('preview-message');

        message.hidden = ready;
        message.textContent = PREVIEW_MESSAGES[d.status] ?? '';
        $('preview-body').hidden = !ready;
        $('preview-basemaps').hidden = !ready;

        if (!ready) return;

        started = true;

        import('../map/index.js')
            .then((m) => m.initDatasetPreview(d))
            .catch(() => {
                $('preview-loading').hidden = true;
                $('preview-error').hidden = false;
            });
    };
}

export function initDatasetDetail(root) {
    const id = root.dataset.datasetId;
    const syncPreview = createPreviewController();
    let current = null;

    function apply(dataset) {
        current = dataset;
        render(dataset);
        syncPreview(dataset);
        curation.sync(dataset);
    }

    const curation = createCurationPanel({
        // null = state changed elsewhere (409); reload the current record.
        onChange(dataset) {
            if (dataset) {
                apply(dataset);
            } else {
                getDataset(id).then(apply).catch(() => {});
            }
        },
    });

    const poller = createStatusPoller({
        onUpdate(dataset) {
            if (current && current.status !== dataset.status) {
                if (dataset.status === 'ready_for_curation') showToast('Processing finished. Dataset is ready.', 'success');
                if (dataset.status === 'failed') showToast('Processing failed.', 'error');
            }

            apply(dataset);
        },
    });

    async function load() {
        $('detail-loading').hidden = false;
        $('detail-error').hidden = true;
        $('detail-content').hidden = true;

        try {
            const dataset = await getDataset(id);
            $('detail-content').hidden = false;
            apply(dataset);

            if (isPending(current)) poller.track(id);
        } catch (error) {
            const message = error.status === 404 ? 'Dataset not found.' : error.message;

            $('detail-error').hidden = false;
            $('detail-error').querySelector('[data-message]').textContent = message;
        } finally {
            $('detail-loading').hidden = true;
        }
    }

    $('detail-retry').addEventListener('click', load);

    load();
}
