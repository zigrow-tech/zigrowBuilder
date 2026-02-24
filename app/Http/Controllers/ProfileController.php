<?php

namespace App\Http\Controllers;

use App\Helpers\ZohoHelper;

use Illuminate\Http\Request;
use App\Models\User; // Import the User model
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\ZohoToken;
use Illuminate\Support\Facades\Http;



class ProfileController extends Controller
{

    public function index()
    {
        // Retrieve the user from the database instead of from cache
        $user = User::find(1);

        if (!$user) {
            $defaultData = [
                'name' => 'Default User',
                'email' => 'default@example.com',
                'phone' => '+00 000 000 000',
                'company' => 'Default Company',
                'bio' => 'Default Bio',
                'country' => 'Default Country',
                'state' => 'Default State',
                'postal_code' => '00000',
                'gst_number' => 'DEFAULTGST',
                'avatar' => 'https://via.placeholder.com/150',
                'facebook' => 'https://www.facebook.com/default',
                'xcom' => 'https://x.com/default',
                'linkedin' => 'https://www.linkedin.com/default',
                'instagram' => 'https://www.instagram.com/default',
                'password' => \Illuminate\Support\Facades\Hash::make('secret')
            ];

            $user = \App\Models\User::create($defaultData);
        }

        return response()->json($user);
    }

    public function update(Request $request)
    {

        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        // Validate incoming request data.

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            //'email'       => 'required|email|max:255'. $user->id,
            'email' => 'required|email|max:255|unique:users,email,' . $user->id . ',id',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'bio' => 'nullable|string',
            'country' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'gst_number' => 'nullable|string|max:50',
            'avatar' => 'nullable|url|max:255',
            'facebook' => 'nullable|url|max:255',
            'xcom' => 'nullable|url|max:255',
            'linkedin' => 'nullable|url|max:255',
            'instagram' => 'nullable|url|max:255',
        ]);


        // 3. Update the user's profile in your local database
        $user->update($validatedData);

        // 4. Update the user in Zoho CRM
        $this->updateZohoUser($user, $validatedData);

        // 5. Return a final success response
        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->fresh() // for the latest user data
        ]);

    }
    private function updateZohoUser($user, $validatedData)
    {
        $zohoId = $user->zoho_id ?? '';
        $token = ZohoToken::first();
        // Exit early if no Zoho ID or token
        if (empty($zohoId) || empty($token) || empty($token->access_token)) {
            return;
        }
        $payload = [
            'data' => [
                [
                    'Account_name' => $validatedData['name'],
                    'Phone' => $validatedData['phone'],
                    'Company' => $validatedData['company'],
                    'State' => $validatedData['state'],
                    'Country' => $validatedData['country'],
                    'Postal_Code' => $validatedData['postal_code'],
                    'GST_Number' => $validatedData['gst_number'],
                    'User_ID' => $user->user_id,
                    'Facebook_Url' => $validatedData['facebook'],
                    'X_Url' => $validatedData['xcom'],
                    'Instagram_Url' => $validatedData['instagram'],
                    'LinkedIn_Url' => $validatedData['linkedin'],
                ]
            ]
        ];
        // Send the Zoho API request
        $response = Http::withToken($token->access_token)
            ->put("https://www.zohoapis.in/crm/v2/Accounts/$zohoId", $payload);

        // Handle token expiry and retry
        if ($response->status() === 401) {
            $newAccessToken = ZohoHelper::refreshZohoToken();
            $response = Http::withToken($newAccessToken)
                ->put("https://www.zohoapis.in/crm/v2/Accounts/$zohoId", $payload);
        }

    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        if ($user->google_id && is_null($user->password)) {
            $request->validate([
                'new_password' => 'required|string|min:6|confirmed',
            ]);
            $user->password = Hash::make($request->new_password);
            $user->save();

            return response()->json(['status' => 'success', 'message' => 'Password created successfully'], 200);
        }

        // 2. For all other users (traditional and social users who have already set a password),
        // require the current password for security.
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'The current password is incorrect.',
            ], 422);
        }

        // 3. Update the password for all users who passed the check.
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['status' => 'success', 'message' => 'Password updated successfully'], 200);
    }
}

