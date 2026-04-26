<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\SteamLinkController;
use App\Http\Controllers\Auth\LoginController;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home');
});

// ─── Authentication Routes ───────────────────────────────────────────
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

Route::get('/auth/link-steam', [SteamLinkController::class, 'show'])->name('auth.link-steam');
Route::post('/auth/link-steam', [SteamLinkController::class, 'process'])->name('auth.link-steam.process');

Route::get('/auth/status', [LoginController::class, 'status'])->name('auth.status');
Route::post('/auth/logout', [LoginController::class, 'logout'])->name('auth.logout');

// ─── Login Page (simple redirect to Google) ──────────────────────────
Route::get('/login', function () {
    return redirect()->route('auth.google');
})->name('login');
