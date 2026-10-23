<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/datasets');

// Pages are thin shells; data comes from /api/datasets via resources/js/datasets.
Route::view('/datasets', 'datasets.index')->name('datasets.index');

Route::get('/datasets/{dataset}', fn (string $dataset) => view('datasets.show', ['datasetId' => $dataset]))
    ->whereUuid('dataset')
    ->name('datasets.show');
