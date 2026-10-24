<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dataset;
use App\Support\CatalogueDataset;
use Illuminate\Http\JsonResponse;

/**
 * Public catalogue: published datasets only. Search, filters, sorting and
 * pagination run in the browser, so the list returns every published dataset
 * in its lightweight public form, newest first.
 */
class CatalogueController extends Controller
{
    public function index(): JsonResponse
    {
        $datasets = CatalogueDataset::query()
            ->orderByDesc('created_at')
            ->orderBy('title')
            ->get();

        return response()->json([
            'data' => $datasets->map(fn (Dataset $dataset) => CatalogueDataset::summary($dataset)),
        ]);
    }

    /** Unpublished and unknown datasets look the same: 404. */
    public function show(string $dataset): JsonResponse
    {
        $found = CatalogueDataset::find($dataset);

        if (! $found) {
            return response()->json(['message' => 'Dataset not found.'], 404);
        }

        return response()->json(['data' => CatalogueDataset::detail($found)]);
    }
}
