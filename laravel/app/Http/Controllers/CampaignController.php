<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    /**
     * Display all campaigns.
     */
    public function index()
    {
        $campaigns = Campaign::with('creator:id,personaname,avatar')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($campaign) {
                return [
                    'id' => $campaign->id,
                    'title' => $campaign->title,
                    'image_path' => $campaign->image_path,
                    'description' => $campaign->description,
                    'status' => $campaign->status,
                    'created_by' => $campaign->creator->personaname ?? null,
                    'created_at' => $campaign->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $campaigns,
        ]);
    }

    /**
     * Display a specific campaign with chapters and docs.
     */
    public function show($id)
    {
        $campaign = Campaign::with([
            'chapters',
            'docs',
            'creator:id,personaname,avatar',
        ])->find($id);

        if (!$campaign) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'campaign' => [
                    'id' => $campaign->id,
                    'title' => $campaign->title,
                    'image_path' => $campaign->image_path,
                    'description' => $campaign->description,
                    'status' => $campaign->status,
                    'created_by' => $campaign->creator->personaname ?? null,
                    'created_at' => $campaign->created_at,
                ],
                'chapters' => $campaign->chapters,
                'docs' => $campaign->docs,
            ],
        ]);
    }
}
