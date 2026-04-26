<?php

namespace App\Http\Controllers;

use App\Models\Position;
use App\Models\PositionCategory;
use App\Models\Qualification;
use App\Models\QualificationCategory;
use App\Models\User;
use Illuminate\Http\Request;

class PersonnelController extends Controller
{
    /**
     * Display all positions grouped by category.
     */
    public function positions()
    {
        $categories = PositionCategory::orderBy('order_index')
            ->with('positions')
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'positions' => $category->positions->map(function ($pos) {
                        return [
                            'id' => $pos->id,
                            'name' => $pos->name,
                            'description' => $pos->description,
                            'image' => $pos->image,
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
     * Display all qualifications grouped by category.
     */
    public function qualifications()
    {
        $categories = QualificationCategory::orderBy('order_index')
            ->with('qualifications')
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'qualifications' => $category->qualifications->map(function ($qual) {
                        return [
                            'id' => $qual->id,
                            'name' => $qual->name,
                            'description' => $qual->description,
                            'image' => $qual->image,
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
     * Display full personnel profile for a user (awards, positions, qualifications).
     */
    public function userProfile($userId)
    {
        $user = User::select([
            'id', 'steamid', 'personaname', 'rank', 'position',
            'avatar', 'status', 'join_date', 'created_at'
        ])->find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Awards
        $awards = $user->userAwards()
            ->with(['award:id,name,image,category_id', 'award.category:id,name'])
            ->orderByDesc('date_awarded')
            ->get()
            ->map(function ($ua) {
                return [
                    'name' => $ua->award->name ?? null,
                    'image' => $ua->award->image ?? null,
                    'category' => $ua->award->category->name ?? null,
                    'date_awarded' => $ua->date_awarded,
                    'notes' => $ua->notes,
                ];
            });

        // Positions
        $positions = $user->userPositions()
            ->with(['position:id,name,image,category_id', 'position.category:id,name'])
            ->orderByDesc('date_assigned')
            ->get()
            ->map(function ($up) {
                return [
                    'name' => $up->position->name ?? null,
                    'image' => $up->position->image ?? null,
                    'category' => $up->position->category->name ?? null,
                    'date_assigned' => $up->date_assigned,
                    'notes' => $up->notes,
                ];
            });

        // Qualifications
        $qualifications = $user->userQualifications()
            ->with(['qualification:id,name,image,category_id', 'qualification.category:id,name'])
            ->orderByDesc('date_awarded')
            ->get()
            ->map(function ($uq) {
                return [
                    'name' => $uq->qualification->name ?? null,
                    'image' => $uq->qualification->image ?? null,
                    'category' => $uq->qualification->category->name ?? null,
                    'date_awarded' => $uq->date_awarded,
                    'notes' => $uq->notes,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'awards' => $awards,
                'positions' => $positions,
                'qualifications' => $qualifications,
            ],
        ]);
    }
}
