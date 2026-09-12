<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'index')->name('home');
Route::view('/eloquent', 'eloquent')->name('eloquent');
