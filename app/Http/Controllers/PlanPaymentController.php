<?php

namespace App\Http\Controllers;

use App\Helpers\ZohoHelper;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Models\PlanPayment;
use App\Models\User;
use App\Models\ZohoToken;
use App\Models\UserTokenWallet;
use Illuminate\Support\Facades\Http;


class PlanPaymentController extends Controller
{
    public function initiate(Request $request)
    {
        $MERCHANT_KEY = config('services.payu.key');
        $SALT = config('services.payu.salt');
        $PAYU_BASE_URL = config('services.payu.base_url');
        $request->validate([
            'plan_name' => 'required|string',
            'price' => 'required|numeric',
        ]);

        $txnid = strtoupper(Str::random(10));
        $user = Auth::user();

        $firstname = $user->name ?? 'User';
        $email = $user->email;
        $phone = $user->phone;
        $userId = $user->user_id;

        $productinfo = $request->plan_name;
        $amount = $request->price;
        $source = $request->input('source', 'dashboard');
        $duration = $request->input('duration'); // ✅ NEW LINE
        $hashString = "$MERCHANT_KEY|$txnid|$amount|$productinfo|$firstname|$email|$userId|$duration|||||||||$SALT";
        $hash = strtolower(hash('sha512', $hashString));

        return response()->json([
            'key' => $MERCHANT_KEY,
            'txnid' => $txnid,
            'offer_key' => 'SAVE20@w0NJbBEMnSZk',
            'amount' => $amount,
            'productinfo' => $productinfo,
            'firstname' => $firstname,
            'email' => $email,
            'phone' => $phone,
            'surl' => route('plan.payment.success', ['source' => $source]), // 👈 named route
            'furl' => route('plan.payment.failure'),
            'hash' => $hash,
            'action' => $PAYU_BASE_URL,
            'udf1' => $userId,
            'udf2' => $duration, // ✅ pass duration as udf2
        ]);
    }


