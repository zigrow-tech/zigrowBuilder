<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\ZohoToken;
use Illuminate\Support\Carbon;
use Str;


class ZohoController extends Controller
{




public function authorizeRedirect()
    {
        $url = "https://accounts.zoho.in/oauth/v2/auth?" . http_build_query([
            'client_id' => '1000.39LPZZ4H6TL5GB6QCKGUMJV0G0TX7T',
            'redirect_uri' => 'http://127.0.0.1:8000/zoho/callback',
            'response_type' => 'code',
            'scope' => 'ZohoCRM.modules.ALL',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => Str::random(10),
        ]);

        return redirect($url);
    }

    public function handleCallback(Request $request)
    {
        $code = $request->input('code');
        // dd($code);
        $response = Http::asForm()->post('https://accounts.zoho.in/oauth/v2/token', [
            'grant_type' => 'authorization_code',
            'client_id' => '1000.39LPZZ4H6TL5GB6QCKGUMJV0G0TX7T',
            'client_secret' => '356f335904a0ff57741cf14284d6d2fe485f1e12bc',
            'redirect_uri' => 'http://127.0.0.1:8000/zoho/callback',
            'code' => $code,
        ]);

        $data = $response->json();

        ZohoToken::updateOrCreate([], [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_at' => Carbon::now()->addSeconds($data['expires_in']),
        ]);

        dd('success');
        //return redirect();
    }


}
