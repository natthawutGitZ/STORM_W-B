<?php

namespace App\Http\Controllers;

use App\Models\Donation;
use Illuminate\Http\Request;

class DonationController extends Controller
{
    /**
     * Display approved donations (public donor wall).
     */
    public function index()
    {
        $donations = Donation::where('status', 'approved')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(function ($donation) {
                return [
                    'id' => $donation->id,
                    'donor_name' => $donation->donor_name,
                    'amount' => $donation->amount,
                    'message' => $donation->message,
                    'created_at' => $donation->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $donations,
        ]);
    }

    /**
     * Display donation statistics.
     */
    public function stats()
    {
        $totalAmount = Donation::where('status', 'approved')->sum('amount');
        $totalDonors = Donation::where('status', 'approved')->distinct('donor_name')->count('donor_name');
        $recentDonations = Donation::where('status', 'approved')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['donor_name', 'amount', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => [
                'total_amount' => $totalAmount,
                'total_donors' => $totalDonors,
                'recent' => $recentDonations,
            ],
        ]);
    }
}
