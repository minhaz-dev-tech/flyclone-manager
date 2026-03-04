<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\RedisController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StatsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/login', [AuthController::class, 'login']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    
    // ==================== AUTHENTICATION ====================
    Route::get('/validate-token', [AuthController::class, 'validateToken']);
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // ==================== DASHBOARD ====================
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    
    // ==================== SITES ====================
    // Basic CRUD
    Route::get('/sites', [SiteController::class, 'index']);
    Route::post('/sites', [SiteController::class, 'create']);
    Route::get('/sites/{site}', [SiteController::class, 'show']);
    Route::put('/sites/{site}', [SiteController::class, 'update']);
     Route::post('/sites/{site}/delete', [SiteController::class, 'destroy']);
    
    // Site actions
    Route::post('/sites/{site}/start', [SiteController::class, 'start']);
    Route::post('/sites/{site}/stop', [SiteController::class, 'stop']);
    Route::post('/sites/{site}/restart', [SiteController::class, 'restart']);
    
    // Domain management
    Route::put('/sites/{site}/domain', [SiteController::class, 'updateDomain']);
    Route::post('/sites/check-domain', [SiteController::class, 'checkDomain']);
    
    // SSL management
    Route::post('/sites/{site}/ssl/enable', [SiteController::class, 'enableSSL']);
    Route::post('/sites/{site}/ssl/disable', [SiteController::class, 'disableSSL']);
    
    // Site statistics
    Route::get('/sites/{site}/stats', [SiteController::class, 'stats']);
    Route::get('/sites/{site}/stats/historical', [SiteController::class, 'historicalStats']);
    
    // Site logs
    Route::get('/sites/{site}/logs', [SiteController::class, 'logs']);
    Route::get('/sites/{site}/logs/error', [SiteController::class, 'errorLogs']);
    
    // Site backup
    Route::post('/sites/{site}/backup', [SiteController::class, 'createBackup']);
    Route::get('/sites/{site}/backups', [SiteController::class, 'listBackups']);
    Route::post('/sites/{site}/restore/{backup}', [SiteController::class, 'restoreBackup']);
    
    // ==================== DATABASES ====================
    Route::get('/databases', [DatabaseController::class, 'index']);
    Route::get('/databases/{database}', [DatabaseController::class, 'show']);
    Route::post('/databases/{database}/backup', [DatabaseController::class, 'backup']);
    Route::get('/databases/{database}/size', [DatabaseController::class, 'getSize']);
    Route::post('/databases/{database}/optimize', [DatabaseController::class, 'optimize']);
    
    // Database credentials
    Route::get('/databases/{database}/credentials', [DatabaseController::class, 'getCredentials']);
    Route::post('/databases/{database}/rotate-password', [DatabaseController::class, 'rotatePassword']);
    
    // ==================== REDIS ====================
    Route::get('/redis', [RedisController::class, 'index']);
    Route::get('/redis/{redis}', [RedisController::class, 'show']);
    Route::post('/redis/{redis}/flush', [RedisController::class, 'flush']);
    Route::get('/redis/{redis}/info', [RedisController::class, 'info']);
    Route::get('/redis/{redis}/stats', [RedisController::class, 'stats']);
    
    // ==================== STATS ====================
    Route::get('/stats/all', [StatsController::class, 'all']);
    Route::get('/stats/system', [StatsController::class, 'system']);
    Route::get('/stats/containers', [StatsController::class, 'containers']);
    Route::post('/stats/cleanup', [StatsController::class, 'cleanup']);
    
    // ==================== USER MANAGEMENT ====================
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::post('/user/change-password', [AuthController::class, 'changePassword']);
    
    // ==================== SYSTEM ====================
    Route::get('/system/health', [DashboardController::class, 'health']);
    Route::get('/system/info', [DashboardController::class, 'systemInfo']);
    Route::get('/system/requirements', [DashboardController::class, 'requirements']);
});

