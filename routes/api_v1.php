<?php

use App\Http\Controllers\Api\V1\WorkOrderApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', fn (Request $request) => $request->user());

Route::apiResource('work-orders', WorkOrderApiController::class)->only(['index', 'show']);
