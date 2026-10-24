/**
 * UI text for JavaScript-rendered strings. Pages are English unless the
 * layout sets <html lang="id"> (the public catalogue); keys are the English
 * text with {placeholders}, so untranslated strings fall back to English.
 */

export const LANG = document.documentElement.lang?.startsWith('id') ? 'id' : 'en';

export const NUMBER_LOCALE = LANG === 'id' ? 'id-ID' : 'en-US';

const ID = {
    // api.js
    'Cannot reach the server. Check that the backend is running.': 'Server tidak dapat dihubungi. Coba lagi nanti.',
    'Request failed (HTTP {status}).': 'Permintaan gagal (HTTP {status}).',
    'Dataset not found.': 'Dataset tidak ditemukan.',

    // Preview messages from the server
    'Spatial table for this dataset was not found.': 'Data spasial dataset ini tidak ditemukan.',
    'Processed raster file was not found.': 'File raster dataset ini tidak ditemukan.',
    'Dataset CRS is unknown and cannot be previewed safely.': 'Sistem koordinat dataset tidak diketahui.',

    // Layer loaders
    'No features available for preview.': 'Tidak ada fitur untuk ditampilkan.',
    'Preview shows the first {limit} of {total} features.': 'Pratinjau menampilkan {limit} dari {total} fitur pertama.',
    'Raster loaded, but dataset extent is unavailable.': 'Raster dimuat, tetapi cakupan dataset tidak tersedia.',
    'Value statistics unavailable. Showing grayscale.': 'Statistik nilai tidak tersedia. Ditampilkan dalam skala abu-abu.',
    'Raster could not be loaded.': 'Raster tidak dapat dimuat.',
    'Layer could not be loaded.': 'Layer tidak dapat dimuat.',
    'Preview could not be loaded.': 'Pratinjau tidak tersedia.',

    // Popup
    'No attributes available.': 'Tidak ada atribut.',
    Feature: 'Fitur',
    'Feature {id}': 'Fitur {id}',
    '{count} features': '{count} fitur',
    '1 feature': '1 fitur',
    '{count} bands': '{count} band',
    '1 band': '1 band',

    // Map page
    'Raster layer limit reached. Disable or remove another raster layer first.': 'Batas layer raster tercapai. Hapus layer raster lain terlebih dahulu.',
    'Already on the map.': 'Sudah ada di peta.',
    'Layer limit reached ({max}). Remove a layer first.': 'Batas layer tercapai ({max}). Hapus salah satu layer terlebih dahulu.',
    'Extent of this layer is unknown.': 'Cakupan layer ini tidak diketahui.',
    'No visible layers with a known extent.': 'Tidak ada layer terlihat dengan cakupan yang diketahui.',
    'Loading…': 'Memuat…',
    'Failed to load: {message}': 'Gagal dimuat: {message}',
    Retry: 'Coba lagi',
    'Value (band 1)': 'Nilai (band 1)',
    'Show {title}': 'Tampilkan {title}',
    Opacity: 'Opasitas',
    'Move up': 'Naikkan',
    'Move down': 'Turunkan',
    'Zoom to layer': 'Perbesar ke layer',
    'Remove layer': 'Hapus layer',
    Added: 'Ditambahkan',
    Add: 'Tambah',
    '{count} of {max} active': '{count} dari {max} aktif',
    '1 layer is not available on the map.': '1 layer tidak tersedia di peta.',
    '{count} layers are not available on the map.': '{count} layer tidak tersedia di peta.',
};

export function t(text, values = {}) {
    const template = (LANG === 'id' && ID[text]) || text;

    return template.replace(/\{(\w+)\}/g, (match, key) => (key in values ? String(values[key]) : match));
}

export const formatCount = (value, options = {}) => Number(value).toLocaleString(NUMBER_LOCALE, options);
