<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ZzProbe2LikeNullOwner extends TestCase
{
    use RefreshDatabase;

    public function test_like_approved_experience_with_null_user_id(): void
    {
        $user = User::factory()->create();
        $exp = Experience::create([
            'user_id' => null,
            'name' => 'Legacy',
            'title' => 't',
            'content' => 'c',
            'status' => 'approved',
        ]);
        $this->assertNull($exp->user_id);

        $res = $this->actingAs($user)->postJson('/like', ['type' => 'experience', 'id' => $exp->id]);
        fwrite(STDERR, "\nPROBE-LIKE status: ".$res->getStatusCode()."\n");
        if ($res->exceptions->isNotEmpty()) {
            $e = $res->exceptions->first();
            fwrite(STDERR, 'PROBE-LIKE exception: '.get_class($e).': '.$e->getMessage()."\n");
        }
        fwrite(STDERR, 'PROBE-LIKE like rows after: '.DB::table('likes')->count()."\n");
        fwrite(STDERR, 'PROBE-LIKE relation null?: '.var_export($exp->fresh()->user === null, true)."\n");
    }

    public function test_like_null_owner_via_comment_branch(): void
    {
        // Control: the same expression pair on a comment whose author row was
        // hard-deleted (user_id left dangling is impossible due to cascade, so
        // use a null-owner experience as the only reachable null-author case).
        $user = User::factory()->create();
        $exp = Experience::create([
            'user_id' => null,
            'name' => 'Legacy',
            'title' => 't2',
            'content' => 'c',
            'status' => 'approved',
        ]);
        $res = $this->actingAs($user)->post('/like', ['type' => 'experience', 'id' => $exp->id]);
        fwrite(STDERR, "\nPROBE-WEB status: ".$res->getStatusCode()."\n");
    }
}
