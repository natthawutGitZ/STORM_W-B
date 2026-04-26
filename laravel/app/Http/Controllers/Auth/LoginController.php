<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Show login status / info.
     */
    public function status()
    {
        if (Auth::check()) {
            $user = Auth::user();
            return response()->json([
                'success' => true,
                'authenticated' => true,
                'user' => [
                    'id' => $user->id,
                    'personaname' => $user->personaname,
                    'avatar' => $user->avatar,
                    'role' => $user->role,
                    'rank' => $user->rank,
                    'status' => $user->status,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'authenticated' => false,
        ]);
    }

    /**
     * Logout the user.
     *
     * Migrated from: src/pages/auth/logout.php
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }
}
