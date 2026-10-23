/**
 * Presentation helpers shared by the dataset list and detail pages.
 * All functions that return HTML escape their input.
 */

export const STATUS = {
    queued: {
        label: 'Queued',
        className: 'bg-slate-100 text-slate-700 ring-slate-300',
        dot: 'bg-slate-400',
    },
    processing: {
        label: 'Processing',
        className: 'bg-sky-50 text-sky-800 ring-sky-200',
        dot: 'bg-sky-500 animate-pulse',
    },
    ready_for_curation: {
        label: 'Ready',
        className: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        dot: 'bg-emerald-500',
    },
    failed: {
        label: 'Failed',
        className: 'bg-rose-50 text-rose-800 ring-rose-200',
        dot: 'bg-rose-500',
    },
};

/**
 * One status per dataset for list badges/filters: ingestion state until the
 * dataset is ready, then its curation state.
 */
export const LIFECYCLE = {
    queued: STATUS.queued,
    processing: STATUS.processing,
    failed: STATUS.failed,
    pending: {
        label: 'Pending review',
        className: 'bg-amber-50 text-amber-800 ring-amber-200',
        dot: 'bg-amber-500',
    },
    approved: {
        label: 'Approved',
        className: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        dot: 'bg-emerald-500',
    },
    rejected: {
        label: 'Rejected',
        className: 'bg-orange-50 text-orange-800 ring-orange-200',
        dot: 'bg-orange-500',
    },
};

export function lifecycleKey(dataset) {
    return dataset.status === 'ready_for_curation' ? (dataset.curation_status || 'pending') : dataset.status;
}

/** ISO 19115 MD_TopicCategoryCode → label. Keys mirror DatasetCurationController::TOPIC_CATEGORIES. */
export const TOPIC_CATEGORIES = {
    biota: 'Biota',
    boundaries: 'Boundaries',
    climatologyMeteorologyAtmosphere: 'Climatology / meteorology / atmosphere',
    economy: 'Economy',
    elevation: 'Elevation',
    environment: 'Environment',
    farming: 'Farming',
    geoscientificInformation: 'Geoscientific information',
    health: 'Health',
    imageryBaseMapsEarthCover: 'Imagery / base maps / earth cover',
    inlandWaters: 'Inland waters',
    intelligenceMilitary: 'Intelligence / military',
    location: 'Location',
    oceans: 'Oceans',
    planningCadastre: 'Planning / cadastre',
    society: 'Society',
    structure: 'Structure',
    transportation: 'Transportation',
    utilitiesCommunication: 'Utilities / communication',
};

/** Keys mirror DatasetCurationController::LICENSES. */
export const LICENSES = {
    'CC-BY-4.0': 'CC BY 4.0',
    'CC-BY-SA-4.0': 'CC BY-SA 4.0',
    'CC0-1.0': 'CC0 1.0',
    restricted: 'Restricted / internal',
    other: 'Other',
};

export const FORMAT = {
    shapefile: { label: 'Shapefile', className: 'bg-violet-50 text-violet-800 ring-violet-200' },
    geotiff: { label: 'GeoTIFF', className: 'bg-amber-50 text-amber-800 ring-amber-200' },
};

export const PENDING_STATUSES = ['queued', 'processing'];

export const isPending = (dataset) => PENDING_STATUSES.includes(dataset?.status);

export const DASH = '—';

export function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

export function orDash(value) {
    return value === null || value === undefined || value === '' ? DASH : escapeHtml(value);
}

export function statusBadge(status, map = STATUS) {
    const s = map[status] ?? { label: status || 'Unknown', className: 'bg-slate-100 text-slate-700 ring-slate-300', dot: 'bg-slate-400' };

    return `<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset ${s.className}">
        <span class="size-1.5 rounded-full ${s.dot}" aria-hidden="true"></span>${escapeHtml(s.label)}
    </span>`;
}

export function lifecycleBadge(dataset) {
    return statusBadge(lifecycleKey(dataset), LIFECYCLE);
}

export function formatBadge(format) {
    const f = FORMAT[format] ?? { label: format || 'Unknown', className: 'bg-slate-100 text-slate-700 ring-slate-300' };

    return `<span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${f.className}">${escapeHtml(f.label)}</span>`;
}

const dateFormatter = new Intl.DateTimeFormat('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
});

/** e.g. "23 Oct 2026, 15:52" in the browser's time zone. */
export function formatDate(value) {
    if (!value) return DASH;

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? DASH : escapeHtml(dateFormatter.format(date));
}

export function formatNumber(value, options = {}) {
    if (value === null || value === undefined || value === '') return DASH;

    const number = Number(value);

    return Number.isFinite(number) ? number.toLocaleString('en-US', options) : escapeHtml(value);
}

export function formatBytes(bytes) {
    if (!Number.isFinite(bytes)) return DASH;

    const units = ['B', 'KB', 'MB', 'GB'];
    let value = bytes;
    let unit = 0;

    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit++;
    }

    return `${value.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`;
}

/** Second line of the "Detail" column: geometry/features or raster size/bands. */
export function summaryDetail(dataset) {
    if (dataset.format === 'shapefile') {
        if (!dataset.geometry_type && dataset.feature_count == null) return DASH;

        return [
            orDash(dataset.geometry_type),
            dataset.feature_count == null ? null : `${formatNumber(dataset.feature_count)} ${Number(dataset.feature_count) === 1 ? 'feature' : 'features'}`,
        ].filter(Boolean).join('<br>');
    }

    if (dataset.format === 'geotiff') {
        if (dataset.raster_width == null) return DASH;

        const bands = dataset.band_count == null ? null : `${formatNumber(dataset.band_count)} ${Number(dataset.band_count) === 1 ? 'band' : 'bands'}`;

        return [
            `${formatNumber(dataset.raster_width)} × ${formatNumber(dataset.raster_height)} px`,
            bands,
        ].filter(Boolean).join('<br>');
    }

    return DASH;
}

export function datasetTitle(dataset) {
    return dataset.title || dataset.original_filename || 'Untitled dataset';
}
