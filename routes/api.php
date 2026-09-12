<?php

use App\Http\Controllers\ArticleSearchController;
use App\Http\Controllers\IndexSearchController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:search')->group(function (): void {
    Route::get('/search', [IndexSearchController::class, 'search'])->name('api.search');
    Route::get('/articles', ArticleSearchController::class)->name('api.articles');
});

Route::get('/answer', [IndexSearchController::class, 'answer'])
    ->middleware('throttle:answer')
    ->name('api.answer');
