/**
 * Static extent thumbnail: a few OpenStreetMap tiles (Web Mercator) with the
 * dataset's [minLon, minLat, maxLon, maxLat] drawn as a box. No OpenLayers and
 * no data requests, so a page of cards stays light. Tiles load lazily.
 *
 * Positions are percentages of a virtual WIDTH × HEIGHT box, so the thumbnail
 * scales with its (2:1) container without re-measuring.
 */

const TILE = 256;
const WIDTH = 320;
const HEIGHT = 160;
const MAX_ZOOM = 14;
const FILL = 0.6; // share of the box the extent may cover
const MAX_LAT = 85.05112878;

const TILE_URL = (z, x, y) => `https://tile.openstreetmap.org/${z}/${x}/${y}.png`;

const lonToX = (lon, world) => ((lon + 180) / 360) * world;

function latToY(lat, world) {
    const phi = (Math.max(-MAX_LAT, Math.min(MAX_LAT, lat)) * Math.PI) / 180;

    return ((1 - Math.log(Math.tan(phi) + 1 / Math.cos(phi)) / Math.PI) / 2) * world;
}

const isExtent = (extent) => Array.isArray(extent) && extent.length === 4 && extent.every(Number.isFinite)
    && extent[0] <= extent[2] && extent[1] <= extent[3];

/** Highest zoom at which the extent still fits inside FILL of the box. */
function zoomFor([minLon, minLat, maxLon, maxLat]) {
    for (let z = MAX_ZOOM; z > 0; z--) {
        const world = TILE * 2 ** z;
        const width = lonToX(maxLon, world) - lonToX(minLon, world);
        const height = latToY(minLat, world) - latToY(maxLat, world);

        if (width <= WIDTH * FILL && height <= HEIGHT * FILL) return z;
    }

    return 0;
}

const pct = (value, total) => `${((value / total) * 100).toFixed(3)}%`;

/** Fills `container` (position: relative; overflow: hidden) with the thumbnail. Returns false without an extent. */
export function renderThumbnail(container, extent) {
    if (!isExtent(extent)) return false;

    const z = zoomFor(extent);
    const world = TILE * 2 ** z;
    const tiles = 2 ** z;

    const [minLon, minLat, maxLon, maxLat] = extent;
    const x0 = lonToX(minLon, world);
    const x1 = lonToX(maxLon, world);
    const y0 = latToY(maxLat, world);
    const y1 = latToY(minLat, world);

    const left = (x0 + x1) / 2 - WIDTH / 2;
    const top = (y0 + y1) / 2 - HEIGHT / 2;

    const fragment = document.createDocumentFragment();

    for (let ty = Math.floor(top / TILE); ty <= Math.floor((top + HEIGHT - 1) / TILE); ty++) {
        if (ty < 0 || ty >= tiles) continue;

        for (let tx = Math.floor(left / TILE); tx <= Math.floor((left + WIDTH - 1) / TILE); tx++) {
            const img = document.createElement('img');
            img.src = TILE_URL(z, ((tx % tiles) + tiles) % tiles, ty);
            img.alt = '';
            img.loading = 'lazy';
            img.decoding = 'async';
            img.draggable = false;
            img.className = 'absolute max-w-none select-none';
            Object.assign(img.style, {
                left: pct(tx * TILE - left, WIDTH),
                top: pct(ty * TILE - top, HEIGHT),
                width: pct(TILE, WIDTH),
                height: pct(TILE, HEIGHT),
            });
            fragment.append(img);
        }
    }

    // Keep tiny (or point) extents visible.
    const minSize = 8;
    const boxWidth = Math.max(x1 - x0, minSize);
    const boxHeight = Math.max(y1 - y0, minSize);

    const box = document.createElement('div');
    box.className = 'absolute rounded-sm border-2 border-emerald-700 bg-emerald-600/20';
    Object.assign(box.style, {
        left: pct((x0 + x1) / 2 - boxWidth / 2 - left, WIDTH),
        top: pct((y0 + y1) / 2 - boxHeight / 2 - top, HEIGHT),
        width: pct(boxWidth, WIDTH),
        height: pct(boxHeight, HEIGHT),
    });
    fragment.append(box);

    container.replaceChildren(fragment);

    return true;
}
