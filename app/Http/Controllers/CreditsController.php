<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use App\Services\TokenCreditBridge;

class CreditsController extends Controller
{
    
    // public function remaining_credits()
    // {
    //     $user = auth()->user();
    //     try {
    //         if ($user) {
    //             $remaining_credits = $user->remaining_credits;
    //             $total_credits = 10;
    //             if ($user->plan_type == 'paid')
    //                 $total_credits = 20;
    //             return response()->json([
    //                 'status' => 'success',
    //                 'message' => 'Success',
    //                 'total_credits' => $total_credits,
    //                 'remaining_credits' => $remaining_credits,
    //                 'user_type' => $user->plan_type,
    //             ]);

    //         } else {
    //             return response()->json(['status' => 'Error', 'Message' => 'User Not Found !'], 403);
    //         }
    //     } catch (Exception $e) {
    //         return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
    //     }
    // }

        public function remaining_credits(TokenCreditBridge $bridge)
    {
        $user = auth()->user();
        try {
            if ($user) {
                $remaining_credits = $user->remaining_credits;
                $total_credits = 10;
                if ($user->plan_type == 'paid')
                    $total_credits = 20;

                // ✅ Token bridge status (adds partial token-based deduction)
            $tokenStatus = $bridge->status($user->id);

            $remainingCreditsDecimal = $tokenStatus['credits_remaining_decimal'] ?? $remainingCreditsInt;
            $spentCreditsDecimal = round($total_credits - $remainingCreditsDecimal, 2);

            $percentUsed = ($total_credits > 0)
                ? round(($spentCreditsDecimal / $total_credits) * 100)
                : 0;
                return response()->json([
                    'status' => 'success',
                    'message' => 'Success',
                    'total_credits' => $total_credits,
                    'remaining_credits' => $remaining_credits,
                    'user_type' => $user->plan_type,
                     // ✅ New: token-aware UI fields
                'remaining_credits_decimal' => $remainingCreditsDecimal, // e.g. 13.72
                'spent_credits_decimal' => $spentCreditsDecimal,         // e.g. 6.28
                'percent_used' => $percentUsed,                          // e.g. 31
                ]);

            } else {
                return response()->json(['status' => 'Error', 'Message' => 'User Not Found !'], 403);
            }
        } catch (Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }


    public function change_remaining(Request $request)
    {
        $auth_user = auth()->user();
        if ($auth_user) {
            if ($auth_user->remaining_credits < 1) {
                return response()->json([
                    'status' => 'Error',
                    'message' => 'Insufficient credits.',
                ], 403);
            }

            try {
                $auth_user->decrement('remaining_credits', 1);

                return response()->json([
                    'status'=> 'success',
                    'message' => 'Credits have been updated successfully.',
                    'remaining_credits' => $auth_user->fresh()->remaining_credits
                ]);
            } catch (Exception $e) {
                return response()->json([
                    'status' => 'Error',
                    'message' => $e->getMessage(),
                ], 500); // Use HTTP status code 500 for Server Error
            }
        } else {
            return response()->json([
                'status' => 'Error',
                'message' => 'User Not Found',
            ]);
        }
    }



}
