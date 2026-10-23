/**
 * Thin client for the existing dataset API (routes/api.php).
 */

export class ApiError extends Error {
    constructor(message, { status = 0, errors = null } = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.errors = errors;
    }
}

async function requestJson(url, { method = 'GET', body } = {}) {
    let response;

    try {
        response = await fetch(url, {
            method,
            headers: {
                Accept: 'application/json',
                ...(body === undefined ? {} : { 'Content-Type': 'application/json' }),
            },
            body: body === undefined ? undefined : JSON.stringify(body),
        });
    } catch {
        throw new ApiError('Cannot reach the server. Check that the backend is running.');
    }

    const json = await response.json().catch(() => null);

    if (!response.ok) {
        throw new ApiError(
            json?.message || `Request failed (HTTP ${response.status}).`,
            { status: response.status, errors: json?.errors ?? null },
        );
    }

    return json;
}

const getJson = (url) => requestJson(url);

export async function listDatasets() {
    const body = await getJson('/api/datasets');

    return body?.data ?? [];
}

export async function getDataset(id) {
    const body = await getJson(`/api/datasets/${encodeURIComponent(id)}`);

    return body?.data ?? null;
}

const curationUrl = (id, action = '') => `/api/datasets/${encodeURIComponent(id)}/curation${action ? `/${action}` : ''}`;

/** Save curation metadata (pending datasets only). Resolves to { message, data: dataset }. */
export function updateCuration(id, payload) {
    return requestJson(curationUrl(id), { method: 'PATCH', body: payload });
}

/** action: 'approve' | 'reject' | 'reopen'. Resolves to { message, data: dataset }. */
export function curationAction(id, action, payload) {
    return requestJson(curationUrl(id, action), { method: 'POST', body: payload });
}

export async function getCurationHistory(id) {
    const body = await getJson(curationUrl(id, 'history'));

    return body?.data ?? [];
}

/** Lightweight /map catalogue: approved + pending ready datasets with EPSG:4326 extents. */
export async function listMapLayers() {
    const body = await getJson('/api/map/layers');

    return body?.data ?? [];
}

/** GeoJSON FeatureCollection (EPSG:4326) with `bbox` and `preview` {limit, total, truncated}. */
export function getVectorPreview(id) {
    return getJson(`/api/datasets/${encodeURIComponent(id)}/preview/vector`);
}

/** COG url, CRS (srid + proj4), WGS84 extent, nodata and approximate band statistics. */
export async function getRasterInfo(id) {
    const body = await getJson(`/api/datasets/${encodeURIComponent(id)}/preview/raster/info`);

    return body?.data ?? null;
}

/**
 * Upload with XMLHttpRequest because fetch() has no upload progress events.
 * Returns { promise, abort }.
 */
export function uploadDataset({ title, file }, { onProgress } = {}) {
    const xhr = new XMLHttpRequest();

    const promise = new Promise((resolve, reject) => {
        const form = new FormData();
        form.append('title', title);
        form.append('file', file);

        xhr.open('POST', '/api/datasets');
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.responseType = 'json';

        xhr.upload.addEventListener('progress', (event) => {
            if (event.lengthComputable && onProgress) {
                onProgress(event.loaded / event.total);
            }
        });

        xhr.addEventListener('load', () => {
            const body = xhr.response;

            if (xhr.status >= 200 && xhr.status < 300) {
                resolve(body);
                return;
            }

            let message = body?.message || `Upload failed (HTTP ${xhr.status}).`;

            if (xhr.status === 413) {
                message = 'File is larger than the server accepts (maximum 512 MB).';
            }

            reject(new ApiError(message, { status: xhr.status, errors: body?.errors ?? null }));
        });

        xhr.addEventListener('error', () => {
            reject(new ApiError('Network error during upload. Check that the backend is running.'));
        });

        xhr.addEventListener('abort', () => {
            reject(new ApiError('Upload cancelled.', { status: -1 }));
        });

        xhr.send(form);
    });

    return { promise, abort: () => xhr.abort() };
}
