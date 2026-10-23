import { curationAction, getCurationHistory, updateCuration } from './api.js';
import { LICENSES, LIFECYCLE, TOPIC_CATEGORIES, escapeHtml, formatDate, lifecycleBadge } from './format.js';
import { showToast } from '../ui/toast.js';

const $ = (id) => document.getElementById(id);

const REVIEWER_KEY = 'soesis.reviewerName';

const FIELDS = [
    'title', 'description', 'keywords', 'topic_category', 'source', 'source_organization',
    'license', 'license_other', 'usage_constraints', 'lineage', 'temporal_start', 'temporal_end',
    'geographic_description', 'contact_name', 'contact_email',
];

const ACTION_LABELS = {
    metadata_updated: 'Metadata updated',
    approved: 'Approved',
    rejected: 'Rejected',
    reopened: 'Reopened for review',
};

const UNAVAILABLE = {
    queued: 'Dataset must finish processing before it can be curated.',
    processing: 'Dataset must finish processing before it can be curated.',
    failed: 'Curation unavailable because dataset processing failed.',
};

function storage(action, value) {
    try {
        return action === 'get' ? localStorage.getItem(REVIEWER_KEY) : localStorage.setItem(REVIEWER_KEY, value);
    } catch {
        return null;
    }
}

function fillOptions(select, options) {
    Object.entries(options).forEach(([value, label]) => select.add(new Option(label, value)));
}

/**
 * Curation panel on the dataset detail page. detail.js calls sync(dataset) on
 * every load/poll; onChange(dataset) reports updates made from this panel.
 */
