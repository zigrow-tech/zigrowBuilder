<?php

namespace App\Http\Controllers;

use App\Helpers\ZohoHelper;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Models\HireUsPayment;
use App\Models\ZohoToken;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Models\PlanPayment;

class PayuController extends Controller
{
    public function initiatePayment(Request $request)
    {
        $MERCHANT_KEY = config('services.payu.key');
        $SALT = config('services.payu.salt');
        $PAYU_BASE_URL = config('services.payu.base_url');
        $SURL = config('services.payu.success_url');
        $FURL = config('services.payu.failure_url');

        $txnid = strtoupper(Str::random(8)); // unique txn id
        $amount = "1000.00"; // You can make this dynamic from request
        $productinfo = "Zigrow Hire Us Plan";

        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }
        $userId = $user->user_id ?? null;

        // You can get user info dynamically if logged in
        $firstname = $user->name;
        $email = $user->email;
        $phone = $user->phone;

        $udf1 = auth()->user()->user_id ?? '';

        $source = $request->input('source', 'hire');

        $hashString = "$MERCHANT_KEY|$txnid|$amount|$productinfo|$firstname|$email|$udf1||||||||||$SALT";
        $hash = strtolower(hash('sha512', $hashString));

        $paymentData = [
            'key' => $MERCHANT_KEY,
            'txnid' => $txnid,
            'amount' => $amount,
            'productinfo' => $productinfo,
            'firstname' => $firstname,
            'email' => $email,
            'phone' => $phone,
            'surl' => $SURL,
            'furl' => $FURL,
            'hash' => $hash,
            'action' => $PAYU_BASE_URL,        // https://secure.payu.in/_payment
            'udf1' => $userId,
        ];

        return response()->json($paymentData);
    }

    private function createZohoHireLead(User $user, string $pdfBytes, string $pdfFileName, string $invoiceId, string $accessToken, Request $request)
    {
        $payload = [
            'data' => [
                [
                    'Name' => $invoiceId,
                    'User_ID' => $request->input('udf1'),
                    'Transaction_ID' => $request->input('txnid'),
                    'Mihpayid' => $request->input('mihpayid'),
                    'Status' => $request->input('status'),
                    'Amount' => $request->input('amount'),
                    'Product_Info' => $request->input('productinfo'),
                    'Plan_Name' => $request->input('productinfo'),
                    'First_Name' => $request->input('firstname'),
                    'Email' => $request->input('email'),
                    'Phone' => $request->input('phone'),
                    'Mode' => $request->input('mode'),
                    'Bank_Code' => $request->input('bankcode'),
                    'Bank_Ref_Num' => $request->input('bank_ref_num'),
                    'Request_Submitted_at' => now('Asia/Kolkata')->format('d M Y H:i A'),
                ]
            ]
        ];
        $createRes = Http::withToken($accessToken)
            ->post("https://www.zohoapis.in/crm/v2/HireUs_Leads", $payload);

        if ($createRes->status() === 401) {
            $accessToken = ZohoHelper::refreshZohoToken();
            $createRes = Http::withToken($accessToken)
                ->post("https://www.zohoapis.in/crm/v2/HireUs_Leads", $payload);
        }
        if (!$createRes->successful()) {
            return;
        }

        $hireLeadId = $createRes->json()['data'][0]['details']['id'] ?? null;
        if (!$hireLeadId)
            return;

        // Attach PDF
        $attachUrl = "https://www.zohoapis.in/crm/v2/HireUs_Leads/{$hireLeadId}/Attachments";

        $attachRes = Http::withToken($accessToken)
            ->attach('file', $pdfBytes, $pdfFileName)
            ->post($attachUrl);

        if ($attachRes->status() === 401) {
            $accessToken = ZohoHelper::refreshZohoToken();
            $attachRes = Http::withToken($accessToken)
                ->attach('file', $pdfBytes, $pdfFileName)
                ->post($attachUrl);
        }


    }
    private function generateInvoicePdf(array $data): string
    {
        $html = view('hireus-invoice', ['pdfData' => $data])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }


    public function paymentSuccess(Request $request)
    {

        $latest = HireUsPayment::orderBy('id', 'desc')->first();
        $nextNumber = $latest ? ($latest->id + 1) : 1;
        $invoiceId = 'INV-HIRE-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

        // (Optional) Verify transaction here, store to DB if needed
        $hired = HireUsPayment::create([
            'invoice_id' => $invoiceId,
            'user_id' => $request->input('udf1'),
            'txnid' => $request->input('txnid'),
            'mihpayid' => $request->input('mihpayid'),
            'status' => $request->input('status'),
            'amount' => $request->input('amount'),
            'productinfo' => $request->input('productinfo'),
            'firstname' => $request->input('firstname'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'mode' => $request->input('mode'),
            'bankcode' => $request->input('bankcode'),
            'bank_ref_num' => $request->input('bank_ref_num'),
            'raw_response' => json_encode($request->all()),
        ]);

        // Only sync to Zoho if user is hired (status == 'success')
        if ($hired) {
            $user = User::where('user_id', $hired->user_id)->first();
            $token = ZohoToken::first();
            $amount = (float) $request->amount; // Total incl GST
            $gst_rate = 0.18;
            $base_price = $amount / (1 + $gst_rate); // exclusive of GST
            $gst_amount = $amount - $base_price;     // GST portion

            $pdfData = [
                'invoice_id' => $invoiceId,
                'total_incl_gst' => $request->amount,
                'base_price' => number_format($base_price, 2),
                'gst_amount' => number_format($gst_amount, 2),
                'net_amount_paid' => $amount,
                'plan_name' => $request->input('productinfo'),
                'amount' => $amount ?? 0,

                'payment_mode' => $request->mode, // e.g., CASH, Credit Card
                'payment_date' => Carbon::parse($request->addedon)->format('d M Y'), // Y-m-d
                'full_name' => $user->name,
                'email' => $user->email,
                'user_id' => $user->user_id,

                'payment_time' => Carbon::parse($request->addedon)->format('H:i A'),
                'transaction_id' => $request->input('txnid'),

                'business_name' => $user->company ?? '—',
                'state' => $user->state ?? '',
                'country' => $user->country ?? '',
                'postal_code' => $user->postal_code ?? '',
                'phone' => $user->phone ?? '—',
                'gst_number' => $user->gst_number ?? '',

            ];
            $pdfBytes = $this->generateInvoicePdf($pdfData);
            $pdfFileName = $invoiceId . '.pdf';


            if ($token && $token->access_token) {
                $this->createZohoHireLead($user, $pdfBytes, $pdfFileName, $invoiceId, $token->access_token, $request);
            }
        }

        $source = $request->query('source', 'hire');
        return redirect('http://127.0.0.1:5173/payment-success?source=hire');
    }

    // ✅ This handles POST request from PayU on failed payment
    public function paymentFailure(Request $request)
    {
        return redirect('http://127.0.0.1:5173/payment-failure');
    }

    public function index()
    {
        $payments = PlanPayment::with('user')  // ✅ Include user data
            ->where('user_id', auth()->id())
            ->get();

        return response()->json($payments);
    }
    public function getHireUsPayments()
    {
        return HireUsPayment::with('user')
            ->where('user_id', auth()->user()->user_id)
            ->orderByDesc('created_at')
            ->get();
    }


}