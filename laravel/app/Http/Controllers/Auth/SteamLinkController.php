<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SteamLinkController extends Controller
{
    /**
     * Show the Steam linking page.
     *
     * Migrated from: src/pages/auth/link_steam.php
     */
    public function show()
    {
        if (!session('google_auth')) {
            return redirect('/laravel-api/login')
                ->with('error', 'Please login with Google first.');
        }

        $googleAuth = session('google_auth');

        return response()->json([
            'success' => true,
            'message' => 'Please provide your Steam ID to link your account.',
            'google_email' => $googleAuth['email'],
        ]);
    }

    /**
     * Process Steam ID linking.
     *
     * Migrated from: src/pages/auth/link_steam_process.php
     *
     * Flow:
     * 1. If steamid already exists in DB → link Google ID to that user
     * 2. If steamid does NOT exist → create new user with Google + Steam data
     */
    public function process(Request $request)
    {
        $request->validate([
            'steamid' => 'required|string|min:10|max:64',
        ]);

        $googleAuth = session('google_auth');
        if (!$googleAuth) {
            return response()->json([
                'success' => false,
                'message' => 'Session expired. Please login with Google again.',
            ], 401);
        }

        $steamid = $request->input('steamid');

        // Check if Steam ID already exists
        $existingUser = User::where('steamid', $steamid)->first();

        if ($existingUser) {
            // Link Google ID to existing user
            $existingUser->update([
                'google_id' => $googleAuth['id'],
                'email' => $googleAuth['email'],
            ]);

            Auth::login($existingUser);
            session()->forget('google_auth');

            return response()->json([
                'success' => true,
                'message' => 'Google account linked successfully!',
                'user' => $existingUser->only(['id', 'personaname', 'role']),
            ]);
        }

        // Create new user
        $user = User::create([
            'steamid' => $steamid,
            'google_id' => $googleAuth['id'],
            'email' => $googleAuth['email'],
            'username' => 'User_' . substr($steamid, -6),
            'personaname' => $googleAuth['name'] ?? 'New Recruit',
            'avatar' => 'assets/images/default_avatar.png',
            'role' => 'user',
            'rank' => 'Recruit',
            'status' => 'Active',
            'password' => Hash::make(Str::random(32)),
        ]);

        Auth::login($user);
        session()->forget('google_auth');

        return response()->json([
            'success' => true,
            'message' => 'Account created and linked successfully!',
            'user' => $user->only(['id', 'personaname', 'role']),
        ]);
    }
}