export function createCurationPanel({ onChange }) {
    const el = {
        section: $('curation-section'),
        badge: $('curation-badge'),
        message: $('curation-message'),
        body: $('curation-body'),
        form: $('curation-form'),
        locked: $('curation-locked'),
        save: $('curation-save'),
        reviewer: $('cur-reviewer'),
        note: $('cur-note'),
        approve: $('curation-approve'),
        reject: $('curation-reject'),
        reopen: $('curation-reopen'),
        approveHint: $('cur-approve-hint'),
        noteHint: $('cur-note-hint'),
        actionError: $('curation-action-error'),
        licenseOtherWrap: $('cur-license-other-wrap'),
        history: $('curation-history'),
        historyEmpty: $('curation-history-empty'),
        historyError: $('curation-history-error'),
    };

    const field = (name) => el.form.elements.namedItem(name);

    fillOptions(field('topic_category'), TOPIC_CATEGORIES);
    fillOptions(field('license'), LICENSES);
    el.reviewer.value = storage('get') ?? '';

    let dataset = null;
    let filledFrom = null;
    let busy = false;
    let historyLoadedFor = null;

    function toggleLicenseOther() {
        el.licenseOtherWrap.hidden = field('license').value !== 'other';
    }

    function fillForm(d) {
        FIELDS.forEach((name) => {
            const input = field(name);
            const value = d[name];

            input.value = name === 'keywords'
                ? (Array.isArray(value) ? value.join(', ') : '')
                : (value ?? '');
        });

        toggleLicenseOther();
        filledFrom = `${d.id}:${d.updated_at}`;
    }

    function payload() {
        const data = {};

        FIELDS.forEach((name) => {
            const raw = field(name).value.trim();

            data[name] = name === 'keywords'
                ? [...new Set(raw.split(',').map((k) => k.trim()).filter(Boolean))]
                : (raw === '' ? null : raw);
        });

        return data;
    }

    function showErrors(errors = {}, message = '') {
        el.section.querySelectorAll('[data-error-for]').forEach((p) => {
            const key = p.dataset.errorFor;
            const text = errors[key]?.[0] ?? Object.entries(errors).find(([k]) => k.startsWith(`${key}.`))?.[1]?.[0];

            p.textContent = text ?? '';
            p.hidden = !text;
            (key === 'reviewer_name' ? el.reviewer : key === 'note' ? el.note : field(key))
                ?.setAttribute('aria-invalid', text ? 'true' : 'false');
        });

        el.actionError.textContent = message;
        el.actionError.hidden = !message;
    }

    function setBusy(value) {
        busy = value;
        render();
    }

    function render() {
        const d = dataset;
        const curatable = d.status === 'ready_for_curation';

        el.section.hidden = false;
        el.badge.innerHTML = curatable ? lifecycleBadge(d) : '';
        el.message.hidden = curatable;
        el.message.textContent = UNAVAILABLE[d.status] ?? '';
        el.body.hidden = !curatable;

        if (!curatable) return;

        const pending = d.curation_status === 'pending';

        [...el.form.elements].forEach((input) => {
            input.disabled = !pending || busy;
        });

        el.locked.hidden = pending;
        el.approve.hidden = !pending;
        el.reject.hidden = !pending;
        el.reopen.hidden = pending;
        el.approveHint.hidden = !pending;
        el.noteHint.hidden = !pending;
        [el.approve, el.reject, el.reopen, el.reviewer, el.note].forEach((control) => {
            control.disabled = busy;
        });
    }

    async function loadHistory() {
        el.historyError.hidden = true;

        try {
            const events = await getCurationHistory(dataset.id);

            el.history.innerHTML = events.map((e) => {
                const transition = e.from_status !== e.to_status && LIFECYCLE[e.to_status]
                    ? `<span class="text-slate-500"> → ${escapeHtml(LIFECYCLE[e.to_status].label)}</span>`
                    : '';
                const changes = Array.isArray(e.changes) && e.changes.length
                    ? `<p class="mt-0.5 text-xs text-slate-500">Changed: ${e.changes.map(escapeHtml).join(', ')}</p>`
                    : '';
                const note = e.note
                    ? `<p class="mt-1 rounded bg-white px-2 py-1 text-slate-700 ring-1 ring-slate-200">${escapeHtml(e.note)}</p>`
                    : '';

                return `<li class="border-l-2 border-slate-200 pl-3">
                    <p class="font-medium text-slate-900">${escapeHtml(ACTION_LABELS[e.action] ?? e.action)}${transition}</p>
                    <p class="text-xs text-slate-500">${escapeHtml(e.reviewer_name)} · ${formatDate(e.created_at)}</p>
                    ${changes}${note}
                </li>`;
            }).join('');

            el.historyEmpty.hidden = events.length > 0;
            historyLoadedFor = dataset.id;
        } catch {
            el.historyError.hidden = false;
        }
    }

    function reviewerName() {
        const name = el.reviewer.value.trim();

        if (name) storage('set', name);

        return name;
    }

    function applyResult(body) {
        dataset = body.data;
        fillForm(dataset);
        onChange(dataset);
        loadHistory();
    }

    /**
     * 409 means the dataset changed elsewhere (e.g. another tab): reload the
     * current state instead of leaving a stale form.
     */
    async function handleError(error) {
        if (error.status === 422) {
            showErrors(error.errors ?? {}, error.errors ? '' : error.message);
            return;
        }

        showErrors({}, error.message);

        if (error.status === 409) {
            onChange(null);
        }
    }

    async function save({ quiet = false } = {}) {
        const body = await updateCuration(dataset.id, { reviewer_name: reviewerName(), ...payload() });

        applyResult(body);
        if (!quiet) showToast(body.message, 'success');
    }

    async function run(task) {
        if (busy) return;

        showErrors();
        setBusy(true);

        try {
            await task();
        } catch (error) {
            await handleError(error);
        } finally {
            setBusy(false);
        }
    }

    async function act(action) {
        const body = await curationAction(dataset.id, action, {
            reviewer_name: reviewerName(),
            note: el.note.value.trim() || null,
        });

        el.note.value = '';
        applyResult(body);
        showToast(body.message, action === 'reject' ? 'info' : 'success');
    }

    el.form.addEventListener('submit', (event) => {
        event.preventDefault();
        run(() => save());
    });

    // Approve validates what is stored, so persist the form first.
    el.approve.addEventListener('click', () => run(async () => {
        await save({ quiet: true });
        await act('approve');
    }));

    el.reject.addEventListener('click', () => run(() => act('reject')));
    el.reopen.addEventListener('click', () => run(() => act('reopen')));
    field('license').addEventListener('change', toggleLicenseOther);

    return {
        sync(d) {
            dataset = d;

            // Only overwrite the form when the stored record changed, never on an identical poll.
            if (d.status === 'ready_for_curation' && filledFrom !== `${d.id}:${d.updated_at}`) {
                fillForm(d);
            }

            render();

            if (d.status === 'ready_for_curation' && historyLoadedFor !== d.id) {
                loadHistory();
            }
        },
    };
}
