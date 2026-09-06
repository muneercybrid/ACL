<?php

namespace App\Models;

/**
 * Extends the base Subscription model with AI-specific fields.
 *
 * This model is used to track student AI subscriptions.
 * The key business rule: Students must have an active paid AI subscription
 * for ANY AI execution. No free exceptions.
 *
 * Fields added to the subscriptions table:
 * - ai_entitlement: boolean - does this plan include AI?
 * - ai_provider: string - which AI provider this subscription is for
 * - ai_model: string - which model this subscription includes
 * - daily_token_limit: integer - daily token usage limit
 * - monthly_token_limit: integer - monthly token usage limit
 * - usage_count_today: integer - today's usage count
 * - usage_count_this_month: integer - this month's usage count
 * - daily_reset_at: timestamp - when daily counter resets
 * - monthly_reset_at: timestamp - when monthly counter resets
 */
class AISubscription extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'subscriptions';

    protected $fillable = [
        'user_id',
        'plan',
        'status',
        'start_date',
        'expiration_date',
        'cancellation_status',
        'payment_status',
        'ai_entitlement', // boolean: does this plan include AI?
        'ai_provider', // which AI provider this subscription is for
        'ai_model', // which model this subscription includes
        'daily_token_limit',
        'monthly_token_limit',
        'usage_count_today',
        'usage_count_this_month',
        'daily_reset_at',
        'monthly_reset_at',
    ];

    /**
     * Check if the subscription is active and includes AI entitlement.
     *
     * This is the critical check for student AI access.
     * Students MUST have an active paid AI subscription - NO EXCEPTIONS.
     *
     * @return bool true if the subscription is active with AI entitlement
     */
    public function isActiveWithAIEntitlement(): bool
    {
        // Status must be active
        if ($this->status !== 'active') {
            return false;
        }

        // Expiration date must be in the future
        if (!$this->expiration_date) {
            return false;
        }

        if ($this->expiration_date->isPast()) {
            return false;
        }

        // Payment must be paid
        if ($this->payment_status !== 'paid') {
            return false;
        }

        // AI entitlement must be enabled
        if ($this->ai_entitlement !== true) {
            return false;
        }

        return true;
    }

    /**
     * Check if the student has exceeded their daily token limit.
     *
     * @return bool true if within limits, false if exceeded
     */
    public function isWithinDailyTokenLimit(int $tokensUsed): bool
    {
        if (!$this->daily_token_limit) {
            return true; // No limit set
        }

        $currentCount = $this->usage_count_today ?? 0;
        $remaining = $this->daily_token_limit - $currentCount;

        return $tokensUsed <= $remaining;
    }

    /** Increment today's usage count */
    public function incrementDailyUsage(int $tokensUsed = 0): void
    {
        // If a daily reset time is set and it has passed, reset the counter
        if ($this->daily_reset_at && $this->daily_reset_at->isPast()) {
            $this->usage_count_today = 0;
        }

        $current = $this->usage_count_today ?? 0;
        $this->usage_count_today = $current + ($tokensUsed > 0 ? $tokensUsed : 1);
    }

    /** Reset today's usage count (called at midnight) */
    public function resetDailyUsage(): void
    {
        $this->usage_count_today = 0;
    }

    /** Get remaining daily tokens */
    public function remainingDailyTokens(): int
    {
        if (!$this->daily_token_limit) {
            return 999999; // Unlimited
        }

        return $this->daily_token_limit - ($this->usage_count_today ?? 0);
    }
}