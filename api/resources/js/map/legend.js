import { formatCount } from '../i18n.js';

/** CSS gradient + formatted bounds for a raster ramp legend ({ minimum, maximum, ramp }). */
export function legendParts(legend) {
    return {
        gradient: `linear-gradient(to right, ${legend.ramp.map((rgb) => `rgb(${rgb.join(' ')})`).join(', ')})`,
        min: formatCount(legend.minimum, { maximumFractionDigits: 2 }),
        max: formatCount(legend.maximum, { maximumFractionDigits: 2 }),
    };
}
