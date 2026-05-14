<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'country', 'squad_subaccount_id', 'bank_code', 'account_number', 'account_name', 'bank_name', 'account_last4'])]
class Institution extends Model
{
    use HasFactory;

    public function verifications(): HasMany
    {
        return $this->hasMany(Verification::class);
    }
}
