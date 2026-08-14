<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel's standard database-notifications shape. Used ONLY as the "you have
 * a new Communication" badge/alert -- CommunicationRecipient remains the
 * canonical access/history record (V8A review item 17). Deleting or reading a
 * notification here never touches communication_recipients; read_at on THIS
 * table is Laravel's own badge state, distinct from
 * communication_recipients.read_at ("opened inside RSMS").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
