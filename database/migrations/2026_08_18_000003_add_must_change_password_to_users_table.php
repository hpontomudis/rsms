<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forced first-login password change (P2B). True for any account created
 * with a server-generated temporary password (bulk provisioning) or after
 * an administrative reset; false once the user has set their own password.
 * Existing accounts default to false -- nobody already logged in gets
 * force-redirected by this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
