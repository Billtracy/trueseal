<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->string('candidate_name');
            $table->string('candidate_email');
            $table->string('document_path');
            $table->string('original_filename');
            $table->string('status')->default('pending_payment');
            $table->string('verdict')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->json('findings')->nullable();
            $table->json('suspicious_regions')->nullable();
            $table->string('heatmap_path')->nullable();
            $table->text('engine_error')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verifications');
    }
};
