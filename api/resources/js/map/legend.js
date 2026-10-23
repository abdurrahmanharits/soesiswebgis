/** CSS gradient + formatted bounds for a raster ramp legend ({ minimum, maximum, ramp }). */
export function legendParts(legend) {
    return {
        gradient: `linear-gradient(to right, ${legend.ramp.map((rgb) => `rgb(${rgb.join(' ')})`).join(', ')})`,
        min: Number(legend.minimum).toLocaleString('en-US', { maximumFractionDigits: 2 }),
        max: Number(legend.maximum).toLocaleString('en-US', { maximumFractionDigits: 2 }),
    };
}
