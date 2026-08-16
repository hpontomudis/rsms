<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metadata ONLY -- who asked, for what, from which provider/model, whether
 * it succeeded, how many tokens, whether the User later clicked Apply. NOT
 * the prompt, NOT the response, NOT estimated cost (V9A architecture
 * review: tokens now, cost later; prompt/response never, by default --
 * they can carry student/guardian/staff/communication content that has no
 * business sitting in a log table).
 *
 * `accepted_at` means exactly one thing: the User explicitly clicked Apply
 * on this generation. It is not a claim that every word survived into the
 * eventual save, or that the User never edited it afterward -- the
 * canonical save is a completely independent, normally-audited write
 * through the existing domain service, which has no idea AI was involved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('use_case');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->string('prompt_version');
            $table->enum('status', ['success', 'failed', 'rate_limited']);
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('error_code')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generations');
    }
};
