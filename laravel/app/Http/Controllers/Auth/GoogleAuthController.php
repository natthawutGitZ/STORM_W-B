<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     *
     * Migrated from: src/pages/auth/login_google.php
     */
    public function redirect()
    {
        return Socialite::driver('google')
            ->scopes(['email', 'profile', 'openid'])
            ->redirect();
    }

    /**
     * Handle the callback from Google.
     *
     * Migrated from: src/pages/auth/login_google_callback.php
     *
     * Flow:
     * 1. If user with this google_id exists → login directly
     * 2. If no user found → store google data in session, redirect to link_steam page
     */
    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect('/laravel-api/login')
                ->with('error', 'Google login failed: ' . $e->getMessage());
        }

        $googleId = $googleUser->getId();
        $email = $googleUser->getEmail();

        // Check if user with this Google ID exists
        $user = User::where('google_id', $googleId)->first();

        if ($user) {
            // Login the existing user
            Auth::login($user);

            if ($user->role === 'admin') {
                return redirect('/laravel-api/admin/dashboard');
            }

            return redirect('/laravel-api/profile');
        }

        // No user found — store Google data in session for Steam linking
        session([
            'google_auth' => [
                'id' => $googleId,
                'email' => $email,
                'name' => $googleUser->getName(),
                'avatar' => $googleUser->getAvatar(),
            ]
        ]);

        return redirect('/laravel-api/auth/link-steam');
    }
}
