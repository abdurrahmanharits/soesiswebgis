import Overlay from 'ol/Overlay.js';
import { t } from '../i18n.js';

function attributeList(properties) {
    const entries = Object.entries(properties);

    if (entries.length === 0) {
        const empty = document.createElement('p');
        empty.className = 'text-slate-500';
        empty.textContent = t('No attributes available.');
        return empty;
    }

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

    return list;
}

/**
 * Attribute popup anchored to the clicked coordinate. Values are written with
 * textContent only, so attribute data is never interpreted as HTML.
 *
 * open(coordinate, title, sections) where sections = [{ heading?, properties }].
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

    function open(coordinate, heading, sections) {
        title.textContent = heading;
        body.replaceChildren(
            ...sections.map(({ heading: sectionHeading, properties }) => {
                const section = document.createElement('section');
                section.className = 'py-2 first:pt-0 last:pb-0';

                if (sectionHeading) {
                    const h = document.createElement('p');
                    h.className = 'mb-1 text-xs font-semibold text-slate-700';
                    h.textContent = sectionHeading;
                    section.append(h);
                }

                section.append(attributeList(properties));
                return section;
            }),
        );
        body.classList.toggle('divide-y', sections.length > 1);
        body.classList.toggle('divide-slate-100', sections.length > 1);

        element.hidden = false;
        overlay.setPosition(coordinate);
    }

    closeButton.addEventListener('click', close);

    return { open, close };
}
