<?php

namespace App\Http\Controllers;

use App\Support\CatalogueDataset;
use App\Support\CatalogueLabels;
use Illuminate\Http\Response;

/**
 * Public dataset page, rendered on the server from the same whitelist as
 * GET /api/catalogue/{id}. Unpublished datasets get a real 404 page.
 */
class CataloguePageController extends Controller
{
    public function show(string $dataset): Response
    {
        $found = CatalogueDataset::find($dataset);

        if (! $found) {
            return response()->view('catalogue.missing', [], 404);
        }

        $item = CatalogueDataset::detail($found);

        return response()->view('catalogue.show', [
            'dataset' => $item,
            'topic' => CatalogueLabels::topic($item['topic_category']),
            'license' => CatalogueLabels::license($item['license'], $item['license_other']),
            'attribution' => CatalogueLabels::attribution($item),
        ]);
    }
}
