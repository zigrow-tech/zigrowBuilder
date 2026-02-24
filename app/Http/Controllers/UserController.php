<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function profile()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        return response()->json([
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'company' => $user->company,
            'bio' => $user->bio,
            'country' => $user->country,
            'state' => $user->state,
            'postal_code' => $user->postal_code,
            'gst_number' => $user->gst_number,
            'avatar' => asset('storage/' . $user->avatar),
            'facebook' => $user->facebook, // ✅ New field
            'xcom' => $user->xcom,         // ✅ New field
            'linkedin' => $user->linkedin, // ✅ New field
            'instagram' => $user->instagram // ✅ New field
        ]);
    }
}

