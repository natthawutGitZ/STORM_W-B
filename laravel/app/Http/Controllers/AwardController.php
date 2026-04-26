<?php

namespace App\Http\Controllers;

use App\Models\Award;
use App\Models\AwardCategory;
use App\Models\UserAward;
use App\Models\User;
use Illuminate\Http\Request;

class AwardController extends Controller
{
    /**
     * Display all awards grouped by category.
     */
    public function index()
    {
        $categories = AwardCategory::orderBy('order_index')
            ->with('awards')
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'awards' => $category->awards->map(function ($award) {
                        return [
                            'id' => $award->id,
                            'name' => $award->name,
                            'description' => $award->description,
                            'image' => $award->image,
                        ];
                    }),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Display a specific award with recipients.
     */
    public function show($id)
    {
        $award = Award::with('category')->find($id);

        if (!$award) {
            return response()->json([
                'success' => false,
                'message' => 'Award not found'
            ], 404);
        }

        // Get users who received this award
        $recipients = UserAward::where('award_id', $id)
            ->with(['user:id,personaname,avatar,rank,status', 'awardedByUser:id,personaname'])
            ->orderByDesc('date_awarded')
            ->get()
            ->map(function ($ua) {
                return [
                    'user' => $ua->user ? [
                        'id' => $ua->user->id,
                        'personaname' => $ua->user->personaname,
                        'avatar' => $ua->user->avatar,
                        'rank' => $ua->user->rank,
                        'status' => $ua->user->status,
                    ] : null,
                    'date_awarded' => $ua->date_awarded,
                    'awarded_by' => $ua->awardedByUser->personaname ?? null,
                    'notes' => $ua->notes,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'award' => $award,
                'recipients' => $recipients,
            ],
        ]);
    }

    /**
     * Display awards for a specific user.
     */
    public function userAwards($userId)
    {
        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $awards = UserAward::where('user_id', $userId)
            ->with(['award.category', 'awardedByUser:id,personaname'])
            ->orderByDesc('date_awarded')
            ->get()
            ->map(function ($ua) {
                return [
                    'id' => $ua->id,
                    'award' => [
                        'id' => $ua->award->id,
                        'name' => $ua->award->name,
                        'description' => $ua->award->description,
                        'image' => $ua->award->image,
                        'category' => $ua->award->category->name ?? null,
                    ],
                    'date_awarded' => $ua->date_awarded,
                    'awarded_by' => $ua->awardedByUser->personaname ?? null,
                    'notes' => $ua->notes,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $awards,
        ]);
    }
}
