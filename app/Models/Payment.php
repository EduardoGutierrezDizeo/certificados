<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'subscription_plan_id', 'reference', 'payment_provider', 'amount_in_cents',
        'wompi_transaction_id', 'gateway_transaction_id', 'status', 'raw_payload',
    ];

    protected function casts(): array
    {
        return ['raw_payload' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function scopePendingVigente(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(30));
    }
}
