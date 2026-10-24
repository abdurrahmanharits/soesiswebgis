import { showToast } from '../ui/toast.js';

const $ = (id) => document.getElementById(id);

/**
 * /catalogue/{id}: metadata is server-rendered; this adds the preview map
 * (through the published-only /api/catalogue preview endpoints) and the
 * copy-attribution button. A failed preview never hides the metadata.
 */
export async function initCatalogueDetail(root) {
    const dataset = { id: root.dataset.datasetId, format: root.dataset.format };

    const copy = $('catalogue-copy-attribution');

    if (navigator.clipboard) {
        copy.hidden = false;
        copy.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText($('catalogue-attribution').textContent.trim());
                showToast('Atribusi disalin.', 'success');
            } catch {
                showToast('Atribusi tidak dapat disalin.', 'error');
            }
        });
    }

    $('preview-section').hidden = false;
    $('preview-body').hidden = false;
    $('preview-basemaps').hidden = false;

    const { initDatasetPreview } = await import('../map/index.js');
    await initDatasetPreview(dataset, { previewBase: '/api/catalogue' });
}
