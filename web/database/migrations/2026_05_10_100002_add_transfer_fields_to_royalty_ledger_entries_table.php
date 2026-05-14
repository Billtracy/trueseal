<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('royalty_ledger_entries', function (Blueprint $table) {
            $table->string('transfer_reference')->nullable()->after('squad_reference');
            $table->string('transfer_status')->nullable()->after('transfer_reference');
            $table->json('transfer_response')->nullable()->after('transfer_status');
        });
    }

    public function down(): void
    {
        Schema::table('royalty_ledger_entries', function (Blueprint $table) {
            $table->dropColumn(['transfer_reference', 'transfer_status', 'transfer_response']);
        });
    }
};
