<?php
namespace App\Support;

use Illuminate\Support\Arr;

class LeadTableSchema
{
    public static function for(string $formKey = null, array $samplePayloads = []): array
    {
        $cfg = config('forms.forms.' . ($formKey ?? ''), null);
        if ($cfg && !empty($cfg['columns'])) {
            return [$cfg['columns'], $cfg['derive'] ?? []];
        }

        // Auto-detect from sample payloads (recent N)
        $keys = [];
        foreach ($samplePayloads as $p) {
            foreach (array_keys((array)$p) as $k) $keys[$k] = true;
        }
        $keys = array_keys($keys);

        // Heuristics → columns
        $columns = [];
        // fullName if firstName/lastName or name present
        if (in_array('firstName',$keys) || in_array('name',$keys)) {
            $columns[] = ['key'=>'fullName','label'=>'Full Name','type'=>'text'];
        }
        if (in_array('email',$keys)) {
            $columns[] = ['key'=>'email','label'=>'Email','type'=>'text'];
        }
        foreach (['phone','phoneNo','mobile'] as $k) {
            if (in_array($k,$keys)) { $columns[] = ['key'=>'phone','label'=>'Phone No','type'=>'text']; break; }
        }
        $columns[] = ['key'=>'submitted_at','label'=>'Submit At','type'=>'datetime'];

        $derive = [
            'fullName' => ['firstName','lastName','name'],
            'email'    => ['email'],
            'phone'    => ['phone','phoneNo','mobile'],
        ];

        return [$columns, $derive];
    }

    public static function deriveField(string $fieldKey, array $deriveMap, array $payload, ?string $value = null): ?string
    {
        $want = $deriveMap[$fieldKey] ?? [];
        if ($fieldKey === 'fullName') {
            $fn = trim(($payload['firstName'] ?? '').' '.($payload['lastName'] ?? ''));
            return $fn !== '' ? $fn : ($payload['name'] ?? null);
        }
        foreach ($want as $k) {
            if (isset($payload[$k]) && $payload[$k] !== '') return (string)$payload[$k];
        }
        // fallback: sometimes `value` carries main field
        return $fieldKey === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }
}
