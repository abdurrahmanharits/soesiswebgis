/**
 * Client-side search, filters, sorting and pagination for the public
 * catalogue. Pure functions (no DOM) so they can be checked in isolation.
 *
 * state: { q, format, topic, license, keyword, from, to, sort, page }
 * (from/to are YYYY-MM-DD; empty string = not set).
 */

export const PER_PAGE = 12;

export const SORTS = ['newest', 'oldest', 'title_asc', 'title_desc'];

export const FILTER_KEYS = ['q', 'format', 'topic', 'license', 'keyword', 'from', 'to'];

export const DEFAULT_STATE = { q: '', format: '', topic: '', license: '', keyword: '', from: '', to: '', sort: 'newest', page: 1 };

const DATE = /^\d{4}-\d{2}-\d{2}$/;

export function readState(search) {
    const params = new URLSearchParams(search);
    const state = { ...DEFAULT_STATE };

    FILTER_KEYS.forEach((key) => {
        state[key] = (params.get(key) ?? '').trim();
    });

    if (!DATE.test(state.from)) state.from = '';
    if (!DATE.test(state.to)) state.to = '';

    state.sort = SORTS.includes(params.get('sort')) ? params.get('sort') : DEFAULT_STATE.sort;
    state.page = Math.max(1, Number.parseInt(params.get('page'), 10) || 1);

    return state;
}

/** Query string with only non-default values, e.g. "?q=desa&page=2" (or ""). */
export function writeState(state) {
    const params = new URLSearchParams();

    FILTER_KEYS.forEach((key) => {
        if (state[key]) params.set(key, state[key]);
    });

    if (state.sort !== DEFAULT_STATE.sort) params.set('sort', state.sort);
    if (state.page > 1) params.set('page', String(state.page));

    const query = params.toString();

    return query ? `?${query}` : '';
}

export const hasFilters = (state) => FILTER_KEYS.some((key) => state[key]);

const normalize = (value) => String(value ?? '').toLocaleLowerCase('id').normalize('NFKD').replace(/[̀-ͯ]/g, '');

/** Text searched by `q`: descriptive public fields only. */
function haystack(item) {
    return normalize([
        item.title,
        item.description,
        ...(item.keywords ?? []),
        item.source,
        item.source_organization,
        item.geographic_description,
    ].filter(Boolean).join(' \n '));
}

/**
 * Temporal filter: the dataset's [start, end] must overlap [from, to].
 * A missing end (or start) means a single date. Undated datasets never match
 * while a temporal filter is active.
 */
function overlaps(item, from, to) {
    const start = item.temporal_start || item.temporal_end;
    const end = item.temporal_end || item.temporal_start;

    if (!start) return false;

    return (!from || end >= from) && (!to || start <= to);
}

export function matches(item, state) {
    if (state.format && item.format !== state.format) return false;
    if (state.topic && item.topic_category !== state.topic) return false;
    if (state.license && item.license !== state.license) return false;

    if (state.keyword) {
        const keyword = normalize(state.keyword);
        if (!(item.keywords ?? []).some((k) => normalize(k) === keyword)) return false;
    }

    if ((state.from || state.to) && !overlaps(item, state.from, state.to)) return false;

    if (state.q) {
        const text = haystack(item);
        // Every word must appear somewhere (AND).
        if (!normalize(state.q).split(/\s+/).filter(Boolean).every((word) => text.includes(word))) return false;
    }

    return true;
}

const byTitle = (a, b) => String(a.title ?? '').localeCompare(String(b.title ?? ''), 'id', { sensitivity: 'base' });
const byCreated = (a, b) => String(a.created_at ?? '').localeCompare(String(b.created_at ?? ''));

const COMPARE = {
    newest: (a, b) => byCreated(b, a) || byTitle(a, b),
    oldest: (a, b) => byCreated(a, b) || byTitle(a, b),
    title_asc: (a, b) => byTitle(a, b),
    title_desc: (a, b) => byTitle(b, a),
};

/** Resolves to { items (current page), total, page (clamped), pages, start, end }. */
export function applyState(all, state, perPage = PER_PAGE) {
    const filtered = all.filter((item) => matches(item, state)).sort(COMPARE[state.sort] ?? COMPARE.newest);
    const pages = Math.max(1, Math.ceil(filtered.length / perPage));
    const page = Math.min(state.page, pages);
    const offset = (page - 1) * perPage;

    return {
        items: filtered.slice(offset, offset + perPage),
        total: filtered.length,
        page,
        pages,
        start: filtered.length ? offset + 1 : 0,
        end: Math.min(offset + perPage, filtered.length),
    };
}

/** Distinct values present in the data, for filter options: [{ value, count }]. */
export function facet(all, pick) {
    const counts = new Map();

    all.forEach((item) => {
        [pick(item)].flat().filter(Boolean).forEach((value) => {
            counts.set(value, (counts.get(value) ?? 0) + 1);
        });
    });

    return [...counts].map(([value, count]) => ({ value, count }));
}
