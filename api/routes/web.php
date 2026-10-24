<?php

use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CataloguePageController;
use App\Http\Middleware\UsePublicLocale;
use Illuminate\Support\Facades\Route;

// ---- Public (no login, Indonesian) ------------------------------------------

Route::middleware(UsePublicLocale::class)->group(function () {
    Route::view('/', 'landing')->name('home');

    // Published datasets only, data from /api/catalogue.
    Route::view('/catalogue', 'catalogue.index')->name('catalogue.index');
    Route::view('/catalogue/map', 'map.index', ['public' => true])->name('catalogue.map');
    Route::get('/catalogue/{dataset}', [CataloguePageController::class, 'show'])
        ->whereUuid('dataset')
        ->name('catalogue.show');
});

// ---- Authentication ---------------------------------------------------------

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// ---- Internal (login required, English) -------------------------------------

Route::middleware(['auth', 'active'])->group(function () {
    // Pages are thin shells; data comes from /api/datasets via resources/js/datasets.
    Route::view('/datasets', 'datasets.index')->name('datasets.index');

    Route::view('/map', 'map.index')->name('map.index');

    Route::get('/datasets/{dataset}', fn (string $dataset) => view('datasets.show', ['datasetId' => $dataset]))
        ->whereUuid('dataset')
        ->name('datasets.show');

    Route::middleware('role:admin')->prefix('/admin')->name('admin.')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');

        Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');
        Route::put('/organizations/{organization}', [OrganizationController::class, 'update'])->name('organizations.update');
    });
});
