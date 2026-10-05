<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The single ACLi subscription settings row.
 *
 * There is exactly one row. The superadmin edits it
 * directly and the entitlement service reads it, so the
 * price, the payment type and the schedule the admin
 * sets are the ones the gate enforces -- there is no
 * second copy to drift out of sync.
 */
class AcliSubscriptionSetting extends Model
{
    protected $table = 'acli_subscription_settings';

    protected $fillable = [
        'payment_enabled',
        'payment_type',
        'price',
        'currency',
        'grace_period_days',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'payment_enabled' => 'boolean',
            'price' => 'decimal:2',
            'grace_period_days' => 'integer',
        ];
    }

    /**
     * The one settings row, creating it on first read so the
     * entitlement check works before the migration has been
     * re-run against a database that predates this table.
     */
    public static function current(): self
    {
        $row = self::first();

        if ($row === null) {
            $row = self::create([
                'payment_enabled' => false,
                'payment_type' => 'one_time',
                'price' => 0,
                'currency' => 'NGN',
                'grace_period_days' => 7,
                'description' => 'ACLi AI features are free by default. Enable payment to gate access.',
            ]);
        }

        return $row;
    }

    public function isPaymentEnabled(): bool
    {
        return (bool) $this->payment_enabled;
    }

    public function getPrice(): string
    {
        return number_format((float) $this->price, 2) . ' ' . $this->currency;
    }

    public function getPaymentTypeLabel(): string
    {
        return match ($this->payment_type) {
            'monthly' => 'Monthly subscription',
            'yearly' => 'Yearly subscription',
            default => 'One-time payment',
        };
    }
}
