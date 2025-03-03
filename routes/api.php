<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AttributeController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TimesheetController;

// Public routes
Route::post('/register', [AuthController::class, 'register']);

// Login route - handles both authenticated and unauthenticated requests
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // User routes
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Resource routes
    Route::apiResource('attributes', AttributeController::class);
    Route::apiResource('projects', ProjectController::class);
    Route::apiResource('timesheets', TimesheetController::class);
});
