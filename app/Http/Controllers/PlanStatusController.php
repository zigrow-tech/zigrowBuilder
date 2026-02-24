<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Models\User;


class PlanStatusController extends Controller
{
  // PlanStatusController.php
public function check()
{
    $user = Auth::user();

    if (!$user) {
        return response()->json(['error' => 'User not authenticated'], 401);
    }

    $isPaid = $user->plan_type === 'paid';
    $expiryRaw = $user->plan_expires_at;
    $isLifetime = false;
    $expiresAtFormatted = '';
    $duration = null;

    // Detect Lifetime plan
    if (!$expiryRaw || $expiryRaw === '' || $expiryRaw === null) {
        $isLifetime = true;
        $expiresAtFormatted = 'Lifetime';
    } else {
        $expiryDateObj = \Carbon\Carbon::parse($expiryRaw);
        if ($expiryDateObj->year === 1970) {
            $isLifetime = true;
            $expiresAtFormatted = 'Lifetime';
        } else {
            $expiresAtFormatted = $expiryDateObj->toDateString();
        }
    }

    // Duration extraction
    if (Str::contains($user->plan_name, '1 Year')) $duration = '1 Year';
    elseif (Str::contains($user->plan_name, '2 Year')) $duration = '2 Year';
    elseif (Str::contains($user->plan_name, '3 Year')) $duration = '3 Year';
    elseif (Str::contains($user->plan_name, 'Lifetime')) $duration = 'Lifetime';

    // Status logic
    $isActive = false;
    if ($isPaid) {
        if ($isLifetime) {
            $isActive = true;
        } else {
            $expiryDateObj = \Carbon\Carbon::parse($expiryRaw);
            $isActive = now()->lt($expiryDateObj);
        }
    }

 

    return response()->json([
        'paid' => $isPaid,
        'active' => $isActive,
        'plan_name' => $user->plan_name,
        'expiresAtFormatted' => $expiresAtFormatted,
        'duration' => $duration,
        'isLifetime' => $isLifetime,
    ]);
}

}

