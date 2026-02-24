<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PublishedDomain;
use App\Models\Template;
use App\Models\TemplateVisitCount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\SeoSetting;


class WebsiteController extends Controller
{


    public function showTemplate($subdomain = null)
{
    $userIdNumeric = null;
    $published = null;

    if (!$subdomain) {
        // 🔹 Access via custom domain
        $host = request()->getHost();
        $published = PublishedDomain::where('custom_domain', $host)->first();

        if (!$published) {
            abort(404, 'No website found for this domain.');
        }

        // Map custom user_id (e.g. Z-000001) → numeric users.id
        $userIdNumeric = User::where('user_id', $published->user_id)->value('id');
    } else {
        // 🔹 Access via subdomain
        $published = PublishedDomain::where('subdomain', $subdomain)->first();

        if (!$published) {
            abort(404, 'No website found for this domain.');
        }

        // Map custom user_id → numeric users.id
        $userIdNumeric = User::where('user_id', $published->user_id)->value('id');

        // If this subdomain also has a custom domain, redirect there
        if (!empty($published->custom_domain)) {
            if ($userIdNumeric) {
                $this->update_count($userIdNumeric);
            }

            return redirect()->away('https://' . ltrim($published->custom_domain, '/'));
        }
    }

    if (!$published || !$published->template_id) {
        abort(404, 'Template not found for this domain.');
    }

    // Load template content
    $template = Template::find($published->template_id);
    if (!$template) {
        abort(404, 'Template content missing.');
    }

    // Fallback: if numeric user id not resolved via PublishedDomain, use template->user_id
    if (!$userIdNumeric && $template->user_id) {
        $userIdNumeric = $template->user_id;
    }

    // Update visit count only when we have a valid numeric user id
    if ($userIdNumeric) {
        $this->update_count($userIdNumeric);
    }

    $html = $template->html;

    /**
     * 🔹 SEO: Fetch from SeoSetting using numeric user id
     */
    $seo = [];

    if ($userIdNumeric) {
        $seo = SeoSetting::where('user_id', $userIdNumeric)->value('seo_data') ?? [];
    }

    // Normalize metaKeywords: array → comma-separated string
    if (!empty($seo['metaKeywords']) && is_array($seo['metaKeywords'])) {
        $seo['metaKeywords'] = implode(', ', $seo['metaKeywords']);
    }

    /**
     * 🔹 Ensure <head> exists
     */
    if (!str_contains($html, '<head>')) {
        $html = str_replace('<html>', '<html><head></head>', $html);
        \Log::warning('⚠️ <head> tag was missing and added programmatically.');
    }

    /**
     * 🔹 Inject <title>
     */
    if (!empty($seo['pageTitle'])) {
        if (preg_match('/<title>.*<\/title>/i', $html)) {
            $html = preg_replace(
                '/<title>.*<\/title>/i',
                '<title>' . e($seo['pageTitle']) . '</title>',
                $html
            );
        } else {
            $html = str_replace(
                '<head>',
                '<head><title>' . e($seo['pageTitle']) . '</title>',
                $html
            );
        }
    }

    /**
     * 🔹 Inject meta description
     */
    if (!empty($seo['metaDescription'])) {
        // Remove any existing description
        $html = preg_replace('/<meta name="description".*?>/i', '', $html);
        $html = str_replace(
            '<head>',
            '<head><meta name="description" content="' . e($seo['metaDescription']) . '">',
            $html
        );
    }

    /**
     * 🔹 Inject meta keywords
     */
    if (!empty($seo['metaKeywords'])) {
        // Remove any existing keywords tag
        $html = preg_replace('/<meta name="keywords".*?>/i', '', $html);
        $html = str_replace(
            '<head>',
            '<head><meta name="keywords" content="' . e($seo['metaKeywords']) . '">',
            $html
        );
    }

    /**
     * 🔹 Inject favicon
     */
    if (!empty($seo['favicon'])) {
        $html = str_replace(
            '<head>',
            '<head><link rel="icon" href="' . e($seo['favicon']) . '" type="image/x-icon">',
            $html
        );
    }

    /**
     * 🔹 Inject Google Analytics
     */
    if (!empty($seo['googleAnalytics'])) {
        $gaId = $seo['googleAnalytics'];

        $ga = <<<GA
<script async src="https://www.googletagmanager.com/gtag/js?id={$gaId}"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', '{$gaId}');
</script>
GA;

        $html = str_replace('</head>', $ga . "\n</head>", $html);
    }

    /**
     * 🔹 Inject Facebook Pixel
     */
    if (!empty($seo['pixelCode'])) {
        $pixelId = $seo['pixelCode'];

        $pixel = <<<PIXEL
<!-- Meta Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '{$pixelId}');
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none"
src="https://www.facebook.com/tr?id={$pixelId}&ev=PageView&noscript=1"
/></noscript>
<!-- End Meta Pixel Code -->
PIXEL;

        $html = str_replace('</head>', $pixel . "\n</head>", $html);
    }

    /**
     * 🔹 Inject Zigrow Forms (current UAT setup)
     */
    if ($published) {
        // Only inject once (idempotent)
        if (stripos($html, 'name="zigrow:api"') === false) {

            // TODO: move this to config/env for prod
            $apiBase = 'http://127.0.0.1:8000'; // UAT only

            $forms = <<<HTML
<meta name="zigrow:domain-id" content="{$published->id}">
<meta name="zigrow:api" content="{$apiBase}">
<script defer src="https://static.zigrow.com/zigrow-forms/v1.js?v=3"></script>
HTML;

            if (preg_match('/<\/head>/i', $html)) {
                $html = preg_replace('/<\/head>/i', $forms . "\n</head>", $html, 1);
            } elseif (preg_match('/<body[^>]*>/i', $html)) {
                $html = preg_replace('/<body[^>]*>/i', '$0' . "\n" . $forms, $html, 1);
            } else {
                $html = $forms . "\n" . $html;
            }
        }
    }

    return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
}



    private function update_count($user_id)
    {
        $existing = TemplateVisitCount::where('user_id', $user_id)->where('date', Carbon::today()->toDateString())->first();
        if ($existing) {
            $existing->increment('count');
        } else {
            $boolean = TemplateVisitCount::create([
                'user_id' => $user_id,
                //'template_id' => $published->template_id,
                'date' => Carbon::today()->toDateString(),
                'count' => 1,
            ]);
        }
        return;
    }

}