    public function paymentSuccess(Request $request)
    {
        

        $user = User::where('user_id', $request->udf1)->firstOrFail();
        

        // Plan info
        $planName = $request->productinfo;
        $amount = $request->amount;
        $txnid = $request->txnid;
       

        $startDate = now();
        $endDate = null;

        // Handle plan duration based on the plan type
        $duration = $request->input('udf2');
        if ($duration === 'Lifetime') {
            // For Lifetime plans, keep endDate as null
            $months = null;
        } else {
            // For other plans, calculate months based on duration
            $months = match ($duration) {
                '2 Year' => 24,
                '3 Year' => 36,
                default => 12,
            };
            $endDate = now()->addMonths($months);
        }
        
       

        // Update user locally
        $updateData = [
            'plan_type' => 'paid',
            'plan_name' => $planName,
            'plan_price' => $amount,
            'remaining_credits' => 20,
            'plan_started_at' => $startDate,
        ];

        // Only set plan_expires_at for non-Lifetime plans
        if ($endDate) {
            $updateData['plan_expires_at'] = $endDate;
        } else {
            // For Lifetime plans, set plan_expires_at to null
            $updateData['plan_expires_at'] = null;
        }

        $user->update($updateData);

        UserTokenWallet::updateOrCreate(
    ['user_id' => $user->id],
    [
        // Keep lifetime analytics if you want:
        // 'tokens_used_total_all' => DB::raw('tokens_used_total_all'),  // (don’t overwrite)

        // Reset only billing-cycle counters:
        'tokens_used_for_credit' => 0,
        'credits_deducted_by_tokens' => 0,
    ]
);
       
        // Generate invoice ID
        $invoiceId = $this->generateInvoiceId();
        $transactionOffer = json_decode($request->transaction_offer, true);
        $offerAvailed = $transactionOffer['offer_data'][0]['offer_title'] ?? null;
        $offerDescription = $transactionOffer['offer_data'][0]['offer_description'] ?? null;
        $offerPercentage = $transactionOffer['offer_data'][0]['offer_percentage'] ?? 0;
       
        PlanPayment::create([
            'invoice_id' => $invoiceId,
            'user_id' => $user->user_id,
            'plan_name' => $planName,
            'price' => $amount,
            'txnid' => $txnid,
            'payment_id' => $request->mihpayid,
            'mode' => $request->mode,
            'discount' => $request->discount,
            'net_amount_debit' => $request->net_amount_debit,
            'offer_key' => $transactionOffer['offer_data'][0]['offer_key'] ?? $request->offer,
            'offer_type' => $transactionOffer['offer_data'][0]['offer_type'] ?? $request->offer_type,
            'offer_description' => $transactionOffer['offer_data'][0]['offer_description'] ?? '',
            'bank_ref_num' => $request->bank_ref_num,
            'status' => $request->status,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'raw_response' => json_encode($request->all()),
        ]);
       
        $original_price = (float) $amount;
        $net_paid_amount = (float) $request->net_amount_debit;
        $discount = (float) ($request->discount ?? 0);
        $discount_price = $original_price - $discount;
        $gst_rate = 0.18;
        $base_price = $discount_price / (1 + $gst_rate);
        $gst_amount = $discount_price - $base_price;

        $pdfData = [
            'invoice_id' => $invoiceId,
            'total_incl_gst' => $discount_price,
            'base_price' => number_format($base_price, 2),
            'discount_percentage' => 20,
            'discount_amount' => $discount,
            'gst_amount' => number_format($gst_amount, 2),
            'net_amount_paid' => $discount_price,
            'plan_name' => $planName,
            'amount' => $amount ?? 0,
            'status' => $request->status,
            'payment_mode' => $request->mode,
            'payment_date' => Carbon::parse($request->addedon)->format('d M Y'),
            'full_name' => $user->name,
            'email' => $user->email,
            'user_id' => $user->user_id,
            'plan_duration' => $request->udf2,
            'expiry_date' => $endDate ? Carbon::parse($endDate)->format('d M Y') : 'Lifetime',
            'payment_time' => Carbon::parse($request->addedon)->format('H:i A'),
            'transaction_id' => $txnid,
            'discount' => $discount ?? 0,
            'net_amount_debit' => $request->net_amount_debit,
            'business_name' => $user->company ?? '—',
            'state' => $user->state ?? '',
            'country' => $user->country ?? '',
            'postal_code' => $user->postal_code ?? '',
            'phone' => $user->phone ?? '—',
            'gst_number' => $user->gst_number ?? '',
            'offer' => $offerAvailed,
            'offer_desc' => $offerDescription,
        ];
        
        $pdfBytes = $this->generateInvoicePdf($pdfData);
        $pdfFileName = $invoiceId . '.pdf';

        // Zoho operations
        $accessToken = ZohoToken::first()?->access_token;
        if ($accessToken) {
            $this->updateZohoAccountUserType($user, $endDate, $accessToken, $planName, $request);
            $this->createZohoPlanInvoice(
                $user,
                $pdfBytes,
                $pdfFileName,
                $planName,
                $amount,
                $txnid,
                $accessToken,
                $startDate,
                $endDate,
                $invoiceId,
                $request
            );
        }
        // Redirect user
        $source = $request->query('source', 'dashboard');
      
        if ($source === 'signup') {
            return redirect('http://127.0.0.1:5173/payment-success?source=signup');
        } else {
            return redirect('http://127.0.0.1:5173/payment-success');
        }
    }

    // ---------------------------
// Helper Functions
// ---------------------------

