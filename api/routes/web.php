<?php

use App\Http\Controllers\CataloguePageController;
use App\Http\Middleware\UsePublicLocale;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/datasets');

// Pages are thin shells; data comes from /api/datasets via resources/js/datasets.
Route::view('/datasets', 'datasets.index')->name('datasets.index');

Route::view('/map', 'map.index')->name('map.index');

Route::get('/datasets/{dataset}', fn (string $dataset) => view('datasets.show', ['datasetId' => $dataset]))
    ->whereUuid('dataset')
    ->name('datasets.show');

// Public catalogue (Indonesian): published datasets only, data from /api/catalogue.
Route::middleware(UsePublicLocale::class)->group(function () {
    Route::view('/catalogue', 'catalogue.index')->name('catalogue.index');
    Route::view('/catalogue/map', 'map.index', ['public' => true])->name('catalogue.map');
    Route::get('/catalogue/{dataset}', [CataloguePageController::class, 'show'])
        ->whereUuid('dataset')
        ->name('catalogue.show');
});
