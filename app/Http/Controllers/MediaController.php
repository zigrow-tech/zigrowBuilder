<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Models\MediaFile;
use Illuminate\Support\Facades\Log;

class MediaController extends Controller
{

public function list(Request $request)
{
    $user = auth()->user();
    if (!$user) {
        Log::warning('Media list request without auth', ['ip' => $request->ip()]);
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $userFolder = public_path('media/uploads/' . $user->id . '/');
    $urlPrefix  = asset('media/uploads/' . $user->id . '/');
     $pathForDb  = '/media/uploads/' . $user->id . '/'; 

    $urls  = [];
    $sizes = [];
    $times = [];

    Log::info('Scanning media folder', [
        'user_id'    => $user->id,
        'userFolder' => $userFolder,
        'urlPrefix'  => $urlPrefix,
    ]);

    if (is_dir($userFolder)) {
        foreach (File::files($userFolder) as $file) {
            $ext = strtolower($file->getExtension());

            Log::debug('Found file', [
                'filename' => $file->getFilename(),
                'ext'      => $ext,
                'size'     => $file->getSize(),
            ]);

            if (in_array($ext, ['jpg','jpeg','png','gif','webp','svg','pdf','mp4','webm','ogg'])) {
                $url = rtrim($urlPrefix, '/') . '/' . $file->getFilename();
                $urls[] = $url;
                $sizes[$url] = $file->getSize();
                // include modified time (ISO 8601) so frontend can sort by recency
                try {
                    $mtime = @filemtime($file->getPathname());
                    $times[$url] = $mtime ? date('c', $mtime) : null;
                } catch (\Exception $e) {
                    $times[$url] = null;
                }

                Log::debug('Accepted media file', [
                    'url'  => $url,
                    'size' => $file->getSize(),
                ]);
            }
        }
    } else {
        Log::warning('User folder does not exist', [
            'user_id' => $user->id,
            'path'    => $userFolder,
        ]);
    }

    // Build meta map keyed by ABSOLUTE URL so frontend can attach quickly
    $metaMap = [];
    $rows = \App\Models\MediaFile::where('user_id', $user->id)
        ->where('path', $pathForDb) // e.g. /media/uploads/77/
        ->whereIn('filename', array_map(fn($u) => basename($u), $urls))
        ->get();

    foreach ($rows as $row) {
        $fullUrl = rtrim($urlPrefix, '/') . '/' . $row->filename;
        $metaMap[$fullUrl] = [
            'title'       => $row->title,
            'alt'         => $row->alt,
            'description' => $row->description,
            // if you keep a DB record with timestamps, expose created_at for frontend sorting
            'created_at'  => isset($row->created_at) ? $row->created_at->toIso8601String() : null,
        ];
    }

    $response = [
        'files' => $urls,
        'sizes' => $sizes,
                'times' => $times,
          'meta'    => $metaMap,
    ];

    Log::info('Media list response', $response);

    return response()->json($response);

    
    // Pull meta rows once and key by filename
    $meta = \App\Models\MediaFile::where('user_id', $user->id)
             ->where('path', $userFolderWeb)
             ->get()
             ->keyBy('filename');

    foreach ($items as &$it) {
        $fn = $it['filename'];
        if (isset($meta[$fn])) {
            $row = $meta[$fn];
            $it['meta'] = [
                'title'       => $row->title,
                'alt'         => $row->alt,
                'description' => $row->description,
            ];
        }
    }

    return response()->json(['success'=>true, 'files'=>$items]);
}






public function upload(Request $request)
{
    Log::info('Upload request received', ['user_id' => optional(auth()->user())->id, 'ip' => $request->ip()]);

    $user = auth()->user();
    if (!$user) {
        Log::warning('Unauthorized upload attempt', ['ip' => $request->ip()]);
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    Log::debug('Validating upload request');
    $request->validate([
        // 'file' => 'required|file|max:1024|mimes:jpg,jpeg,png,gif,svg,webp,pdf,mp4,webm,ogg', // max in KB (1MB)
         'file' => 'required|file|mimes:jpg,jpeg,png,gif,svg,webp,pdf,mp4,webm,ogg|max:51200', // 50 MB
    ], [
        'file.max' => 'File size exceeds 1MB limit.',
        'file.mimes' => 'Invalid file type. Only images are allowed.',
    ]);

    $userFolder = public_path('media/uploads/' . $user->id . '/');
    Log::debug('User folder resolved', ['userFolder' => $userFolder]);

    if (!is_dir($userFolder)) {
        Log::info('User folder does not exist, creating', ['userFolder' => $userFolder]);
        if (!mkdir($userFolder, 0775, true)) {
            Log::error('Failed to create user folder', ['userFolder' => $userFolder]);
            return response()->json(['error' => 'Failed to create user folder'], 500);
        }
    }

    $file = $request->file('file');
    Log::debug('File received', [
        'originalName' => $file->getClientOriginalName(),
        'mime' => $file->getClientMimeType(),
        'size' => $file->getSize(),
    ]);

    $originalName = $file->getClientOriginalName();
    $nameOnly     = pathinfo($originalName, PATHINFO_FILENAME);
    $ext          = strtolower($file->getClientOriginalExtension());

    // Preserve user-provided characters (spaces, dashes, underscores) but strip
    // anything unsafe (slashes, control chars, other special chars).
    // This keeps filenames looking exactly as users set them while avoiding
    // directory traversal and very unusual characters.
    $safeBase = preg_replace('/[^A-Za-z0-9 _\-]/u', '', $nameOnly);
    $safeBase = preg_replace('/\s+/u', ' ', $safeBase); // collapse multiple spaces
    $safeBase = trim($safeBase);
    if ($safeBase === '') {
        Log::warning('Sanitized filename empty, generating random');
        $safeBase = \Illuminate\Support\Str::random(8);
    }

    $filename = $safeBase . '.' . $ext;

    // avoid overwrite: add -1, -2, … if exists
    $i = 1;
    while (file_exists($userFolder . $filename)) {
        Log::info('Filename exists, incrementing', ['filename' => $filename]);
        $filename = $safeBase . '-' . $i . '.' . $ext;
        $i++;
    }

    Log::debug('Final filename determined', ['filename' => $filename]);

    // move file
    try {
        $file->move($userFolder, $filename);
        Log::info('File moved successfully', ['path' => $userFolder . $filename]);
    } catch (\Exception $e) {
        Log::error('File move failed', ['error' => $e->getMessage()]);
        return response()->json(['error' => 'Failed to save file'], 500);
    }

    $userFolderWeb = '/media/uploads/' . $user->id . '/';
    $url  = asset('media/uploads/' . $user->id . '/' . $filename);
    $absPath = $userFolder . $filename;

    // --- file meta
    $size  = @filesize($absPath) ?: null;
    $mime  = $file->getClientMimeType() ?: (@mime_content_type($absPath) ?: null);
    $width = null;
    $height = null;

    Log::debug('File meta', [
        'size' => $size,
        'mime' => $mime,
        'absPath' => $absPath,
    ]);

    // raster dimensions (SVG won’t return here)
    if (in_array($ext, ['jpg','jpeg','png','gif','webp','pdf','mp4','webm','ogg',])) {
        if ($info = @getimagesize($absPath)) {
            $width  = $info[0] ?? null;
            $height = $info[1] ?? null;
            Log::debug('Image dimensions', ['width' => $width, 'height' => $height]);
        } else {
            Log::warning('Could not get image dimensions', ['absPath' => $absPath]);
        }
    }

    // --- default SEO/meta (can be edited later in “Image details” modal)
    // Keep the title/alt consistent with the preserved filename (do not convert
    // dashes/underscores to spaces) so UI shows exactly what the user specified.
    $defaultTitle = $nameOnly;
    $defaultAlt   = $safeBase;
    $defaultDesc  = null;

    // --- DB upsert (create row if new / update meta if re-uploaded same name)
    try {
        MediaFile::updateOrCreate(
            [
                'user_id'  => $user->id,
                'path'     => $userFolderWeb,
                'filename' => $filename,
            ],
            [
                'mime'        => $mime,
                'size'        => $size,
                'width'       => $width,
                'height'      => $height,
                'title'       => $defaultTitle,
                'alt'         => $defaultAlt,
                'description' => $defaultDesc,
            ]
        );
        Log::info('MediaFile DB upserted', ['filename' => $filename, 'user_id' => $user->id]);
    } catch (\Exception $e) {
        Log::error('DB upsert failed', ['error' => $e->getMessage()]);
        return response()->json(['error' => 'Failed to save file metadata'], 500);
    }

    Log::info('Upload completed successfully', ['filename' => $filename, 'url' => $url]);

    return response()->json([
        'success'  => true,
        'url'      => $url,
        'filename' => $filename,
        'size'     => $size,
        'mime'     => $mime,
        'width'    => $width,
        'height'   => $height,
        'meta' => [
            'title'       => $defaultTitle,
            'alt'         => $defaultAlt,
            'description' => $defaultDesc,
        ],
    ]);
}
  

    public function delete(Request $r)
{
    $r->validate([
        'file' => 'required|string', // absolute URL of the file
    ]);

    $user = auth()->user();
    if (!$user) {
        return response()->json(['success'=>false, 'message'=>'Unauthorized'], 401);
    }

    // Convert absolute URL to local path (same logic you used for updateMeta)
    $urlPath = parse_url($r->file, PHP_URL_PATH);     // e.g. /media/uploads/77/hero.webp
    $dir     = Str::of($urlPath)->beforeLast('/') . '/';
    $name    = Str::of($urlPath)->afterLast('/');

    // guard: only allow within the user's folder
    $expectedPrefix = '/media/uploads/'.$user->id.'/';
    if (!Str::startsWith($urlPath, $expectedPrefix)) {
        return response()->json(['success'=>false, 'message'=>'Invalid file path'], 422);
    }

    $abs = public_path(ltrim($urlPath, '/')); // full local path

    // unlink file if exists
    $deleted = false;
    if (File::exists($abs)) {
        $deleted = @File::delete($abs);
    }

    // remove meta row if you keep one
    if (class_exists(MediaFile::class)) {
        MediaFile::where('user_id', $user->id)
            ->where('path', $dir)
            ->where('filename', $name)
            ->delete();
    }

    if ($deleted) {
        return response()->json(['success'=>true, 'message'=>'File deleted.']);
    } else {
        // file might already be missing, but still clean up meta above
        return response()->json(['success'=>true, 'message'=>'File removed (or not found).']);
    }
}



public function rename(\Illuminate\Http\Request $request)
{
    $request->validate([
        'file'    => 'required|string',      // absolute URL to current file
        'newfile' => 'required|string'       // new filename, no path
    ]);

    $user = auth()->user();
    if (!$user) {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $fileUrl  = $request->input('file');
    $newName  = trim($request->input('newfile'));

    // basic sanitize: allow letters, numbers, spaces, dash, underscore and dot
    // Do NOT convert spaces to hyphens here — remove disallowed characters
    $newName = preg_replace('/[^A-Za-z0-9 ._\-]/u', '', $newName);
    // collapse multiple whitespace into a single space
    $newName = preg_replace('/\s+/u', ' ', $newName);
    $newName = trim($newName);
    if ($newName === '' || $newName === '.' || $newName === '..') {
        return response()->json(['success' => false, 'message' => 'Invalid file name'], 422);
    }

    // Extract path after domain, e.g. /media/uploads/{user_id}/file.webp
    $path = parse_url($fileUrl, PHP_URL_PATH); // "/media/uploads/77/file.webp"
    if (!$path) {
        return response()->json(['success' => false, 'message' => 'Invalid file URL'], 422);
    }

    // Security: ensure it lives under the user’s own folder
    $userFolder = "/media/uploads/{$user->id}/";
    if (strpos($path, $userFolder) !== 0) {
        return response()->json(['success' => false, 'message' => 'Forbidden path'], 403);
    }

    $absOld = public_path($path);
    if (!file_exists($absOld)) {
        return response()->json(['success' => false, 'message' => 'Original file not found'], 404);
    }

    // Build new absolute path in the same folder
    $dir    = public_path($userFolder);
    $absNew = $dir . $newName;

    // Prevent overwrite unless you want to allow it
    if (file_exists($absNew)) {
        return response()->json(['success' => false, 'message' => 'A file with this name already exists'], 409);
    }

    // Do the rename
    if (!@rename($absOld, $absNew)) {
        return response()->json(['success' => false, 'message' => 'Rename failed on server'], 500);
    }

    $newUrl = url($userFolder . $newName);

    // if you compute size maps in upload, you can return them too
    return response()->json([
        'success'  => true,
        'message'  => 'Renamed successfully.',
        'filename' => $newName,
        'url'      => $newUrl,
    ]);
}

public function updateMeta(Request $r) {
    $r->validate([
        'file'        => 'required|string', // absolute URL
        'title'       => 'nullable|string|max:255',
        'alt'         => 'nullable|string|max:255',
        'description' => 'nullable|string|max:2000',
    ]);
    $user = auth()->user();
    $path = parse_url($r->file, PHP_URL_PATH);        // /media/uploads/77/hero.webp
    $dir  = Str::of($path)->beforeLast('/') . '/';    // /media/uploads/77/
    $name = Str::of($path)->afterLast('/');           // hero.webp

    $row = MediaFile::firstOrCreate([
        'user_id'=>$user->id, 'path'=>$dir, 'filename'=>$name
    ]);
    $row->fill($r->only('title','alt','description'))->save();

    return response()->json([
        'success'=>true,
        'message'=>'Metadata updated',
        'meta'=> $row->only('title','alt','description')
    ]);
}


public function listAi(Request $request)
{
    // Auth check (jo already tha)
    $user = auth()->user();
    if (!$user) {
        // \Log::warning('AI gallery list without auth', ['ip' => $request->ip()]);
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    // GLOBAL AI gallery folder
    $aiFolder  = public_path('media/ai-gallery/');
    $urlPrefix = asset('media/ai-gallery/');
    $dbPath    = '/media/ai-gallery/';

    $urls    = [];
    $sizes   = [];
    $metaMap = [];

    // ✅ STEP 1: JSON tags file read karo
    // Example path: public/media/ai-gallery/ai-tags.json
    $tagsMap = [];
    $tagsJsonPath = public_path('media/ai-gallery/ai-tags.json');

    if (File::exists($tagsJsonPath)) {
        try {
            $raw = File::get($tagsJsonPath);
            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                // CASE A: [ { "filename": "doctor-1.webp", "tags": ["doctor","clinic"] }, ... ]
                foreach ($decoded as $row) {
                    $filename = $row['filename'] ?? $row['file'] ?? null;
                    if (!$filename) {
                        continue;
                    }

                    $tags = $row['tags'] ?? [];
                    // string hua to comma se split
                    if (is_string($tags)) {
                        $tags = array_filter(array_map('trim', explode(',', $tags)));
                    }

                    if (!is_array($tags)) {
                        $tags = [];
                    }

                    $tagsMap[$filename] = $tags;
                }
            }
        } catch (\Throwable $e) {
            // \Log::error('AI tags JSON parse error', [
            //     'path'  => $tagsJsonPath,
            //     'error' => $e->getMessage(),
            // ]);
        }
    }

    // ✅ STEP 2: Folder scan + meta + tags attach
    if (is_dir($aiFolder)) {
        foreach (File::files($aiFolder) as $file) {
            $filename = $file->getFilename();
            $ext = strtolower($file->getExtension());

            // sirf image formats
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'])) {
                continue;
            }

            $url = rtrim($urlPrefix, '/') . '/' . $filename;

            // size
            $sizes[$url] = $file->getSize();

            // basic meta
            $baseName = pathinfo($filename, PATHINFO_FILENAME);
            $title = $baseName;
            $alt   = str_replace(['-', '_'], ' ', $baseName);

            $meta = [
                'title' => $title,
                'alt'   => $alt,
            ];

            // 🔗 JSON se tags attach karo
            if (isset($tagsMap[$filename])) {
                $meta['tags'] = $tagsMap[$filename]; // array of strings
            }

            $urls[]          = $url;
            $metaMap[$url]   = $meta;
        }
    }

    return response()->json([
        'files' => $urls,     // array of URLs
        'sizes' => $sizes,    // { url: size }
        'meta'  => $metaMap,  // { url: { title, alt, tags[] } }
    ]);
}
public function searchStockImages(Request $request)
{
    $query = $request->get('query', 'featured');
    $page = max(1, (int) $request->get('page', 1));
    $perPage = max(1, min(50, (int) $request->get('per_page', 30)));

    if (empty($query)) {
        return response()->json(['results' => [], 'page' => $page, 'per_page' => $perPage, 'total_pages' => 0]);
    }

    try {
        $client = new \GuzzleHttp\Client();

        $response = $client->get('https://api.unsplash.com/search/photos', [
            'query' => [
                'query' => $query,
                'per_page' => $perPage,
                'page' => $page,
            ],
            'headers' => [
                'Authorization' => 'Client-ID ' . config('services.unsplash.access_key'),
                'Accept-Version' => 'v1',
            ],
        ]);

        $data = json_decode($response->getBody(), true);

        $results = collect($data['results'] ?? [])->map(function ($photo) {
            return [
                'id'          => $photo['id'],
                'thumb'       => $photo['urls']['small'],
                'full'        => $photo['urls']['regular'],
                'alt'         => $photo['alt_description'] ?? '',
                'author'      => $photo['user']['name'] ?? '',
                'author_link' => $photo['user']['links']['html'] ?? '',
            ];
        })->values();

        $totalPages = isset($data['total_pages']) ? (int) $data['total_pages'] : null;

        return response()->json([
            'results' => $results,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ]);
    } catch (\Exception $e) {
        Log::error('Stock search failed', ['message' => $e->getMessage(), 'query' => $query, 'page' => $page]);
        return response()->json(['results' => [], 'page' => $page, 'per_page' => $perPage, 'total_pages' => 0], 500);
    }
}


}

