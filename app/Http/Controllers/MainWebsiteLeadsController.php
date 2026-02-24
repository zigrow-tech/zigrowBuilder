<?php

namespace App\Http\Controllers;

use App\Models\ZohoToken;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;




class MainWebsiteLeadsController extends Controller
{
    //

     private function refreshZohoToken()
    {
        $token = ZohoToken::first();

        $response = Http::asForm()->post('https://accounts.zoho.in/oauth/v2/token', [
            'refresh_token' => $token->refresh_token,
            'client_id' => '1000.39LPZZ4H6TL5GB6QCKGUMJV0G0TX7T',
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

public function contactformleads(Request $request)
    {

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'message' => 'required|string',
            'ss' => 'nullable|image|max:6048',
        ]);
        // 🚀 Sync with Zoho (Contact_Leads module)
        $token = ZohoToken::first();

        if ($token && $token->access_token) {
            $payload = [
                'data' => [
                    [
                        'Name' => $validated['name'],
                        'Subject' => $validated['subject'],
                        'Email' => $validated['email'],
                        'Message' => $validated['message'],
                        'User_ID' => $request->userId ?? '',
                    ]
                ]
            ];

            //logger()->info('Syncing contact lead to Zoho...', ['payload' => $payload]);

            $response = Http::withToken($token->access_token)
                ->post("https://www.zohoapis.in/crm/v2/Contact_Leads", $payload);

            //logger()->info('Zoho response (initial)', ['status' => $response->status(), 'body' => $response->body()]);

            $newAccessToken = '';

            if ($response->status() === 401) {
                //logger()->warning('Zoho token expired. Attempting refresh...');
                $newAccessToken = $this->refreshZohoToken();// replace with $this

                $response = Http::withToken($newAccessToken)
                    ->post("https://www.zohoapis.in/crm/v2/Contact_Leads", $payload);

                //logger()->info('Zoho response (after retry)', ['status' => $response->status(), 'body' => $response->body()]);
            }

            $result = $response->json();

            if (($result['data'][0]['code'] ?? '') === 'SUCCESS') {
                $recordId = $result['data'][0]['details']['id'];

               if($newAccessToken){
                    $token->access_token = $newAccessToken;
                    $token->save();
                }

                if ($request->hasFile('ss')) {
                    $responseAttachment = Http::withToken($token->access_token)
                        ->attach(
                            'file',
                            fopen($request->file('ss')->getRealPath(), 'r'),
                            $request->file('ss')->getClientOriginalName()
                        )
                        ->post("https://www.zohoapis.in/crm/v2/Contact_Leads/{$recordId}/Attachments");

                    /*logger()->info('Zoho attachment upload response', [
                        'status' => $responseAttachment->status(),
                        'body' => $responseAttachment->body()
                    ]);*/
                }
            }

            else{
                return response()->json([
                    'message' => 'Something went wrong',
                ]);
            }
        } else {
            //logger()->warning('Zoho token or user zoho_id missing, skipping Zoho sync');
        }
        return response()->json([
            'message' => 'Enquiry Sent successfully!'
        ], 201);
    }






public function newsletterMails(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $token = ZohoToken::first();

        if ($token && $token->access_token) {
            $payload = [
                'data' => [
                    [
                        'Email' => $validated['email'],
                    ]
                ]
            ];

            // logger()->info('Syncing newsletter email to Zoho...', ['payload' => $payload]);

            $response = Http::withToken($token->access_token)
                ->post("https://www.zohoapis.in/crm/v2/NewsLetter_Mails", $payload);

            // logger()->info('Zoho response (initial)', ['status' => $response->status(), 'body' => $response->body()]);

            if ($response->status() === 401) {
                // logger()->warning('Zoho token expired. Attempting refresh...');
                $newAccessToken = $this->refreshZohoToken();// replace with $this->

                $response = Http::withToken($newAccessToken)
                    ->post("https://www.zohoapis.in/crm/v2/NewsLetter_Mails", $payload);

                // logger()->info('Zoho response (after retry)', ['status' => $response->status(), 'body' => $response->body()]);
            }

            $result = $response->json();
            if (($result['data'][0]['code'] ?? '') !== 'SUCCESS') {
                // logger()->error('Failed to store newsletter email in Zoho', ['response' => $result]);
            } else {
                // logger()->info('Newsletter email synced to Zoho successfully', ['response' => $result]);
            }
        } else {
            // logger()->warning('Zoho token missing, skipping Zoho sync');
        }
        return response()->json([
            'message' => 'Newsletter email sent successfully!'
        ], 201);
    }








public function customForm(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'company' => 'required|string|max:255',
        'email' => 'required|email|max:255',
        'phone' => 'required|string|max:20',
        'time' => 'required|string',
        'services' => 'required|array',
        'services.*' => 'string',
        'notes' => 'required|string',
        'files' => 'nullable|array',
        'files.*' => 'file|max:6048'
    ]);

