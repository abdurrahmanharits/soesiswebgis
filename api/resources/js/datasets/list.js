import { listDatasets } from './api.js';
import {
    datasetTitle,
    escapeHtml,
    formatBadge,
    formatDate,
    isPending,
    lifecycleBadge,
    lifecycleKey,
    orDash,
    summaryDetail,
} from './format.js';
import { createStatusPoller } from './poller.js';
import { initUploadDialog } from './upload.js';
import { showToast } from '../ui/toast.js';

const $ = (id) => document.getElementById(id);

export function initDatasetList() {
    const state = {
        datasets: [],
        search: '',
        format: '',
        status: '',
    };

    const el = {
        loading: $('dataset-loading'),
        error: $('dataset-error'),
        empty: $('dataset-empty'),
        content: $('dataset-content'),
        tbody: $('dataset-tbody'),
        noResults: $('dataset-no-results'),
        count: $('dataset-count'),
        search: $('dataset-search'),
        format: $('filter-format'),
        status: $('filter-status'),
        clear: $('filters-clear'),
        retry: $('dataset-retry'),
        summary: {
            total: $('summary-total'),
            processing: $('summary-processing'),
            pending: $('summary-pending'),
            approved: $('summary-approved'),
            failed: $('summary-failed'),
        },
    };

    const poller = createStatusPoller({
        onUpdate(dataset) {
            const previous = state.datasets.find((d) => d.id === dataset.id);

            upsert(dataset);

            if (previous && previous.status !== dataset.status) {
                if (dataset.status === 'ready_for_curation') {
                    showToast(`“${datasetTitle(dataset)}” is ready.`, 'success');
                } else if (dataset.status === 'failed') {
                    showToast(`“${datasetTitle(dataset)}” failed to process.`, 'error');
                }
            }
        },
    });

    initUploadDialog({
        onUploaded(dataset, message) {
            upsert(dataset);
            poller.track(dataset.id);
            showToast(message || 'Dataset uploaded and queued for processing.', 'success');
        },
    });

    function upsert(dataset) {
        const index = state.datasets.findIndex((d) => d.id === dataset.id);

        if (index === -1) {
            state.datasets.unshift(dataset);
        } else {
            state.datasets[index] = dataset;
        }

        render();
    }

    function filtered() {
        const term = state.search.trim().toLowerCase();

        return state.datasets.filter((d) => {
            if (state.format && d.format !== state.format) return false;
            if (state.status && lifecycleKey(d) !== state.status) return false;
            if (!term) return true;

            return [d.title, d.original_filename].some((value) => value?.toLowerCase().includes(term));
        });
    }

    function renderSummary() {
        const count = (predicate) => state.datasets.filter(predicate).length;

        el.summary.total.textContent = state.datasets.length;
        el.summary.processing.textContent = count(isPending);
        el.summary.pending.textContent = count((d) => lifecycleKey(d) === 'pending');
        el.summary.approved.textContent = count((d) => lifecycleKey(d) === 'approved');
        el.summary.failed.textContent = count((d) => d.status === 'failed');
    }

    function renderRow(d) {
        const url = `/datasets/${encodeURIComponent(d.id)}`;
        const failure = d.status === 'failed' && d.error_message
            ? `<p class="mt-1 max-w-56 truncate text-xs text-rose-700" title="${escapeHtml(d.error_message)}">${escapeHtml(d.error_message)}</p>`
            : '';

        return `<tr class="align-top hover:bg-slate-50/70">
            <td class="px-4 py-3">
                <a href="${url}" class="font-medium text-slate-900 hover:text-emerald-700 hover:underline">${escapeHtml(datasetTitle(d))}</a>
                <p class="mt-0.5 max-w-72 truncate font-mono text-xs text-slate-500" title="${escapeHtml(d.original_filename)}">${escapeHtml(d.original_filename)}</p>
            </td>
            <td class="px-4 py-3">${formatBadge(d.format)}</td>
            <td class="px-4 py-3 font-mono text-xs text-slate-700">${orDash(d.crs)}</td>
            <td class="px-4 py-3 text-xs leading-5 text-slate-600">${summaryDetail(d)}</td>
            <td class="px-4 py-3 whitespace-nowrap">${lifecycleBadge(d)}${failure}</td>
            <td class="px-4 py-3 text-xs whitespace-nowrap text-slate-600">${formatDate(d.created_at)}</td>
            <td class="px-4 py-3 text-right">
                <a href="${url}" class="inline-flex items-center rounded-md px-2.5 py-1 text-sm font-medium text-emerald-700 ring-1 ring-slate-200 ring-inset hover:bg-emerald-50">View</a>
            </td>
        </tr>`;
    }

    function render() {
        renderSummary();

        const hasData = state.datasets.length > 0;
        el.empty.hidden = hasData;
        el.content.hidden = !hasData;

        if (!hasData) return;

        const rows = filtered();
        const filtersActive = Boolean(state.search || state.format || state.status);

        el.tbody.innerHTML = rows.map(renderRow).join('');
        el.noResults.hidden = rows.length > 0;
        el.clear.hidden = !filtersActive;
        el.count.textContent = filtersActive
            ? `Showing ${rows.length} of ${state.datasets.length}`
            : `${state.datasets.length} ${state.datasets.length === 1 ? 'dataset' : 'datasets'}`;
    }

    async function load() {
        el.loading.hidden = false;
        el.error.hidden = true;
        el.empty.hidden = true;
        el.content.hidden = true;

        try {
            state.datasets = await listDatasets();
            state.datasets.filter(isPending).forEach((d) => poller.track(d.id));
            el.loading.hidden = true;
            render();
        } catch (error) {
            el.loading.hidden = true;
            el.error.hidden = false;
            el.error.querySelector('[data-message]').textContent = error.message;
        }
    }

    el.search.addEventListener('input', () => {
        state.search = el.search.value;
        render();
    });

    el.format.addEventListener('change', () => {
        state.format = el.format.value;
        render();
    });

    el.status.addEventListener('change', () => {
        state.status = el.status.value;
        render();
    });

    el.clear.addEventListener('click', () => {
        state.search = state.format = state.status = '';
        el.search.value = el.format.value = el.status.value = '';
        render();
    });

    el.retry.addEventListener('click', load);

    load();
}
