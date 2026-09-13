<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #108: support_requests.admin_id was a dead column — no write site
 * ever set it (store() sets user_id+subject only, close() touches status
 * only, no factory/seeder covers it) and no read site ever used it (the
 * admin() relation has zero callers; admins are told apart via
 * messages.user.isAdmin and the is_admin JSON flag). Every thread carried
 * a permanent NULL plus an FK index no join ever used. Drop it; the old
 * migration stays untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_requests', function (Blueprint $table) {
            // FK first: dropping the column while the constraint lives 500s
            // on MySQL. dropConstrainedForeignId handles both in one call —
            // on SQLite the dropForeign leg compiles to a no-op (FKs vanish
            // implicitly when the table is rebuilt for the column drop)
            // while dropColumn executes for real.
            $table->dropConstrainedForeignId('admin_id');
        });
    }

    public function down(): void
    {
        Schema::table('support_requests', function (Blueprint $table) {
            $table->foreignId('admin_id')->nullable()->constrained('users')->onDelete('set null');
        });
    }
};
