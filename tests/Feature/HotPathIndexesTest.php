<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Issue #85: the alerts/experiences feeds, admin lists and comment
 * threads all ran filesort/full-scan because the only indexes on those
 * tables were the PK and the auto-created FK ones. The migration pins
 * the composites the hot paths need. Schema::hasIndex resolves column
 * lists through each driver's getIndexes, so these assertions are
 * dialect-tolerant by construction (sqlite and mysql CI both run it).
 */
class HotPathIndexesTest extends TestCase
{
    use RefreshDatabase;

    public function test_alerts_feeds_are_indexed(): void
    {
        // Public feed: WHERE status = 'approved' ORDER BY created_at DESC,
        // id DESC (index() + the #83 dashboard breakdown read).
        $this->assertTrue(
            Schema::hasIndex('alerts', ['status', 'created_at', 'id']),
            'alerts missing the (status, created_at, id) feed index'
        );
        // Admin list + my-history: ORDER BY created_at DESC, id DESC over
        // all statuses.
        $this->assertTrue(
            Schema::hasIndex('alerts', ['created_at', 'id']),
            'alerts missing the (created_at, id) tiebreak-order index'
        );
    }

    public function test_experiences_feeds_are_indexed(): void
    {
        $this->assertTrue(
            Schema::hasIndex('experiences', ['status', 'created_at', 'id']),
            'experiences missing the (status, created_at, id) feed index'
        );
        $this->assertTrue(
            Schema::hasIndex('experiences', ['created_at', 'id']),
            'experiences missing the (created_at, id) tiebreak-order index'
        );
    }

    public function test_comment_threads_are_indexed(): void
    {
        // Top-level thread listing (post_id = ? AND parent_id IS NULL
        // ORDER BY created_at DESC) and the replies eager-load
        // (parent_id IN (...)) share this prefix order.
        $this->assertTrue(
            Schema::hasIndex('comments', ['alert_id', 'parent_id', 'created_at']),
            'comments missing the (alert_id, parent_id, created_at) thread index'
        );
        $this->assertTrue(
            Schema::hasIndex('comments', ['experience_id', 'parent_id', 'created_at']),
            'comments missing the (experience_id, parent_id, created_at) thread index'
        );
    }

    public function test_likes_morph_lookup_index_survives(): void
    {
        // Not added by this migration — morphs() creates it — but the
        // #85 scoping decision ("likes is already covered") rests on it,
        // so pin it rather than trust the vendor helper silently.
        $this->assertTrue(
            Schema::hasIndex('likes', ['likeable_type', 'likeable_id']),
            'likes lost the (likeable_type, likeable_id) morph index morphs() provides'
        );
    }
}
