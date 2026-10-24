<?php

namespace App\Http\Controllers\Api;

use App\Models\Dataset;
use App\Support\CatalogueDataset;

/**
 * Public preview endpoints for the catalogue. Same output as the internal
 * preview, but a dataset that is not published (approved) answers 404, so
 * pending/rejected data never leaks through the public area.
 */
class CataloguePreviewController extends DatasetPreviewController
{
    protected function ensurePreviewable(Dataset $dataset, string $format): void
    {
        if (! CatalogueDataset::isPublished($dataset)) {
            $this->fail(404, 'Dataset not found.');
        }

        parent::ensurePreviewable($dataset, $format);
    }

    protected function rasterUrl(Dataset $dataset): string
    {
        return route('api.catalogue.preview.raster', $dataset, absolute: false);
    }
}
