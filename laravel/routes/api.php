<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserResumeController;
use App\Http\Controllers\RankController;
use App\Http\Controllers\AwardController;
use App\Http\Controllers\PersonnelController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// ─── Users ───────────────────────────────────────────────────────────
Route::get('/users', [UserController::class, 'index']);
Route::get('/users/{id}', [UserController::class, 'show']);
Route::get('/users/{id}/resume', [UserResumeController::class, 'show']);
Route::get('/users/{id}/awards', [AwardController::class, 'userAwards']);
Route::get('/users/{id}/profile', [PersonnelController::class, 'userProfile']);

// ─── Ranks ───────────────────────────────────────────────────────────
Route::get('/ranks', [RankController::class, 'index']);
Route::get('/ranks/{id}', [RankController::class, 'show']);

// ─── Awards ──────────────────────────────────────────────────────────
Route::get('/awards', [AwardController::class, 'index']);
Route::get('/awards/{id}', [AwardController::class, 'show']);

// ─── Positions & Qualifications ──────────────────────────────────────
Route::get('/positions', [PersonnelController::class, 'positions']);
Route::get('/qualifications', [PersonnelController::class, 'qualifications']);
