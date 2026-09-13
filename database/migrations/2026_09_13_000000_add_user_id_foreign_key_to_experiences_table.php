<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Issue #47: experiences.user_id was a plain unsignedBigInteger while
     * alerts/comments/likes/support_requests all cascade, so deleting an
     * account left its experience posts behind forever.
     */
    public function up(): void
    {
        // Rows already pointing at deleted users (ghost ids) would violate
        // the constraint on MySQL the moment it is added, and they are the
        // exact leftovers the cascade is meant to prevent — remove them.
        // NULL user_id rows are kept: they predate per-user accounts and the
        // views render their denormalized `name`, and a FK accepts NULLs.
        DB::statement('DELETE FROM experiences WHERE user_id IS NOT NULL AND user_id NOT IN (SELECT id FROM users)');

        Schema::table('experiences', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
    }
};
