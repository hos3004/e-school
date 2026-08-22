<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| مسارات الموديولات تُحمَّل تلقائيًا من modules/<Name>/routes/web.php
| عبر Shared\Module\ModuleRegistry. لا تضف مسارات موديول هنا.
*/

Route::get('/', fn () => redirect()->route('login'))->name('home');
