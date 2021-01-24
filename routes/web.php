<?php

use Illuminate\Support\Facades\Route;
use M1guelpf\FastLogin\Http\Controllers\FastLoginController;

Route::middleware('web')->as('fastlogin')->prefix('fastlogin')->group(function () {
    Route::post('login/details', [FastLoginController::class, 'loginDetails'])->name('.login.details');
    Route::post('login', [FastLoginController::class, 'login'])->name('.login');

    Route::middleware('auth')->group(function () {
        Route::post('create/details', [FastLoginController::class, 'createDetails'])->name('.create.details');
        Route::post('create', [FastLoginController::class, 'create'])->name('.create');
    });
});
