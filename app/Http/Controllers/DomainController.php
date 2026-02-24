<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\PublishedDomain;
use App\Models\Template;
use App\Models\User;



class DomainController extends Controller
{
 public function create(Request $request)
    {
        $request->validate([
            'subdomain' => 'required|string|alpha_dash|unique:published_domains,subdomain',
        ]);

        $user = Auth::user();
        // ✅ Use firstOrNew to avoid duplicates 
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }
        // ✅ Use firstOrNew to avoid duplicates
        $domain = PublishedDomain::firstOrNew(['user_id' => $user->user_id]);
        $domain->subdomain = $request->subdomain;
        $domain->template_id = Template::where('user_id', $user->id)->latest()->value('id') ?? null;
        $domain->save();

        // \Log::info('✅ Domain saved.', ['final_subdomain' => $domain->subdomain]);

        return response()->json([
            'success' => true,
            'message' => 'Subdomain saved.',
        ]);
    }


public function checkAvailability(Request $request)
{
    $request->validate([
        'subdomain' => 'required|string|alpha_dash',
    ]);

    $currentUser = Auth::user();
    $subdomain = $request->subdomain;

   $existing = \App\Models\PublishedDomain::where('subdomain', $subdomain)->first();

    if ($existing) {
        if ($existing->user_id == $currentUser->id) {
            return response()->json([
                'available' => true,
                'message' => 'This is your current subdomain.',
            ]);
        } else {
            return response()->json([
                'available' => false,
                'message' => 'Subdomain already taken.',
            ]);
        }
    }

    // ✅ Available if not found at all
    return response()->json([
        'available' => true,
        'message' => 'Subdomain is available.',
    ]);
}




public function saveCustom(Request $request)
{
    $request->validate([
        'domain' => 'required|string|regex:/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/|unique:published_domains,custom_domain',
    ]);

    $user = Auth::user();

    // Fetch the most recent subdomain entry for this user
    $domain = PublishedDomain::where('user_id', $user->user_id)->latest()->first();

    if (!$domain) {
        $domain = new PublishedDomain();
        $domain->user_id = $user->user_id;
    }

    $domain->custom_domain = strtolower($request->domain);
    $domain->save();


    return response()->json(['message' => 'Custom domain saved']);
}




public function verifyDNS(Request $request)
{
    $domain = $request->input('domain');
    $expectedIp = '23.20.129.191';

    if (!$domain) {
        return response()->json(['valid' => false, 'error' => 'Domain is required.'], 400);
    }

    try {
        $rootRecords = dns_get_record($domain, DNS_A);
        $wwwRecords = dns_get_record("www." . $domain, DNS_A);

        $rootIps = collect($rootRecords)->pluck('ip')->filter()->unique();
        $wwwIps = collect($wwwRecords)->pluck('ip')->filter()->unique();
        $allIps = $rootIps->merge($wwwIps)->unique();

        $isValid = $allIps->contains($expectedIp);

        return response()->json([
            'valid' => $isValid,
            'checked' => $domain,
            'found_ips' => $allIps,
            'expected_ip' => $expectedIp
        ]);
    } catch (\Exception $e) {
        // \Log::error('❌ DNS error:', [$e->getMessage()]);
        return response()->json(['valid' => false, 'error' => 'DNS lookup failed.'], 500);
    }
}


public function deleteCustomDomain(Request $request)
{
    $user = Auth::user();

    if (!$user) {
        return response()->json(['error' => 'Unauthenticated'], 401);
    }

    $domain = PublishedDomain::where('user_id', $user->user_id)->first();

    if (!$domain) {
        return response()->json(['message' => 'No domain found for this user.'], 404);
    }

    $domain->custom_domain = null;
    $domain->save();

    /* \Log::info("🗑️ Custom domain removed", [
        'user_id' => $user->user_id,
    ]);*/

    return response()->json(['message' => 'Custom domain removed successfully.']);
}

public function get_user_domain(){
        $user = Auth::user(); // ✅ Works because we're in web.php

        if (!$user) {
            return response()->json([
                'subdomain' => null,
                'url' => null,
                'custom_domain' => null,
                'message' => 'Unauthenticated',
            ]);
        }

        $domain = $user->publishedDomain;

        return response()->json([
            'subdomain' => $domain?->subdomain,
            'custom_domain' => $domain?->custom_domain,
            'url' => $domain
                ? ($domain->custom_domain
                    ? "https://{$domain->custom_domain}"
                    : "https://{$domain->subdomain}.zigrow.com")
                : null,
        ]);
}




}

