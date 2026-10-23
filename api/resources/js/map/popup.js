import Overlay from 'ol/Overlay.js';

/**
 * Attribute popup anchored to the clicked coordinate. Values are written with
 * textContent only, so attribute data is never interpreted as HTML.
 */
export function createPopup(map, element, { onClose } = {}) {
    const title = element.querySelector('[data-popup-title]');
    const body = element.querySelector('[data-popup-body]');
    const closeButton = element.querySelector('[data-popup-close]');

    const overlay = new Overlay({
        element,
        positioning: 'bottom-center',
        offset: [0, -12],
        autoPan: { animation: { duration: 200 } },
    });

    map.addOverlay(overlay);

    function close() {
        overlay.setPosition(undefined);
        element.hidden = true;
        onClose?.();
    }

    function open(coordinate, heading, properties) {
        title.textContent = heading;
        body.replaceChildren();

        const entries = Object.entries(properties);

        if (entries.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'text-slate-500';
            empty.textContent = 'No attributes available.';
            body.append(empty);
        } else {
            const list = document.createElement('dl');
            list.className = 'grid grid-cols-[auto_1fr] gap-x-3 gap-y-1';

            entries.forEach(([key, value]) => {
                const dt = document.createElement('dt');
                dt.className = 'font-mono text-xs leading-5 text-slate-500';
                dt.textContent = key;

                const dd = document.createElement('dd');
                dd.className = 'break-words text-slate-900';
                dd.textContent = value === null || value === undefined || value === '' ? '—' : String(value);

                list.append(dt, dd);
            });

            body.append(list);
        }

        element.hidden = false;
        overlay.setPosition(coordinate);
    }

    closeButton.addEventListener('click', close);

    return { open, close };
}
