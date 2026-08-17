<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NISN (Nomor Induk Siswa Nasional -- Indonesian national student ID),
 * Students only (P2A). Same nullable-unique-where-present shape as `nik`
 * in the companion migration; see that migration's docblock for why a
 * plain nullable unique index is sufficient here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('nisn', 10)->nullable()->unique()->after('nik');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('nisn');
        });
    }
};
