<?php

namespace App\Http\Controllers;

use App\Services\TokenCreditBridge;
use Illuminate\Http\Request;

class UserTokenController extends Controller
{
    public function status(Request $request, TokenCreditBridge $bridge)
    {
        return response()->json($bridge->status($request->user()->id));
    }

    // AI Text Editor will call this (tokens will reduce credits if they cross 5000)
    public function consume(Request $request, TokenCreditBridge $bridge)
    {
        $request->validate([
            'tokens' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json(
            $bridge->consumeTextEditorTokens($request->user()->id, (int) $request->tokens)
        );
    }

    // Optional: Template generation can call this to log tokens (NO credit deduction)
    public function recordTemplateTokens(Request $request, TokenCreditBridge $bridge)
    {
        $request->validate([
            'tokens' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json(
            $bridge->recordTemplateTokensOnly($request->user()->id, (int) $request->tokens)
        );
    }
}
