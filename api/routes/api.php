<?php

use App\Http\Controllers\Api\DatasetController;
use App\Http\Controllers\Api\DatasetPreviewController;
use Illuminate\Support\Facades\Route;

Route::get('/datasets', [DatasetController::class, 'index']);
Route::post('/datasets', [DatasetController::class, 'store']);
Route::get('/datasets/{dataset}', [DatasetController::class, 'show']);

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
