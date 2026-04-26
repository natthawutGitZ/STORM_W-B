<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserResumeController;
use App\Http\Controllers\RankController;
use App\Http\Controllers\AwardController;
use App\Http\Controllers\PersonnelController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\DonationController;

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

// ─── Campaigns ───────────────────────────────────────────────────────
Route::get('/campaigns', [CampaignController::class, 'index']);
Route::get('/campaigns/{id}', [CampaignController::class, 'show']);

// ─── Media Gallery ───────────────────────────────────────────────────
Route::get('/media/albums', [MediaController::class, 'albums']);
Route::get('/media/albums/{id}', [MediaController::class, 'albumShow']);
Route::get('/media/categories', [MediaController::class, 'categories']);
Route::get('/media/recent', [MediaController::class, 'recent']);

// ─── Donations ───────────────────────────────────────────────────────
Route::get('/donations', [DonationController::class, 'index']);
Route::get('/donations/stats', [DonationController::class, 'stats']);
