<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #210: /admin/alerts registered THREE submit listeners for every
 * form.form-reject — public/js/alerts_admin_index.js (loaded by a script
 * tag), a byte-identical inline block in the same blade, and the
 * whole-layout handler in layouts/app.blade.php. All three preventDefault
 * into Swal.fire, and sweetalert2@11 is a singleton: each fire() destroys
 * the previous instance and resolves its promise as dismissed, so only the
 * LAST-registered handler (the layout's generic 'từ chối bài này' wording)
 * ever rendered a dialog the admin could actually confirm. The
 * alert-specific wording the page shipped — twice — was unreachable dead
 * code. The fix deletes both extra registrations and lets the surviving
 * layout handler read optional data-reject-title / data-reject-text
 * attributes off the form, which the alerts page now sets.
 */
class AdminAlertsRejectHandlerTest extends TestCase
{
    use RefreshDatabase;

    private function renderAdminAlerts(): string
    {
        $admin = User::factory()->admin()->create();
        Alert::create([
            'user_id' => $admin->id,
            'title' => 'pending one',
            'description' => 'd',
            'status' => 'pending',
        ]);

        return $this->actingAs($admin)
            ->get('/admin/alerts')
            ->assertOk()
            ->getContent();
    }

    public function test_page_ships_exactly_one_form_reject_registration(): void
    {
        $html = $this->renderAdminAlerts();

        // The layout's single listener is the only querySelectorAll block
        // left on the page (pre-fix this counted 2 — layout + inline — and
        // the deleted external file made a third registration).
        $this->assertSame(
            1,
            substr_count($html, "querySelectorAll('form.form-reject')"),
            'form-reject must have exactly one handler registration'
        );
        $this->assertDoesNotMatchRegularExpression('/<script[^>]*alerts_admin_index\.js/', $html);
    }

    public function test_alert_wording_reaches_the_dialog_through_the_surviving_handler(): void
    {
        $html = $this->renderAdminAlerts();

        // The handler reads the attributes, and the forms carry the wording
        // the dead copies used to (pointlessly) hardcode.
        $this->assertStringContainsString('form.dataset.rejectTitle', $html);
        $this->assertStringContainsString('form.dataset.rejectText', $html);
        $this->assertStringContainsString(
            'data-reject-title="Bạn có chắc chắn muốn từ chối cảnh báo này?"',
            $html
        );
        $this->assertStringContainsString(
            'data-reject-text="Hành động này sẽ từ chối cảnh báo và không thể hoàn tác!"',
            $html
        );
    }

    public function test_pages_without_the_attributes_keep_the_generic_wording(): void
    {
        // Control: experiences/admin_index and the dashboard reject forms
        // carry no data attributes; the layout handler must still render its
        // original default strings for them (|| fallback).
        $admin = User::factory()->admin()->create();
        $html = $this->actingAs($admin)
            ->get('/admin/experiences')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString("form.dataset.rejectTitle || 'Bạn có chắc chắn muốn từ chối bài này?'", $html);
        $this->assertSame(1, substr_count($html, "querySelectorAll('form.form-reject')"));
    }
}
