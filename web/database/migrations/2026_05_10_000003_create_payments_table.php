<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verification_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('opay');
            $table->string('transaction_ref')->unique();
            $table->string('checkout_url')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('amount_kobo');
            $table->unsignedInteger('platform_amount_kobo');
            $table->unsignedInteger('royalty_amount_kobo');
            $table->string('currency', 3)->default('NGN');
            $table->timestamp('paid_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
