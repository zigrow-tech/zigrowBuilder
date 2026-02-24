<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use App\Services\TokenCreditBridge;

class AiWriterController extends Controller
{
    public function generate(Request $request, TokenCreditBridge $bridge)
    {
        $request->validate([
            'action' => 'required|string|in:rewrite,expand,shorten,improve,transform',
            'tone'   => 'nullable|string|max:50',
            'text'   => 'required|string|max:8000',
        ]);

        $userId = Auth::id();

        // 1) HARD STOP: check remaining tokens based on remaining credits (your 3-value model)
        $status = $bridge->status($userId); // make sure your bridge has a status() that returns statusPayload

        if ((int)($status['remaining_tokens'] ?? 0) <= 0) {
            return response()->json([
                'message' => 'Token limit exhausted. Please upgrade or buy credits.'
            ], 402);
        }

        // 2) Build prompt
        $action = $request->input('action');
        $tone   = $request->input('tone') ?? 'default';
        $text   = $request->input('text');

        $system = "You are an AI writing assistant for a website builder. Follow the user's action and tone.";
        $userPrompt = "Action: {$action}\nTone: {$tone}\n\nText:\n{$text}\n\nReturn only the rewritten text.";

        // 3) Call OpenAI from backend (API key stays in .env)
        $resp = Http::withToken(config('services.openai.key'))
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini', // choose what you want
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.7,
            ]);

        if (!$resp->ok()) {
            return response()->json([
                'message' => 'AI request failed',
                'details' => $resp->json(),
            ], $resp->status());
        }

        $data = $resp->json();

        $outText = trim($data['choices'][0]['message']['content'] ?? '');
        $usedTokens = (int)($data['usage']['total_tokens'] ?? 0);

        // 4) Consume tokens using your existing bridge (this will reduce credits after each 5000)
        if ($usedTokens > 0) {
            $consumeResult = $bridge->consumeTextEditorTokens($userId, $usedTokens);

            // If your consume returns an error payload/flag when exhausted, enforce it:
            if (!empty($consumeResult['error'])) {
                return response()->json([
                    'message' => $consumeResult['error']
                ], 402);
            }

            // Refresh status to return latest values
            $status = $bridge->status($userId);
        }

        return response()->json([
            'text' => $outText,
            'usedTokens' => $usedTokens,
            'tokenStatus' => $status,
        ]);
    }
}
