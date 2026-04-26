<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\FormResponse;
use Illuminate\Http\Request;

class UserResumeController extends Controller
{
    /**
     * Display resume data for a specific user.
     *
     * Migrated from: src/api/user_resume.php
     * Fixed bugs: legacy code referenced non-existent table 'form_submissions'
     * and incorrect column names 'submission_id' / 'answer_value'.
     */
    public function show($id)
    {
        $user = User::select([
            'id', 'steamid', 'personaname', 'rank', 'position',
            'avatar', 'status', 'created_at', 'resume_data'
        ])->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'User not found'
            ], 404);
        }

        // Format user data
        $userData = $user->toArray();
        $userData['created_at_formatted'] = $user->created_at
            ? $user->created_at->format('d M Y')
            : null;

        // Decode resume_data JSON if present
        if ($userData['resume_data']) {
            $userData['resume_data'] = json_decode($userData['resume_data'], true);
        }

        // Get accepted form responses for this user
        // The legacy table stores user_id as varchar, so we search by both
        // the numeric ID and the steamid for compatibility.
        $responses = FormResponse::where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->orWhere('user_id', $user->steamid);
            })
            ->where('status', 'accepted')
            ->with(['form:id,title', 'answers.question' => function ($query) {
                $query->where('question_type', '!=', 'section')
                      ->orderBy('sort_order');
            }])
            ->orderByDesc('submitted_at')
            ->limit(5)
            ->get();

        // Format responses
        $formattedResponses = $responses->map(function ($response) {
            $answers = $response->answers
                ->filter(function ($answer) {
                    // Only include answers whose question is loaded
                    // (section-type questions are excluded by the eager load)
                    return $answer->question !== null;
                })
                ->map(function ($answer) {
                    return [
                        'question' => $answer->question->question_text,
                        'answer' => $answer->answer_text,
                    ];
                })
                ->values();

            return [
                'form_title' => $response->form->title ?? 'Unknown Form',
                'submitted_at' => $response->submitted_at
                    ? date('d M Y', strtotime($response->submitted_at))
                    : null,
                'answers' => $answers,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $userData,
                'responses' => $formattedResponses,
            ]
        ]);
    }
}
