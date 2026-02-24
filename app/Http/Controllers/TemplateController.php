<?php

namespace App\Http\Controllers; // ✅ Required to avoid class conflict

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Template;
use Illuminate\Support\Str;
use Firebase\JWT\JWT;
use App\Models\SeoSetting;

class TemplateController extends Controller
{
    public function save(Request $request)
    {
        $request->validate([
            'template_name' => 'required|string',
            'html' => 'required|string',
        ]);

        $user = Auth::user();
        $numericUserId = $user->id;         // used for templates
        $customUserId = $user->user_id;    // used in published_domains
        $templateName = trim(strtolower($request->template_name));
        $folderName = basename($templateName);
        $cleanHtml = $this->fixAssetPaths($request->html, $folderName, $numericUserId);

        // ✅ Capture the result of updateOrCreate
        $template = Template::updateOrCreate(
            ['user_id' => $numericUserId, 'template_name' => $templateName],
            ['html' => $cleanHtml]
        );

        // ✅ Update published_domains.template_id to latest saved template
        $updated = \DB::table('published_domains')
            ->where('user_id', $customUserId)
            ->update(['template_id' => $template->id]);

        return response()->json(['message' => 'Saved successfully']);
    }

    public function load(Request $request)
    {
        $templateName = $request->query('template_name');
        $userId = Auth::id();
        $template = Template::where('user_id', $userId)
            ->where('template_name', $templateName)
            ->first();
        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        $folderName = basename($templateName);
        $fixedHtml = $this->fixAssetPaths($template->html, $templateName, $userId);

         $templateArr = $template->toArray();
        $templateArr['html'] = $fixedHtml;

        return response()->json([
            'template' => $templateArr

        ]);

        // return response()->json([
        //     'template' => $template
        // ]);
    }


    public function delete(Request $request)
    {
        $request->validate([
            'template_name' => 'required|string',
        ]);

        $deleted = Template::where('user_id', Auth::id())
            ->where('template_name', $request->template_name)
            ->delete();

        if ($deleted) {
            return response()->json(['message' => 'Template deleted successfully']);
        }

        return response()->json(['message' => 'Template not found'], 404);
    }
       private function fixAssetPaths(string $html, string $folderName, int $userId): string
{
    $folderName = trim($folderName, "/ \t\n\r\0\x0B");

    // ✅ Same-origin root relative paths (Odoo-like)
    $base = "/zigrow-assets/{$folderName}/";

    // Handle href/src for css/js/assets (supports both css/ and ./css/)
    $replacements = [
        'href="css/'      => 'href="' . $base . 'css/',
        "href='css/"      => "href='" . $base . "css/",
        'href="./css/'    => 'href="' . $base . 'css/',
        "href='./css/"    => "href='" . $base . "css/",

        'src="js/'        => 'src="' . $base . 'js/',
        "src='js/"        => "src='" . $base . "js/",
        'src="./js/'      => 'src="' . $base . 'js/',
        "src='./js/"      => "src='" . $base . "js/",

        'src="assets/'    => 'src="' . $base . 'assets/',
        "src='assets/"    => "src='" . $base . "assets/",
        'src="./assets/'  => 'src="' . $base . 'assets/',
        "src='./assets/"  => "src='" . $base . "assets/",
    ];

    return str_replace(array_keys($replacements), array_values($replacements), $html);
}




         public function saveSeo(Request $request)
{
    $user = Auth::user();

    $validated = $request->validate([
         'seoData'                => 'required|array',
        'seoData.pageTitle'      => 'nullable|string|max:255',
        'seoData.metaDescription'=> 'nullable|string|max:500',
        'seoData.metaKeywords'   => 'nullable|string', // ⬅ CHANGED from array → string
        'seoData.googleAnalytics'=> 'nullable|string|max:255',
        'seoData.pixelCode'      => 'nullable|string',
        'seoData.favicon'        => 'nullable|string',
    ]);

    $seoData = $validated['seoData'];

    // Upsert based on user_id
    $seoSetting = SeoSetting::updateOrCreate(
        ['user_id' => $user->id],
        ['seo_data' => $seoData]
    );

    return response()->json([
        'message' => 'SEO settings saved successfully.',
        'seoData' => $seoSetting->seo_data,
    ]);
}

