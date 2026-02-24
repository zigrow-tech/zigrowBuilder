<?php

namespace App\Http\Controllers;

use App\Helpers\ZohoHelper;
use Illuminate\Http\Request;
use App\Models\Lead;
use Carbon\Carbon;
use App\Models\ZohoToken;
use Illuminate\Support\Facades\Http;


class LeadController extends Controller
{

    public function store(Request $request)
    {
        $validated = $request->validate([
            'package_name' => 'required|string|max:255',
            'details' => 'nullable|string',
        ]);

        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        $sevenDaysAgo = Carbon::now()->subDays(7);
        $existingLead = Lead::where('user_id', $user->user_id)
            ->where('package_name', $validated['package_name'])
            ->where('created_at', '>=', $sevenDaysAgo)
            ->first();

        if ($existingLead) {
            return response()->json([
                'message' => 'You have already submitted this offer. Please wait 7 days before trying again.'
            ], 403);
        }

        // Save locally
        $lead = Lead::create([
            'user_id' => $user->user_id,
            'package_name' => $validated['package_name'],
            'details' => $validated['details'] ?? null,
        ]);

        // 🚀 Sync with Zoho (Marketing_Leads module)
        $token = ZohoToken::first();
        $zoho_id = $user->zoho_id ?? null;
        // adding additional fields => name, email and phone

        if ($token && $token->access_token && $zoho_id) {
            $payload = [
                'data' => [
                    [
                        'Name' => $user->user_id,
                        'Package_Name' => $validated['package_name'],
                        'Details' => $validated['details'] ?? '',
                        'Name1' => $user->name,
                        'Email' => $user->email,
                        'Phone' => $user->phone,
                        'Request_Submitted_At' => now('Asia/Kolkata')->format('d M Y, h:i A'), // 03 Sep 2025, 01:00 PM (IST),
                    ]
                ]
            ];
            $response = Http::withToken($token->access_token)
                ->post("https://www.zohoapis.in/crm/v2/Marketing_Leads", $payload);
            if ($response->status() === 401) {
                $newAccessToken = ZohoHelper::refreshZohoToken();
                $response = Http::withToken($newAccessToken)
                    ->post("https://www.zohoapis.in/crm/v2/Marketing_Leads", $payload);
            }
            $result = $response->json();

        }

        return response()->json([
            'message' => 'Lead captured successfully!',
            'lead' => $lead,
        ], 201);
    }

    public function getMarketingLeads()
    {
        $leads = Lead::with(['user:user_id,name,company,phone,email,role'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($leads); // ← ✅ just return raw leads data
    }

    // API to get list of blocked offers
    public function disabledOffers()
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json([], 401);
        }

        $offers = Lead::where('user_id', $user->user_id)
            ->where('created_at', '>=', now()->subDays(7))
            ->pluck('package_name');

        return response()->json($offers);
    }

}
