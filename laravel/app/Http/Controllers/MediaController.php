<?php

namespace App\Http\Controllers;

use App\Models\MediaAlbum;
use App\Models\MediaGallery;
use App\Models\MediaCategory;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    /**
     * Display all albums with optional category filter.
     */
    public function albums(Request $request)
    {
        $query = MediaAlbum::with([
            'category:id,name,slug',
            'coverImage:id,filename',
            'tags:id,name,slug',
        ])->where('status', 'published');

        if ($request->has('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        $albums = $query->orderByDesc('created_at')->paginate(12);

        return response()->json([
            'success' => true,
            'data' => $albums,
        ]);
    }

    /**
     * Display a specific album with its media items.
     */
    public function albumShow($id)
    {
        $album = MediaAlbum::with([
            'category:id,name,slug',
            'media',
            'tags:id,name,slug',
            'creator:id,personaname,avatar',
        ])->find($id);

        if (!$album) {
            return response()->json([
                'success' => false,
                'message' => 'Album not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $album,
        ]);
    }

    /**
     * Display all categories.
     */
    public function categories()
    {
        $categories = MediaCategory::withCount('albums')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Display recent media items.
     */
    public function recent()
    {
        $media = MediaGallery::with([
            'category:id,name,slug',
            'album:id,title',
            'uploader:id,personaname,avatar',
        ])
            ->orderByDesc('uploaded_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $media,
        ]);
    }
}
