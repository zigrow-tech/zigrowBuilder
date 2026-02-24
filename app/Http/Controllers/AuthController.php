<?php

namespace App\Http\Controllers;

use App\Helpers\ZohoHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
       $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return response()->json([
                'message' => 'Login successful',
                'user' => Auth::user(),
            ], 200);
        }
        // If authentication fails, return a generic error message
        return response()->json([
            'message' => 'Invalid email or password.'
        ], 401);
    }

    public function get_loggedin_user()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'message' => 'No user is currently logged in.'
            ], 401);
        }
        return response()->json([
            'message' => 'User fetched successfully',
            'user' => $user,
        ], 200);

    }

    public function reset_password(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:6|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => \Hash::make($password),
                ])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Password reset successful.'])
            : response()->json(['message' => __($status)], 400);
    }
    public function reset_password_token(Request $request, $token)
    {
        $email = $request->query('email');
        $qs = http_build_query(['token' => $token, 'email' => $email]);
        return redirect("http://127.0.0.1:5173/forgot-password/reset?{$qs}");
    }
    public function forgot_password(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink(
            $request->only('email'),
            function ($user, string $token) {
                $resetUrl = url("/reset-password/{$token}?email=" . urlencode($user->email));
                $emailBody = ZohoHelper::forgot_password_email($resetUrl, $user);
                Mail::mailer('support')->html(
                    $emailBody,
                    function ($message) use ($user) {
                        $message->from('support@zigrow.com', 'Zigrow Info');
                        $message->to($user->email);
                        $message->subject('Reset your password');
                    }
                );
            }
        );
        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => __($status)])
            : response()->json(['message' => __($status)], 400);
    }
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['message' => 'Logged out'], 200);
    }

}

