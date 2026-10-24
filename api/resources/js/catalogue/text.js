/** Indonesian date helpers for the public catalogue. */

const dateFormatter = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' });

/** "2026-10-24" → "24 Okt 2026" (calendar date, no time-zone shift). */
export function formatDay(value) {
    if (!value) return '';

    const date = new Date(`${value}T00:00:00Z`);

    return Number.isNaN(date.getTime()) ? String(value) : dateFormatter.format(date);
}

/** Temporal extent as one string: a single date, a range, or '' when undated. */
export function formatPeriod(start, end) {
    if (!start && !end) return '';
    if (!start || !end || start === end) return formatDay(start || end);

    return `${formatDay(start)} – ${formatDay(end)}`;
}
