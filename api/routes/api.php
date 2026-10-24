<?php

use App\Http\Controllers\Api\CatalogueController;
use App\Http\Controllers\Api\CataloguePreviewController;
use App\Http\Controllers\Api\DatasetController;
use App\Http\Controllers\Api\DatasetCurationController;
use App\Http\Controllers\Api\DatasetPreviewController;
use App\Http\Controllers\Api\MapLayerController;
use Illuminate\Support\Facades\Route;

// Internal API: session cookie + CSRF (web group), login required. Guests get 401 JSON,
// missing roles 403 JSON. Called same-origin by resources/js/datasets/api.js.
Route::middleware(['web', 'auth', 'active'])->group(function () {
    Route::get('/datasets', [DatasetController::class, 'index']);
    Route::post('/datasets', [DatasetController::class, 'store']);
    Route::get('/datasets/{dataset}', [DatasetController::class, 'show']);

    Route::get('/map/layers', [MapLayerController::class, 'index'])->name('api.map.layers');

    Route::prefix('/datasets/{dataset}/preview')
        ->whereUuid('dataset')
        ->name('api.datasets.preview.')
        ->controller(DatasetPreviewController::class)
        ->group(function () {
            $notFound = fn () => response()->json(['message' => 'Dataset not found.'], 404);

            Route::get('/vector', 'vector')->name('vector')->missing($notFound);
            Route::get('/raster', 'raster')->name('raster')->missing($notFound);
            Route::get('/raster/info', 'rasterInfo')->name('raster.info')->missing($notFound);
        });

    Route::prefix('/datasets/{dataset}/curation')
        ->whereUuid('dataset')
        ->name('api.datasets.curation.')
        ->controller(DatasetCurationController::class)
        ->group(function () {
            $notFound = fn () => response()->json(['message' => 'Dataset not found.'], 404);

            Route::patch('/', 'update')->name('update')->missing($notFound);
            Route::get('/history', 'history')->name('history')->missing($notFound);

            // Decisions are for curators and admins; uploaders may only edit metadata.
            Route::middleware('role:curator,admin')->group(function () use ($notFound) {
                Route::post('/approve', 'approve')->name('approve')->missing($notFound);
                Route::post('/reject', 'reject')->name('reject')->missing($notFound);
                Route::post('/reopen', 'reopen')->name('reopen')->missing($notFound);
            });
        });
});

// Public catalogue: published (approved) datasets only; anything else is 404.
Route::get('/catalogue', [CatalogueController::class, 'index'])->name('api.catalogue.index');
Route::get('/catalogue/{dataset}', [CatalogueController::class, 'show'])->whereUuid('dataset')->name('api.catalogue.show');

Route::prefix('/catalogue/{dataset}/preview')
    ->whereUuid('dataset')
    ->name('api.catalogue.preview.')
    ->controller(CataloguePreviewController::class)
    ->group(function () {
        $notFound = fn () => response()->json(['message' => 'Dataset not found.'], 404);

        Route::get('/vector', 'vector')->name('vector')->missing($notFound);
        Route::get('/raster', 'raster')->name('raster')->missing($notFound);
        Route::get('/raster/info', 'rasterInfo')->name('raster.info')->missing($notFound);
    });
