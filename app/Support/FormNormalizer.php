<?php
namespace App\Support;

class FormNormalizer
{
    /** Map many aliases to canonical keys */
    private const ALIASES = [
        // email
        'email' => ['email','e-mail','email_address','emailaddress','mail'],
        // phone
        'phone' => ['phone','phone_number','phonenumber','mobile','mobile_number','contact','contact_number','tel'],
        // name
        'first_name' => ['first_name','firstname','first','given_name','givenname','fname'],
        'last_name'  => ['last_name','lastname','last','surname','lname','family_name','familyname'],
        'name'       => ['name','full_name','fullname'],
        // message
        'message' => ['message','msg','comments','comment','note','notes','enquiry','inquiry','query'],
        // company (not honeypot) — if you actually collect org name
        'company' => ['company','organization','organisation','org','business'],
        // others add as needed...
    ];

    /** Reverse lookup for quick alias detection */
    private static function aliasMap(): array
    {
        static $map = null;
        if ($map !== null) return $map;
        $map = [];
        foreach (self::ALIASES as $canonical => $list) {
            foreach ($list as $k) $map[strtolower($k)] = $canonical;
        }
        return $map;
    }

    /**
     * Normalize raw payload (flat array) into:
     * - canonical: {firstName,lastName,email,phone,message,company,...}
     * - primary value: prefer email > phone > name
     */
    public static function normalize(array $raw, ?array $overrides = null): array
    {
        $aliasMap = self::aliasMap();
        $canon = [];

        // 1) Fold aliases
        foreach ($raw as $key => $val) {
            $lk = strtolower(trim((string)$key));
            $canonical = $aliasMap[$lk] ?? $lk; // if not in aliases, keep as-is (raw-only)
            switch ($canonical) {
                case 'first_name': $canon['firstName'] = self::stringVal($val); break;
                case 'last_name':  $canon['lastName']  = self::stringVal($val); break;
                case 'name':
                    $canon['name'] = self::stringVal($val);
                    // optionally split name into first/last if needed
                    break;
                case 'email':      $canon['email'] = self::stringVal($val); break;
                case 'phone':      $canon['phone'] = self::stringVal($val); break;
                case 'message':    $canon['message'] = self::stringVal($val); break;
                case 'company':    $canon['company'] = self::stringVal($val); break;
                default:
                    // keep unfamiliar fields under extras
                    if (!isset($canon['extras'])) $canon['extras'] = [];
                    $canon['extras'][$key] = $val;
            }
        }

        // 2) Apply template/form overrides (optional)
        if ($overrides) {
            // Example: force a certain field as primary, rename keys, etc.
            $canon = array_merge($canon, $overrides);
        }

        // 3) Pick primary "value"
        $primary = $canon['email']
            ?? $canon['phone']
            ?? ($canon['name'] ?? ($canon['firstName'] ?? null));

        return [
            'primary'  => $primary ? (string)$primary : null,
            'canonical'=> $canon,
        ];
    }

    private static function stringVal($v): ?string
    {
        if (is_array($v))  return implode(', ', array_map('strval', $v));
        if (is_bool($v))   return $v ? 'true' : 'false';
        $s = trim((string)$v);
        return $s !== '' ? $s : null;
    }
}
