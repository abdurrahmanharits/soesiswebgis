// Low → high values; reads as a hypsometric (terrain) ramp for DEMs.
// Kept separate from raster.js so UI code can use it without loading the GeoTIFF decoder.
export const RAMP = [
    [43, 122, 61],
    [143, 191, 90],
    [232, 217, 138],
    [185, 138, 82],
    [140, 90, 60],
    [242, 242, 242],
];
