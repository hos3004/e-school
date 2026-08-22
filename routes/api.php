<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| مسارات API لكل موديول تُحمَّل من modules/<Name>/routes/api.php
| عقود الـ API موثّقة في docs/10-api-contracts.md
*/

Route::middleware('auth:sanctum')->get('/me', fn () => request()->user());
