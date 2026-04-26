<?php

namespace App\Http\Controllers;

use App\Models\Rank;
use App\Models\RankCategory;
use Illuminate\Http\Request;

class RankController extends Controller
{
    /**
     * Display all ranks grouped by category.
     */
    public function index()
    {
        $categories = RankCategory::orderBy('order_index')
            ->with('ranks')
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'ranks' => $category->ranks->map(function ($rank) {
                        return [
                            'id' => $rank->id,
                            'name' => $rank->name,
                            'abbreviation' => $rank->abbreviation,
                            'nato_code' => $rank->nato_code,
                            'image' => $rank->image,
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
     * Display a specific rank.
     */
    public function show($id)
    {
        $rank = Rank::find($id);

        if (!$rank) {
            return response()->json([
                'success' => false,
                'message' => 'Rank not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $rank,
        ]);
    }
}