 public function getSeo(Request $request)
{
    $user = Auth::user();

    $seoSetting = SeoSetting::where('user_id', $user->id)->first();

    if (!$seoSetting || empty($seoSetting->seo_data)) {
        return response()->json(['seoData' => null]);
    }

    return response()->json(['seoData' => $seoSetting->seo_data]);
}

    // For creating copy of generated template by AI

    public function importAI(Request $request)
    {
        $user_id = $request->input('user_id');
        $aiUrl = "https://build-with-ai.zigrow.com/preview/{$user_id}";

        $html = @file_get_contents($aiUrl);

        if ($html === false) {
            return response()->json(['error' => 'Failed to fetch AI preview.'], 400);
        }

        $folder = public_path("builder/templates/build-with-ai/{$user_id}");
        if (!file_exists($folder)) {
            mkdir($folder, 0775, true);
        }
        file_put_contents("{$folder}/index.html", $html);

        if (file_put_contents("{$folder}/index.html", $html) === false) {
            return response()->json(['error' => 'Failed to write file.'], 500);
        }


        return response()->json(['success' => true]);
    }


    public function getPreviewLink(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $userId = $user->user_id; // e.g. "Z-000008"

        $payload = [
            'user_id' => $userId,
            'iat' => time(),
            'exp' => time() + 10, // 30 second expiry for checking
        ];

        $jwt = JWT::encode($payload, (string) env('JWT_SECRET'), 'HS256');

        $previewUrl = "https://build-with-ai.zigrow.com/preview/$userId?token=$jwt";

        return response()->json([
            'token' => $jwt,
            'preview_url' => $previewUrl
        ]);
    }
    public function saved_template()
    {
        $userId = auth()->id();
        if (!$userId) {
            return response()->json([
                'template_name' => null,
                'updated_at' => null,
                'message' => 'User Not Found',
            ]);
        }
        $template = Template::where('user_id', $userId)->latest()->first();
        return response()->json([
            'template_name' => $template->template_name ?? null,
            'updated_at' => $template->updated_at ?? null,
        ]);
    }
    public function get_fixed_template()
    {
        $template = Template::find(14);
        $base = 'https://static.zigrow.com/digital-marketing/';

        $html = $template->html;
        $html = str_replace(['href="css/', "href='css/"], ["href=\"{$base}css/", "href='{$base}css/"], $html);
        $html = str_replace(['src="assets/', "src='assets/"], ["src=\"{$base}assets/", "src='{$base}assets/"], $html);
        $html = str_replace(['src="js/', "src='js/"], ["src=\"{$base}js/", "src='{$base}js/"], $html);

        $template->html = $html;
        $template->save();

        return '✅ Template paths updated!';
    }

public function aiLoad(Request $request)
{
    $requested = strtoupper((string) $request->query('user_id'));
    $user = Auth::user();

    if (!$user) {
        return response('Unauthorized', 401);
    }

    $userUid = strtoupper((string) $user->user_id); // normalize

    if (!$requested || $userUid !== $requested) {
        return response('This AI template does not belong to your account.', 403);
    }

    // ✅ Option 1: fetch from Node preview URL (runtime fetch)
    // Choose the correct host for UAT:
    $aiUrl = "https://build-with-ai.zigrow.com/preview/{$requested}/index.html";
   

    $html = @file_get_contents($aiUrl);

    if ($html === false || trim($html) === '') {
        return response('Failed to fetch AI preview HTML', 400);
    }

    // Return as HTML (editor will wrap/insert base tag)
    return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
}



}




