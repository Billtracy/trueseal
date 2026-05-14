<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'verification_id',
    'provider',
    'transaction_ref',
    'checkout_url',
    'status',
    'amount_kobo',
    'platform_amount_kobo',
    'royalty_amount_kobo',
    'currency',
    'paid_at',
    'raw_response',
])]
class Payment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'raw_response' => 'array',
        ];
    }

    public function verification(): BelongsTo
    {
        return $this->belongsTo(Verification::class);
    }

    public function royaltyLedgerEntry(): HasOne
    {
        return $this->hasOne(RoyaltyLedgerEntry::class);
    }
}