    // 🚀 Sync with Zoho (custom module module)
    $token = ZohoToken::first();
    $preferredDateTime = date('Y-m-d', time()) . 'T' . date('H:i:s', strtotime($validated['time'])) . '+05:30';


    if ($token && $token->access_token) {
        $payload = [
            'data' => [
                [
                    'Name' => $validated['name'],
                    'Company' => $validated['company'],
                    'Email' => $validated['email'],
                    'Phone' => $validated['phone'],
                    'Preferred_Time' => $preferredDateTime, // full datetime
                    'Preferred_Services' => implode(', ', $validated['services']),
                    'User_Notes' => $validated['notes']
                ]
            ]
        ];

        //logger()->info('Syncing help request to Zoho...', ['payload' => $payload]);

        $response = Http::withToken($token->access_token)
            ->post("https://www.zohoapis.in/crm/v2/Custom_Help", $payload);

        //logger()->info('Zoho response (initial)', ['status' => $response->status(), 'body' => $response->body()]);
        
        $newAccessToken = '';
        if ($response->status() === 401) {
            //logger()->warning('Zoho token expired. Attempting refresh...');
            $newAccessToken = $this->refreshZohoToken();

            $response = Http::withToken($newAccessToken)
                ->post("https://www.zohoapis.in/crm/v2/Custom_Help", $payload);
            //logger()->info('Zoho response (after retry)', ['status' => $response->status(), 'body' => $response->body()]);
        }

        $result = $response->json();
        // \Log::info('Zoho response (after retry)', ['status' => $response->status(), 'body' => $result]);


        if (($result['data'][0]['code'] ?? '') === 'SUCCESS') {
            $recordId = $result['data'][0]['details']['id'];
            
            if($newAccessToken){
                $token->access_token = $newAccessToken;
                $token->save();
            }

            // Handle multiple file uploads
            if ($request->hasFile('files')) {
                foreach($request->file('files') as $file) {
                    $responseAttachment = Http::withToken($token->access_token)
                        ->attach(
                            'file',
                            fopen($file->getRealPath(), 'r'),
                            $file->getClientOriginalName()
                        )
                        ->post("https://www.zohoapis.in/crm/v2/Custom_Help/{$recordId}/Attachments");

                    /*logger()->info('Zoho attachment upload response', [
                        'filename' => $file->getClientOriginalName(),
                        'status' => $responseAttachment->status(),
                        'body' => $responseAttachment->body()
                    ]);*/
                }
            }
        } else {
            return response()->json([
                'message' => 'Something went wrong',
            ], 400);
        }
    } else {
        //logger()->warning('Zoho token or user zoho_id missing, skipping Zoho sync');
        return response()->json([
            'message' => 'Authentication error'
        ], 401);
    }

    return response()->json([
        'message' => 'Help request submitted successfully!'
    ], 201);
}






}



