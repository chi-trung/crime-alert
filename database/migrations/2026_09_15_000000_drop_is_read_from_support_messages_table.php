<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #119: support_messages.is_read was a dead column — nothing ever set
 * it to true (no controller, command, policy, or job touches it) and nothing
 * ever read it (no query filters on it, no blade renders it, messagesAjax()
 * doesn't include it). Read state already lives in the notifications table
 * (NewSupportMessage fans out per reply). Plain boolean, no FK, so a bare
 * dropColumn suffices on both dialects. The old migration stays untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->dropColumn('is_read');
        });
    }

    public function down(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->boolean('is_read')->default(false);
        });
    }
};
