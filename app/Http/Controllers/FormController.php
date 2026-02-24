<?php

namespace App\Http\Controllers;
use App\Models\PublishedDomain;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Models\FormSubmission; // make sure this is imported
use App\Support\FormNormalizer;
use Illuminate\Support\Facades\DB;


class FormController extends Controller
{
    // Public endpoint: simple 1-field form
    public function submit(Request $req)
    {
        Log::info('Form submission started', [
            'ip' => $req->ip(),
            'user_agent' => $req->userAgent(),
            'input' => $req->all(), // raw request data
        ]);

        try {
            $data = $req->validate([
                //'template_id' => 'nullable|integer|exists:templates,id',
                'domain'      => 'required|string|max:255',
                'form_key'    => 'nullable|string|max:64',
                'value'       => 'required|string|max:500', // abhi single field
                'page_url'    => 'nullable|string|max:1024',
                // anti-spam (honeypot)
                '_company'    => 'nullable|string|max:100',
            ]);



// --- OWNER RESOLUTION (user-scoped) ---
$host = (string) $req->input('domain');
$pd   = $this->findPublishedDomainByHost($host);
if (!$pd) {
    \Log::warning('Forms: Unknown domain for owner resolution', ['domain' => $host]);
    return response()->json(['ok'=>false, 'message'=>'Unknown domain'], 404);
}

/**
 * Two patterns in your stack:
 *  - published_domains.user_id stores your custom code like "Z-000017" (maps to users.user_id)
 *  - (optional) published_domains.owner_user_id = users.id (if you ever add it)
 */
$ownerId = null;

// (A) direct numeric FK if present
if (isset($pd->owner_user_id) && is_numeric($pd->owner_user_id)) {
    $ownerId = (int) $pd->owner_user_id;
}

// (B) fallback: map custom code -> users.id
if (!$ownerId && !empty($pd->user_id)) {
    $ownerId = (int) DB::table('users')->where('user_id', $pd->user_id)->value('id');
}

if (!$ownerId) {
    \Log::warning('Forms: cannot resolve owner_user_id from published_domains', ['pd_id' => $pd->id ?? null, 'domain' => $host]);
    return response()->json(['ok'=>false, 'message'=>'Ownership not resolved'], 404);
}



            Log::info('Validation passed', ['data' => $data]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', ['errors' => $e->errors()]);
            throw $e; // still throw so user gets proper validation response
        }

        // honeypot: bots mostly fill hidden fields
        if (!empty($req->input('_company'))) {
            Log::warning('Honeypot triggered, dropping submission', [
                'domain' => $req->input('domain'),
                'form_key' => $req->input('form_key'),
            ]);

            return response()->json(['ok' => true]); // silently accept & drop
        }

        $rawPayload = $req->input('payload', '');
$rawArr = [];
if (is_string($rawPayload) && $rawPayload !== '') {
    $tmp = json_decode($rawPayload, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
        $rawArr = $tmp;
    }
}

$normalized = FormNormalizer::normalize($rawArr /*, $overrides optional */);

// Prefer frontend-provided value but fallback to normalizer
$value = $req->input('value');
if (!$value && $normalized['primary']) {
    $value = $normalized['primary'];
}

// Build payload to store (choose one of the two styles):

// (A) Canonical only (lean & clean)
$payloadToStore = $normalized['canonical'] ?: null;


        
        try {
            $submission = FormSubmission::create([
                 'owner_user_id' => $ownerId,         // NEW: stable owner
                'template_id'   => null,   
                'domain'       => $data['domain'],
                'form_key'     => $data['form_key'] ?? 'contact',
                'value'        => $data['value'],
                'payload'      => $payloadToStore, // future-proof if you add more fields
                'page_url'     => $data['page_url'] ?? null,
                'ip'           => $req->ip(),
                'ua'           => substr($req->userAgent() ?? '', 0, 512),
                'submitted_at' => now(),
            ]);

            Log::info('Form submission saved', [
                'submission_id' => $submission->id,
                'domain' => $submission->domain,
                'form_key' => $submission->form_key,
            ]);
        } catch (\Exception $e) {
            Log::error('Error while saving submission', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'Failed to save submission',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'id' => $submission->id,
            'message' => 'Submitted',
        ], 201);
    }




private function findPublishedDomainByHost(string $host): ?PublishedDomain
{
    // normalize
    $host = strtolower(trim($host, " \t\n\r\0\x0B."));  // trim trailing dot/spaces

    // Zigrow-managed roots (UAT + Prod)
    $roots = ['dashboard.zigrow.com', 'zigrow.com'];

    // 1) If host ends with a zigrow root, take the FIRST label as subdomain
    foreach ($roots as $root) {
        if (Str::endsWith($host, '.' . $root)) {
            // e.g. site-8q0dqx.uat.zigrow.com -> site-8q0dqx
            $labels = explode('.', $host);
            $subdomain = $labels[0] ?? '';
            if ($subdomain !== '') {
                $row = PublishedDomain::where('subdomain', $subdomain)->first();
                if ($row) {
                    \Log::info('Forms: resolved by subdomain', compact('host','subdomain','root'));
                    return $row;
                }
                // fallback: if you ever store multi-label subdomains, try the full pre-root part
                $rootParts = explode('.', $root);
                $preRoot = implode('.', array_slice($labels, 0, count($labels) - count($rootParts))); // everything before root
                if ($preRoot && $preRoot !== $subdomain) {
                    $row = PublishedDomain::where('subdomain', $preRoot)->first();
                    if ($row) {
                        \Log::info('Forms: resolved by preRoot', compact('host','preRoot','root'));
                        return $row;
                    }
                }
            }
            // if we’re here, subdomain path failed; continue to custom domain as a last resort
            break;
        }
    }

    // 2) Otherwise treat as custom domain
    $row = PublishedDomain::where('custom_domain', $host)->first();
    if ($row) {
        \Log::info('Forms: resolved by custom_domain', compact('host'));
        return $row;
    }

    // 3) Log what we tried for easier debugging
    \Log::warning('Forms: Unknown host mapping', ['host' => $host]);
    return null;
}

}
