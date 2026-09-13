<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #85: the app's three biggest read paths ran with no secondary
 * index at all beyond the primary key and the auto-created FK indexes.
 *
 * - alerts/experiences feeds: WHERE status='approved' ORDER BY
 *   created_at DESC, id DESC LIMIT 10 — a full-table filesort on every
 *   request. The (status, created_at, id) composite serves the public
 *   filter and both tiebreak legs; (created_at, id) serves the admin
 *   lists, which sort the same way but across all statuses, plus the
 *   profile my-history pages.
 * - comments threads: WHERE {alert,experience}_id = ? AND parent_id IS
 *   NULL ORDER BY created_at DESC (and the replies eager-load on
 *   parent_id IN (...)). The (post, parent_id, created_at) composite
 *   covers both the top-level listing (prefix on post_id, IS NULL is an
 *   index-resolvable predicate) and per-parent reply fetches.
 *
 * Deliberately absent: likes (likeable_type, likeable_id) — morphs()
 * already creates it (issue #85 correction) — and anything for the
 * radius/whereYear queries, which wrap columns in expressions no btree
 * index can answer.
 *
 * Both engines append the PK to secondary index entries anyway; naming
 * `id` explicitly just makes the #81 tiebreak readable in SHOW INDEX.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->index(['status', 'created_at', 'id'], 'alerts_status_created_id_idx');
            $table->index(['created_at', 'id'], 'alerts_created_id_idx');
        });

        Schema::table('experiences', function (Blueprint $table) {
            $table->index(['status', 'created_at', 'id'], 'experiences_status_created_id_idx');
            $table->index(['created_at', 'id'], 'experiences_created_id_idx');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->index(['alert_id', 'parent_id', 'created_at'], 'comments_alert_parent_created_idx');
            $table->index(['experience_id', 'parent_id', 'created_at'], 'comments_exp_parent_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropIndex('alerts_status_created_id_idx');
            $table->dropIndex('alerts_created_id_idx');
        });

        Schema::table('experiences', function (Blueprint $table) {
            $table->dropIndex('experiences_status_created_id_idx');
            $table->dropIndex('experiences_created_id_idx');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex('comments_alert_parent_created_idx');
            $table->dropIndex('comments_exp_parent_created_idx');
        });
    }
};
