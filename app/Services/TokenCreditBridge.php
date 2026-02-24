<?php

namespace App\Services;

use App\Models\UserTokenWallet;
use Illuminate\Support\Facades\DB;

class TokenCreditBridge
{
    public const TOKENS_PER_CREDIT = 5000;

    /**
     * Use this for AI Text Editor token consumption (this CAN reduce credits).
     */
    public function consumeTextEditorTokens(int $userId, int $tokens): array
    {
        return DB::transaction(function () use ($userId, $tokens) {

            // lock user row because we may decrement credits
            $user = DB::table('users')->where('id', $userId)->lockForUpdate()->first();
            if (!$user) abort(404, 'User not found');

            // lock or create wallet
            $wallet = UserTokenWallet::where('user_id', $userId)->lockForUpdate()->first();
            if (!$wallet) {
                $wallet = UserTokenWallet::create(['user_id' => $userId]);
                $wallet = UserTokenWallet::where('user_id', $userId)->lockForUpdate()->first();
            }

            // Update analytics total
            $wallet->tokens_used_total_all += $tokens;

            // Update tokens that count towards credit deduction
            $wallet->tokens_used_for_credit += $tokens;

            // Figure out how many credits should be deducted in total from token usage
            $shouldDeductTotal = intdiv($wallet->tokens_used_for_credit, self::TOKENS_PER_CREDIT);
            $toDeductNow = $shouldDeductTotal - (int) $wallet->credits_deducted_by_tokens;

            if ($toDeductNow > 0) {
                if ($toDeductNow > (int) $user->remaining_credits) {
                    abort(403, 'Insufficient credits (token usage exceeded)');
                }

                DB::table('users')->where('id', $userId)->update([
                    'remaining_credits' => (int) $user->remaining_credits - $toDeductNow,
                    'updated_at' => now(),
                ]);

                $wallet->credits_deducted_by_tokens += $toDeductNow;
            }

            $wallet->save();

            return $this->statusPayload($userId, $wallet);
        });
    }

    /**
     * Use this for AI Template Generation token accounting (NO credit deduction here),
     * because template generation already costs 1 credit via existing flow.
     */
    public function recordTemplateTokensOnly(int $userId, int $tokens): array
    {
        return DB::transaction(function () use ($userId, $tokens) {
            $wallet = UserTokenWallet::where('user_id', $userId)->lockForUpdate()->first();
            if (!$wallet) {
                $wallet = UserTokenWallet::create(['user_id' => $userId]);
                $wallet = UserTokenWallet::where('user_id', $userId)->lockForUpdate()->first();
            }

            $wallet->tokens_used_total_all += $tokens;
            $wallet->save();

            return $this->statusPayload($userId, $wallet);
        });
    }

    public function status(int $userId): array
    {
        $wallet = UserTokenWallet::firstOrCreate(['user_id' => $userId]);
        return $this->statusPayload($userId, $wallet);
    }



  private function statusPayload(int $userId, UserTokenWallet $wallet): array
{
    $remainingCredits = (int) DB::table('users')->where('id', $userId)->value('remaining_credits');

    $planType = DB::table('users')->where('id', $userId)->value('plan_type');
    $planTypeNorm = strtolower(trim((string) $planType));
    $planCredits = ($planTypeNorm === 'paid') ? 20 : 10;

    $usedInCurrent = (int) ($wallet->tokens_used_for_credit % self::TOKENS_PER_CREDIT);

    $totalTokens = $planCredits * self::TOKENS_PER_CREDIT;

    $consumedTokens =
        (($planCredits - $remainingCredits) * self::TOKENS_PER_CREDIT)
        + $usedInCurrent;

    $remainingTokens = max(
        0,
        ($remainingCredits * self::TOKENS_PER_CREDIT) - $usedInCurrent
    );

    $creditsRemainingDecimal = max(
        0,
        $remainingCredits - ($usedInCurrent / self::TOKENS_PER_CREDIT)
    );

    return [
        'user_id' => $userId,

        'total_tokens' => $totalTokens,
        'consumed_tokens' => $consumedTokens,
        'remaining_tokens' => $remainingTokens,

        'used_tokens_in_current_credit' => $usedInCurrent,
        'tokens_left_in_current_credit' => self::TOKENS_PER_CREDIT - $usedInCurrent,

        'used_in_current_tokens' => $usedInCurrent,
        'credits_remaining_decimal' => round($creditsRemainingDecimal, 2),
    ];
}

}
