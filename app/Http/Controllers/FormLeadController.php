<?php

namespace App\Http\Controllers;

use App\Models\FormSubmission;
use App\Support\LeadTableSchema;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;

class FormLeadController extends Controller
{
    public function index(Request $r)
    {
        $r->validate([
            'page'      => 'sometimes|integer|min:1',
            'per_page'  => 'sometimes|integer|min:1|max:100',
            'q'         => 'sometimes|string|max:200',
            'form_key'  => 'sometimes|string|max:100',
            'date_from' => 'sometimes|date_format:Y-m-d',
            'date_to'   => 'sometimes|date_format:Y-m-d',
            'sort'      => 'sometimes|in:submitted_at,-submitted_at',
            'view'      => 'sometimes|in:meta,data', // meta = columns-only
        ]);

        $userId   = $r->user()->id;
        $perPage  = (int)($r->input('per_page', 20));
        $q        = $r->input('q');
        $formKey  = $r->input('form_key');
        $dateFrom = $r->input('date_from');
        $dateTo   = $r->input('date_to');
        $sort     = $r->input('sort', '-submitted_at');
        $view     = $r->input('view', 'data');

        $query = FormSubmission::query()
            ->where('owner_user_id', $userId);

        if ($formKey)    $query->where('form_key', $formKey);
        if ($dateFrom)   $query->whereDate('submitted_at', '>=', $dateFrom);
        if ($dateTo)     $query->whereDate('submitted_at', '<=', $dateTo);
        if ($q) {
            $like = '%'.$q.'%';
            $query->where(function ($w) use ($like) {
                $w->where('value', 'like', $like)
                  ->orWhereRaw("JSON_EXTRACT(payload,'$.name') LIKE ?", [$like])
                  ->orWhereRaw("JSON_EXTRACT(payload,'$.firstName') LIKE ?", [$like])
                  ->orWhereRaw("JSON_EXTRACT(payload,'$.lastName') LIKE ?", [$like])
                  ->orWhereRaw("JSON_EXTRACT(payload,'$.email') LIKE ?", [$like])
                  ->orWhereRaw("JSON_EXTRACT(payload,'$.phone') LIKE ?", [$like])
                  ->orWhereRaw("JSON_EXTRACT(payload,'$.phoneNo') LIKE ?", [$like])
                  ->orWhereRaw("JSON_EXTRACT(payload,'$.mobile') LIKE ?", [$like]);
            });
        }

        $query->orderBy('submitted_at', $sort === '-submitted_at' ? 'desc' : 'asc');

        // Sample payloads for auto-detect schema (cheap)
        $sample = (clone $query)->select('payload')->latest('submitted_at')->take(20)->get()->pluck('payload')->all();
        [$columns, $derive] = LeadTableSchema::for($formKey, $sample);

        if ($view === 'meta') {
            return response()->json([
                'columns' => $columns,
                'pagination' => null,
                'rows' => [],
            ]);
        }

        $p = $query->paginate($perPage);
        $rows = [];
        foreach ($p->items() as $item) {
            $payload = (array)($item->payload ?? []);
            $row = ['id' => $item->id];

            foreach ($columns as $col) {
                $key = $col['key'];
                switch ($key) {
                    case 'submitted_at':
                        $row[$key] = optional($item->submitted_at)->toISOString();
                        break;
                    default:
                        // derive dynamic fields from payload/value
                        $row[$key] = LeadTableSchema::deriveField($key, $derive, $payload, $item->value) 
                                     ?? ($payload[$key] ?? null);
                }
            }

            // Optional: raw payload for preview drawer
            $row['payload'] = $payload;

            $rows[] = $row;
        }

        return response()->json([
            'columns' => $columns,
            'rows' => $rows,
            'pagination' => [
                'page' => $p->currentPage(),
                'per_page' => $p->perPage(),
                'total' => $p->total(),
            ],
        ]);
    }

    // Add this helper
    protected function resolveOwnerId(Request $request): string
    {
        $user = Auth::user();
        if (!$user) abort(401, 'Unauthenticated');

        // Your app often has a public string id like "Z-000017"
        return $user->user_id ?? (string) $user->id;
    }
public function todayCount(\Illuminate\Http\Request $request)
{
    $user = \Illuminate\Support\Facades\Auth::user();
    if (!$user) abort(401, 'Unauthenticated');

    // Your schema shows owner_user_id is numeric in DB → use numeric id.
    $ownerId = (int) $user->id;

    // --- IST-based "today" (no UTC conversion) ---
    $tz      = 'Asia/Kolkata';
    $nowIst  = \Illuminate\Support\Carbon::now($tz);
    $istDate = $nowIst->toDateString(); // e.g., "2025-10-15"

    // Primary query: date match in IST (best for DATETIME stored as local wall time)
    $istQ = \App\Models\FormSubmission::where('owner_user_id', $ownerId)
        ->whereDate('submitted_at', $istDate);

    if ($key = $request->query('form_key')) {
        $istQ->where('form_key', $key);
    }

    $istCount = (clone $istQ)->count();

    // --- Secondary (debug only): UTC window, for comparison ---
    $startUtc = $nowIst->copy()->startOfDay()->utc();
    $endUtc   = $nowIst->copy()->endOfDay()->utc();

    $utcQ = \App\Models\FormSubmission::where('owner_user_id', $ownerId)
        ->whereBetween('submitted_at', [$startUtc, $endUtc]);

    if ($key) $utcQ->where('form_key', $key);
    $utcCount = (clone $utcQ)->count();

    // Extra diagnostics: last few rows for THIS owner
    $latestForOwner = \App\Models\FormSubmission::where('owner_user_id', $ownerId)
        ->orderByDesc('submitted_at')->limit(5)
        ->get(['owner_user_id','form_key','submitted_at'])
        ->toArray();

 

    // Return IST-based count as the source of truth for the dashboard
    return response()->json(['count' => $istCount]);
}
}
