<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id',
    'institution_id',
    'candidate_name',
    'candidate_email',
    'document_path',
    'original_filename',
    'status',
    'verdict',
    'score',
    'findings',
    'suspicious_regions',
    'forensic_details',
    'heatmap_path',
    'engine_error',
    'scanned_at',
])]
class Verification extends Model
{
    use HasFactory;

    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PASS = 'pass';
    public const STATUS_FAIL = 'fail';
    public const STATUS_ERROR = 'error';

    protected function casts(): array
    {
        return [
            'findings' => 'array',
            'suspicious_regions' => 'array',
            'forensic_details' => 'array',
            'scanned_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function royaltyLedgerEntry(): HasOne
    {
        return $this->hasOne(RoyaltyLedgerEntry::class);
    }

    public function hasCompletedScan(): bool
    {
        return in_array($this->status, [self::STATUS_PASS, self::STATUS_FAIL, self::STATUS_ERROR], true);
    }
}
