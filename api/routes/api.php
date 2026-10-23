<?php

use App\Http\Controllers\Api\DatasetController;
use App\Http\Controllers\Api\DatasetCurationController;
use App\Http\Controllers\Api\DatasetPreviewController;
use App\Http\Controllers\Api\MapLayerController;
use Illuminate\Support\Facades\Route;

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
        Route::post('/approve', 'approve')->name('approve')->missing($notFound);
        Route::post('/reject', 'reject')->name('reject')->missing($notFound);
        Route::post('/reopen', 'reopen')->name('reopen')->missing($notFound);
        Route::get('/history', 'history')->name('history')->missing($notFound);
    });
