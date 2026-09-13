<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Issue #211: /alerts/create advertised "tối đa 5MB" while AlertController's
 * store()/update() rules enforce max:2048 (kilobytes) = 2MB — the page told
 * users to attach images the server would reject, after the whole form had
 * been filled in. The fix brought the copy down to 2MB (the enforced number
 * per the issue's chosen resolution); these tests pin BOTH sides so the copy
 * and the rule can never drift apart again: the rendered page against the
 * validator source, and the real 3MB/1MB boundary behavior itself.
 */
class AlertUploadCopyTest extends TestCase
{
    use RefreshDatabase;

    private function createPageHtml(): string
    {
        $user = User::factory()->create();

        return $this->actingAs($user)
            ->get('/alerts/create')
            ->assertOk()
            ->getContent();
    }

    public function test_page_advertises_the_enforced_2mb_instead_of_5mb(): void
    {
        $html = $this->createPageHtml();

        $this->assertStringContainsString('tối đa 2MB', $html);
        $this->assertStringNotContainsString('5MB', $html);
    }

    public function test_displayed_size_matches_the_validator_rule_number(): void
    {
        // The copy-vs-rule drift this issue is about: read the max: out of
        // AlertController's 'image' rule (kB) and the advertised number out
        // of the rendered page (MB) and require them to describe ONE limit.
        $controller = file_get_contents(app_path('Http/Controllers/AlertController.php'));
        $this->assertSame(1, preg_match("/'image' => '[^']*max:(\d+)/", $controller, $rule));

        $html = $this->createPageHtml();
        $this->assertSame(1, preg_match('/tối đa (\d+)MB/u', $html, $copy));

        $this->assertSame((int) $rule[1].' kB', (int) $copy[1] * 1024 .' kB');
    }

    public function test_a_3mb_upload_rejected_proves_the_enforced_cap_is_2mb(): void
    {
        // Behavior side of the pin: 3MB sits inside the OLD advertised range
        // but above the enforced 2048 kB, so it must be rejected with the
        // 2048-kB validation error. If someone later raises the rule toward
        // the old 5MB claim, the consistency test above breaks in lockstep.
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/alerts', [
                'title' => 'Ke gian tranh tien',
                'description' => 'Mo ta canh bao',
                'confirmCheckbox' => '1',
                'image' => UploadedFile::fake()->image('big.png')->size(3072),
            ])
            ->assertSessionHasErrors('image');

        $this->assertSame(0, Alert::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_1mb_upload_within_the_advertised_cap_is_accepted(): void
    {
        // The other edge: 1MB is inside 2MB, so the page's promise must hold
        // in the accepting direction too (proves the cap really is 2MB, not
        // some smaller number the copy could also misdescribe).
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/alerts', [
                'title' => 'Ke gian tranh tien',
                'description' => 'Mo ta canh bao',
                'confirmCheckbox' => '1',
                'image' => UploadedFile::fake()->image('ok.png')->size(1024),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Alert::count());
    }
}
