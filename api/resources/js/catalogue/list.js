import { listCatalogue } from '../datasets/api.js';
import { escapeHtml, formatBadge } from '../datasets/format.js';
import { DEFAULT_STATE, applyState, facet, hasFilters, readState, writeState } from './filter.js';
import { formatPeriod } from './text.js';
import { renderThumbnail } from './thumbnail.js';

const $ = (id) => document.getElementById(id);

const MAX_KEYWORDS = 4;

const detailUrl = (item) => `/catalogue/${encodeURIComponent(item.id)}`;
const mapUrl = (item) => `/catalogue/map?layers=${encodeURIComponent(item.id)}`;

/**
 * /catalogue: every published dataset is fetched once; search, filters,
 * sorting and pagination run here, with state in the URL
 * (?q=&format=&topic=&license=&keyword=&from=&to=&sort=&page=).
 */
export function initCatalogueList(root) {
    const labels = JSON.parse($('catalogue-labels').textContent);

    const ui = {
        form: $('catalogue-filters'),
        summary: $('catalogue-summary'),
        loading: $('catalogue-loading'),
        error: $('catalogue-error'),
        empty: $('catalogue-empty'),
        noResults: $('catalogue-no-results'),
        grid: $('catalogue-grid'),
        pagination: $('catalogue-pagination'),
        clearButtons: [...root.querySelectorAll('[data-clear-filters]')],
    };

    const fields = Object.fromEntries([...ui.form.elements].filter((el) => el.name).map((el) => [el.name, el]));

    let all = [];
    let state = readState(window.location.search);

    const topicLabel = (code) => labels.topics[code] ?? code;
    const licenseLabel = (item) => (item.license === 'other' ? item.license_other || labels.licenses.other : labels.licenses[item.license] ?? item.license);

    // ---- Filters ---------------------------------------------------------

    function fillSelect(select, options, current) {
        const first = select.options[0];
        const values = new Set(options.map((o) => o.value));

        // Keep a value from a shared link visible even if no dataset has it any more.
        if (current && !values.has(current)) options.push({ value: current, label: current, count: 0 });

        select.replaceChildren(first, ...options.map(({ value, label, count }) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = `${label} (${count})`;
            return option;
        }));
    }

    function buildFacets() {
        const byLabel = (a, b) => a.label.localeCompare(b.label, 'id', { sensitivity: 'base' });

        fillSelect(fields.topic, facet(all, (i) => i.topic_category).map((f) => ({ ...f, label: topicLabel(f.value) })).sort(byLabel), state.topic);
        fillSelect(fields.license, facet(all, (i) => i.license).map((f) => ({ ...f, label: licenseLabel({ license: f.value }) })).sort(byLabel), state.license);
        fillSelect(fields.keyword, facet(all, (i) => i.keywords).map((f) => ({ ...f, label: f.value })).sort(byLabel), state.keyword);
    }

    function syncForm() {
        Object.entries(fields).forEach(([name, field]) => {
            field.value = name === 'page' ? '' : String(state[name] ?? '');
        });
    }

    function readForm() {
        const next = { ...state, page: 1 };
        Object.entries(fields).forEach(([name, field]) => {
            next[name] = field.value.trim();
        });
        return next;
    }

    function setState(next, { push = false } = {}) {
        state = next;
        const url = `${window.location.pathname}${writeState(state)}`;
        push ? history.pushState(null, '', url) : history.replaceState(null, '', url);
        render();
    }

    // ---- Rendering -------------------------------------------------------

    function card(item) {
        const keywords = item.keywords ?? [];
        const shown = keywords.slice(0, MAX_KEYWORDS);
        const period = formatPeriod(item.temporal_start, item.temporal_end);
        const source = item.source_organization || item.source;

        const meta = [
            source ? ['Sumber', source] : null,
            ['Lisensi', licenseLabel(item)],
            period ? ['Periode', period] : null,
        ].filter(Boolean);

        return `<li>
            <article class="flex h-full flex-col overflow-hidden rounded-lg border border-slate-200 bg-white">
                <a href="${detailUrl(item)}" tabindex="-1" aria-hidden="true" class="block">
                    <div data-thumbnail="${escapeHtml(item.id)}" class="relative aspect-[2/1] overflow-hidden bg-slate-100">
                        <p class="grid h-full place-items-center text-xs text-slate-500">Cakupan tidak tersedia</p>
                    </div>
                </a>
                <div class="flex flex-1 flex-col p-4">
                    <div class="flex flex-wrap items-center gap-1.5">
                        ${formatBadge(item.format)}
                        ${item.topic_category ? `<span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs text-slate-700">${escapeHtml(topicLabel(item.topic_category))}</span>` : ''}
                    </div>
                    <h2 class="mt-2 font-semibold leading-snug text-slate-900">
                        <a href="${detailUrl(item)}" class="hover:text-emerald-800 hover:underline">${escapeHtml(item.title)}</a>
                    </h2>
                    ${item.description ? `<p class="mt-1 line-clamp-3 text-sm text-slate-600">${escapeHtml(item.description)}</p>` : ''}
                    <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs">
                        ${meta.map(([label, value]) => `<dt class="text-slate-500">${label}</dt><dd class="truncate text-slate-800" title="${escapeHtml(value)}">${escapeHtml(value)}</dd>`).join('')}
                    </dl>
                    ${shown.length ? `<ul class="mt-3 flex flex-wrap gap-1" aria-label="Kata kunci">
                        ${shown.map((k) => `<li class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-800">${escapeHtml(k)}</li>`).join('')}
                        ${keywords.length > shown.length ? `<li class="px-1 text-xs text-slate-500">+${keywords.length - shown.length}</li>` : ''}
                    </ul>` : ''}
                    <div class="mt-auto flex items-center gap-2 pt-4">
                        <a href="${detailUrl(item)}" class="rounded-md bg-emerald-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-800">Lihat detail</a>
                        <a href="${mapUrl(item)}" class="rounded-md px-3 py-1.5 text-sm font-medium text-slate-700 ring-1 ring-slate-300 ring-inset hover:bg-slate-50">Buka di peta</a>
                    </div>
                </div>
            </article>
        </li>`;
    }

    function pageButton(page, label, { current = false, disabled = false, aria = '' } = {}) {
        return `<button type="button" data-page="${page}" ${disabled ? 'disabled' : ''} ${current ? 'aria-current="page"' : ''} ${aria ? `aria-label="${aria}"` : ''}
            class="min-w-9 rounded-md px-3 py-1.5 text-sm font-medium ${current ? 'bg-slate-900 text-white' : 'text-slate-700 ring-1 ring-slate-300 ring-inset hover:bg-slate-50'} disabled:opacity-40 disabled:hover:bg-transparent">${label}</button>`;
    }

    function renderPagination({ page, pages }) {
        ui.pagination.hidden = pages <= 1;
        if (pages <= 1) {
            ui.pagination.replaceChildren();
            return;
        }

        const numbers = [];
        for (let p = 1; p <= pages; p++) {
            if (p === 1 || p === pages || Math.abs(p - page) <= 1) {
                numbers.push(pageButton(p, p, { current: p === page, aria: `Halaman ${p}` }));
            } else if (numbers.at(-1) !== '…') {
                numbers.push('…');
            }
        }

        ui.pagination.innerHTML = [
            pageButton(page - 1, '‹ Sebelumnya', { disabled: page === 1 }),
            ...numbers.map((n) => (n === '…' ? '<span class="px-1 text-slate-400">…</span>' : n)),
            pageButton(page + 1, 'Berikutnya ›', { disabled: page === pages }),
        ].join('');
    }

    function render() {
        syncForm();

        const filtered = hasFilters(state);
        const result = applyState(all, state);

        ui.clearButtons.forEach((button) => {
            button.disabled = !filtered;
        });

        ui.empty.hidden = all.length > 0;
        ui.noResults.hidden = all.length === 0 || result.total > 0;

        ui.summary.textContent = result.total
            ? `Menampilkan ${result.start}–${result.end} dari ${result.total} dataset${filtered ? ` (difilter dari ${all.length})` : ''}.`
            : '';

        ui.grid.innerHTML = result.items.map(card).join('');
        ui.grid.querySelectorAll('[data-thumbnail]').forEach((element) => {
            const item = result.items.find((i) => i.id === element.dataset.thumbnail);
            renderThumbnail(element, item?.extent);
        });

        renderPagination(result);
    }

    // ---- Events ----------------------------------------------------------

    let searchTimer;

    ui.form.addEventListener('input', (event) => {
        if (event.target.name !== 'q') return;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => setState(readForm()), 250);
    });

    ui.form.addEventListener('change', (event) => {
        if (event.target.name === 'q') return;
        setState(readForm());
    });

    ui.form.addEventListener('submit', (event) => {
        event.preventDefault();
        clearTimeout(searchTimer);
        setState(readForm());
    });

    ui.clearButtons.forEach((button) => button.addEventListener('click', () => {
        setState({ ...DEFAULT_STATE, sort: state.sort });
    }));

    ui.pagination.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-page]');
        if (!button || button.disabled) return;

        setState({ ...state, page: Number(button.dataset.page) }, { push: true });
        ui.summary.scrollIntoView({ block: 'start', behavior: 'smooth' });
    });

    window.addEventListener('popstate', () => {
        state = readState(window.location.search);
        render();
    });

    // ---- Startup ---------------------------------------------------------

    async function load() {
        ui.loading.hidden = false;
        ui.error.hidden = true;

        try {
            all = await listCatalogue();
        } catch (error) {
            ui.error.hidden = false;
            ui.error.querySelector('[data-message]').textContent = error.message;
            return;
        } finally {
            ui.loading.hidden = true;
        }

        buildFacets();
        render();
    }

    ui.error.querySelector('[data-retry]').addEventListener('click', load);

    syncForm();
    load();
}
