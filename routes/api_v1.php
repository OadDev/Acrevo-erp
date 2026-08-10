<?php

use App\Http\Controllers\Api\V1\AuthApiController;
use App\Http\Controllers\Api\V1\WorkOrderApiController;
use Illuminate\Support\Facades\Route;

Route::get('/user', [AuthApiController::class, 'me']);

Route::apiResource('work-orders', WorkOrderApiController::class)->only(['index', 'show']);
