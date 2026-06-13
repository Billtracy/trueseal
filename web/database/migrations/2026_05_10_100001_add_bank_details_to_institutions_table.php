<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->string('bank_code', 10)->nullable()->after('opay_subaccount_id');
            $table->string('account_number', 20)->nullable()->after('bank_code');
            $table->string('account_name')->nullable()->after('account_number');
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn(['bank_code', 'account_number', 'account_name']);
        });
    }
};
