<?php

namespace App\Http\Controllers;

use App\Helpers\ZohoHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\ZohoToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail; // ✅ Add this

class RegisterController extends Controller
{
    public function checkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $exists = User::where('email', $request->email)->exists();
        return response()->json(['available' => !$exists]);
    }

    public function store(Request $request)
    {

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        try {
            $token = ZohoToken::first();
            $payload = [
                'data' => [
                    [
                        'Account_Name' => $request->name ?: 'Unknown',
                        'Email_1' => $request->email,
                        'user_Type' => 'Free',
                        'User_ID' => $user->user_id,
                    ]
                ]
            ];

            $zohoResponse = Http::withToken($token->access_token)
                ->post('https://www.zohoapis.in/crm/v2/Accounts', $payload);

            // ⛔ Retry if unauthorized
            if ($zohoResponse->status() === 401) {
                $newAccessToken = ZohoHelper::refreshZohoToken();
                $zohoResponse = Http::withToken($newAccessToken)
                    ->post('https://www.zohoapis.in/crm/v2/Accounts', $payload);
            }
            $result = $zohoResponse->json();
            if (!empty($result['data'][0]['details']['id'])) {
                $user->update([
                    'zoho_id' => $result['data'][0]['details']['id']
                ]);
            }

         

            Auth::login($user);

            return response()->json([
                'message' => 'User registered and logged in successfully',
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Something went wrong. Check logs.'], 500);
        }
    }


}



