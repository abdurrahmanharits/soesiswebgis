<?php

use App\Http\Controllers\Api\DatasetController;
use Illuminate\Support\Facades\Route;

Route::get('/datasets', [DatasetController::class, 'index']);
Route::post('/datasets', [DatasetController::class, 'store']);
Route::get('/datasets/{dataset}', [DatasetController::class, 'show']);