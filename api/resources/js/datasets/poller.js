import { getDataset } from './api.js';
import { isPending } from './format.js';

export const POLL_INTERVAL_MS = 3000;

/**
 * Polls GET /api/datasets/{id} for each tracked dataset while it is
 * queued/processing, and stops tracking it once it reaches a final status.
 * The loop idles (no timer) whenever nothing is tracked.
 */
export function createStatusPoller({ onUpdate, onError, interval = POLL_INTERVAL_MS }) {
    const tracked = new Set();
    let timer = null;
    let running = false;

    async function tick() {
        timer = null;

        if (tracked.size === 0) return;

        running = true;

        const ids = [...tracked];
        const results = await Promise.allSettled(ids.map((id) => getDataset(id)));

        results.forEach((result, index) => {
            const id = ids[index];

            if (result.status === 'rejected') {
                // A deleted dataset will never finish; stop asking for it.
                if (result.reason?.status === 404) tracked.delete(id);
                onError?.(result.reason, id);
                return;
            }

            const dataset = result.value;

            if (!dataset || !isPending(dataset)) tracked.delete(id);
            if (dataset) onUpdate(dataset);
        });

        running = false;
        schedule();
    }

    function schedule() {
        if (timer === null && !running && tracked.size > 0) {
            timer = setTimeout(tick, interval);
        }
    }

    return {
        track(id) {
            tracked.add(id);
            schedule();
        },
        stop() {
            tracked.clear();
            clearTimeout(timer);
            timer = null;
        },
        get size() {
            return tracked.size;
        },
    };
}
