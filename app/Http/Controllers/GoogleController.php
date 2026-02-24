<?php

namespace App\Http\Controllers;

use App\Models\ZohoToken;
use Http;
use Illuminate\Http\Request;
use App\Models\User;
use Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;
class GoogleController extends Controller
{
    //

    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

     public function callback()
    {
        try {
            // Try normal flow first (will succeed when session cookies work)
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException $e) {
            // Fallback for state mismatch (cookies blocked / SameSite issues)
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (Throwable $e) {
            // Any other unexpected error -> send user back with a message
            return redirect('http://127.0.0.1:5173/login?error=google_auth_failed');
        }
        // Extra guards
        $email = $googleUser->getEmail();
        if (!$email) {
            return redirect('http://127.0.0.1:5173/login?error=email_unavailable');
        }
        
        $current_user = User::where('email', $googleUser->getEmail())->first();
        // Create or update the user
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name'              => $googleUser->getName() ?? '',
                'google_id'         => $googleUser->getId(),
                'avatar'            => $googleUser->getAvatar(),
                'email_verified_at' => now(),
            ]
        );
        if (!$current_user) {
            $token = ZohoToken::first();
           
            $payload = [
                'data' => [
                    [
                        'Account_Name' => $googleUser->getName() ?: 'Unknown',
                        'Email_1' => $googleUser->getEmail(),
                        'user_Type' => $user->plan_type === 'paid' ? 'Paid' : 'Free',
                        'User_ID' => $user->user_id,
                    ]
                ]
            ];

           

            $zohoResponse = Http::withToken($token->access_token)
                ->post('https://www.zohoapis.in/crm/v2/Accounts', $payload);

          

            // ⛔ Retry if unauthorized
            if ($zohoResponse->status() === 401) {
              

                $newAccessToken = $this->refreshZohoToken();
              

                $zohoResponse = Http::withToken($newAccessToken)
                    ->post('https://www.zohoapis.in/crm/v2/Accounts', $payload);

            }
            $result = $zohoResponse->json();
        
            if (!empty($result['data'][0]['details']['id'])) {
                $user->update([
                    'zoho_id' => $result['data'][0]['details']['id']
                ]);
            }
        }

        Auth::login($user, true); // "remember" for convenience

        return redirect('http://127.0.0.1:5173/');
    }

    private function refreshZohoToken()
    {
        $token = ZohoToken::first();

        $response = Http::asForm()->post('https://accounts.zoho.in/oauth/v2/token', [
            'refresh_token' => $token->refresh_token,
            'client_id' =>'1000.39LPZZ4H6TL5GB6QCKGUMJV0G0TX7T',
            'client_secret' => '356f335904a0ff57741cf14284d6d2fe485f1e12bc',
            'grant_type' => 'refresh_token',
        ]);

        $data = $response->json();

        if (isset($data['access_token'])) {
            $token->update([
                'access_token' => $data['access_token'],
                'expires_at' => now()->addSeconds($data['expires_in']),
            ]);

            return $data['access_token'];
        }

        throw new \Exception('Unable to refresh Zoho token: ' . json_encode($data));
    }


   public function redirectFacebook()
{
    return Socialite::driver('facebook')->redirect();
}

public function callbackFacebook()
    {
        try {
            // Try normal flow first (will succeed when session cookies work)
            $fbUser = Socialite::driver('facebook')->user();
        } catch (InvalidStateException $e) {
            // Fallback for state mismatch (cookies blocked / SameSite issues)
            $fbUser = Socialite::driver('facebook')->stateless()->user();
        } catch (Throwable $e) {
            // Any other unexpected error -> send user back with a message
            return redirect('http://127.0.0.1:5173/login?error=facebook_auth_failed');
        }

        // Extra guards
        $email = $fbUser->getEmail();
        if (!$email) {
            return redirect('http://127.0.0.1:5173/login?error=email_unavailable');
        }
        
        $current_user = User::where('email', $fbUser->getEmail())->first();
        
        // Create or update the user
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name'              => $fbUser->getName() ?: $fbUser->getNickname() ?: 'Unknown',
                'facebook_id'       => $fbUser->getId(),
                'avatar'            => $fbUser->getAvatar(),
                'email_verified_at' => now(),
            ]
        );

        // Only create Zoho record for NEW users
        if (!$current_user) {
            $token = ZohoToken::first();
           
            $payload = [
                'data' => [
                    [
                        'Account_Name' => $fbUser->getName() ?: $fbUser->getNickname() ?: 'Unknown',
                        'Email_1' => $fbUser->getEmail(),
                        'user_Type' => $user->plan_type === 'paid' ? 'Paid' : 'Free',
                        'User_ID' => $user->user_id,
                    ]
                ]
            ];

            $zohoResponse = Http::withToken($token->access_token)
                ->post('https://www.zohoapis.in/crm/v2/Accounts', $payload);

            // ⛔ Retry if unauthorized
            if ($zohoResponse->status() === 401) {
                $newAccessToken = $this->refreshZohoToken();
                
                $zohoResponse = Http::withToken($newAccessToken)
                    ->post('https://www.zohoapis.in/crm/v2/Accounts', $payload);
            }
            
            $result = $zohoResponse->json();
        
            if (!empty($result['data'][0]['details']['id'])) {
                $user->update([
                    'zoho_id' => $result['data'][0]['details']['id']
                ]);
            }
        }

        Auth::login($user, true); // "remember" for convenience

        return redirect('http://127.0.0.1:5173/');
    }



}