    private function generateInvoiceId(): string
    {
        $latest = PlanPayment::orderBy('id', 'desc')->first();
        $nextNumber = $latest ? ($latest->id + 1) : 1;
        return 'INV-PLAN-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    private function generateInvoicePdf(array $data): string
    {
        $html = view('invoice', ['pdfData' => $data])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function updateZohoAccountUserType(User $user, ?string $endDate, string $accessToken, string $planName, Request $request)
    {
        $recordId = $user->zoho_id;
        if (!$recordId) {
            return;
        }

        $payload = [
            'data' => [
                [
                    'user_Type' => 'Paid',
                    'Plan_Taken' => $planName,
                    'Plan_Amount' => $request->amount,
                    'Plan_Purchased_At' => now()->format('d M Y'),
                    'Plan_Expiry' => $endDate ? Carbon::parse($endDate)->format('d M Y') : 'Lifetime',
                ]
            ]
        ];

        $updateRes = Http::withToken($accessToken)
            ->put("https://www.zohoapis.in/crm/v2/Accounts/{$recordId}", $payload);

        if ($updateRes->status() === 401) {
            $accessToken = ZohoHelper::refreshZohoToken();
            $updateRes = Http::withToken($accessToken)
                ->put("https://www.zohoapis.in/crm/v2/Accounts/{$recordId}", $payload);
        }


    }

    private function createZohoPlanInvoice(User $user, string $pdfBytes, string $pdfFileName, string $planName, string $amount, string $txnid, string $accessToken, $startDate, $endDate, string $invoiceId, Request $request)
    {
        $recordId = $user->zoho_id;
        if (!$recordId) {
            return;
        }
        $transactionOffer = json_decode($request->transaction_offer, true);

        $offerAvailed = $transactionOffer['offer_data'][0]['offer_title'] ?? null;
        $offerPercentage = (string) ($transactionOffer['offer_data'][0]['offer_percentage'] ?? '');
        $offer_type = $transactionOffer['offer_data'][0]['offer_type'] ?? $request->offer_type ?? null;

        // Map request and user data to Zoho PlanInvoice fields
        $payload = [
            'data' => [
                [
                    'Account' => ['id' => $recordId],
                    'Invoice_ID' => $invoiceId,
                    'Transaction_ID' => $request->txnid,
                    'Status' => $request->status,
                    'Mode' => $request->mode,
                    'Amount' => $request->amount,
                    'Discount' => $request->discount ?? null,
                    'Net_Amount_Debit' => $request->net_amount_debit ?? null,
                    'Payment_Date' => $request->addedon ?? now()->format('d M Y'),
                    'Plan_Name' => $request->productinfo,
                    'Full_Name' => $request->firstname,
                    'Email' => $request->email,
                    'User_ID' => $request->udf1,
                    'Plan_Duration' => $request->udf2,
                    'Expiry_Date' => $endDate ? (is_a($endDate, 'Carbon\Carbon') ? $endDate->format('d M Y') : $endDate) : 'Lifetime',
                    'Discount_Coupon' => $offerAvailed,
                    'Discount_Type' => $offer_type,
                    'Discount_Percent' => $offerPercentage,
                ]
            ]
        ];

        $createRes = Http::withToken($accessToken)
            ->post("https://www.zohoapis.in/crm/v2/PlanInvoices", $payload);

        if ($createRes->status() === 401) {
            $accessToken = ZohoHelper::refreshZohoToken();
            $createRes = Http::withToken($accessToken)
                ->post("https://www.zohoapis.in/crm/v2/PlanInvoices", $payload);
        }

        if (!$createRes->successful()) {
            return;
        }

        $planInvoiceId = $createRes->json()['data'][0]['details']['id'] ?? null;
        if (!$planInvoiceId) {
            
            return;
        }

        
        // Attach PDF
        $attachUrl = "https://www.zohoapis.in/crm/v2/PlanInvoices/{$planInvoiceId}/Attachments";
        
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
    public function get_plan_payments()
    {
        $response = PlanPayment::with('user')
            ->where('user_id', auth()->user()->user_id)
            ->orderByDesc('created_at')
            ->get()->map(function ($res) {
                $totalPrice = $res->amount; // 3999
                $gstAmount = number_format($totalPrice * 0.18, 2, '.', ''); // (719.82)
                $basePrice = number_format($totalPrice - (float) $gstAmount, 2, '.', ''); // 3279.18
                // then total will be 3999
    
                // Add new key gst_amount and base_price
                $res->base_price = $basePrice;
                $res->gst_amount = $gstAmount;

                return $res;
            });
        return $response;
    }

}

