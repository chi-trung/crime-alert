<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use App\Models\WantedPerson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #343: four UI defects that lied to the user or drifted from a sibling
 * page, pinned together because each is a one-line view fix with no logic
 * change — and because three of the four only fail silently, no exception,
 * so nothing else would ever catch them.
 */
class UiConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_no_longer_advertises_social_login(): void
    {
        // The "HOẶC ĐĂNG NHẬP VỚI" divider was followed by an empty block —
        // no provider is wired. It promised a button that never rendered.
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringNotContainsString('HOẶC ĐĂNG NHẬP VỚI', $html);
        $this->assertStringNotContainsString('social-btn', $html);
    }

    public function test_login_and_register_declare_the_app_locale_not_hardcoded_vi(): void
    {
        // layouts/app and layouts/guest emit the dynamic tag; login/register
        // hardcoded <html lang="vi">, so a non-Vietnamese locale rendered a
        // page whose declared language contradicted its content. The app's
        // own locale IS vi, so the rendered value alone could not tell the
        // fixed version from the hardcoded one — swap the locale in config
        // and require the tag to follow it.
        foreach (['/login', '/register'] as $uri) {
            \App::setLocale('vi');
            $vi = $this->get($uri)->assertOk()->getContent();
            $this->assertStringContainsString('<html lang="vi">', $vi);

            \App::setLocale('en');
            $en = $this->get($uri)->assertOk()->getContent();
            $this->assertStringContainsString('<html lang="en">', $en);

            // Restore: every other test in the suite relies on vi.
            \App::setLocale('vi');
        }
    }

    public function test_fraud_alerts_route_is_gone(): void
    {
        // The "coming soon" placeholder was reachable by URL but linked from
        // nothing — nav, views, JS all silent. Round-29 dead-code doctrine:
        // an unreachable route+view pair is dead weight, not a feature.
        $this->get('/fraud-alerts')->assertNotFound();

        $this->assertNull(\Route::getRoutes()->getByName('fraud_alerts.index'));
    }

    public function test_alert_edit_page_advertises_the_same_2mb_as_create(): void
    {
        // #211 fixed the create page's wrong "5MB" copy; the edit form kept no
        // notice at all. Same validator rule, same rejection, no warning — so
        // replacing an image there failed with no prior hint. The number is
        // pinned to the rule itself by AlertUploadCopyTest; this pins that
        // BOTH forms carry it.
        $user = User::factory()->create();
        $alert = Alert::create([
            'user_id' => $user->id,
            'title' => 'T',
            'description' => 'd',
            'status' => 'approved',
        ]);

        $html = $this->actingAs($user)
            ->get('/alerts/'.$alert->id.'/edit')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('tối đa 2MB', $html);
    }

    public function test_wanted_list_header_describes_the_columns_it_renders(): void
    {
        // The intro promised "có ảnh, mô tả, mức độ nguy hiểm, khen thưởng" —
        // wanted_people has no image, danger-level or reward column at all
        // (verified live on both dialects), and the table renders 7 fields,
        // none of them those four. Verified against the rendered header so a
        // future column addition still has to describe the real table.
        WantedPerson::create([
            'name' => 'Nguyễn Văn A',
            'birth_year' => '1980',
            'address' => 'Hà Nội',
            'parents' => 'Nguyễn Văn B',
            'crime' => 'Lừa đảo',
            'decision' => 'QĐ 01',
            'agency' => 'CQĐT',
        ]);

        $html = $this->get('/wanted-list')->assertOk()->getContent();

        foreach (['có ảnh', 'mức độ nguy hiểm', 'khen thưởng'] as $promise) {
            $this->assertStringNotContainsString($promise, $html);
        }
        // The seven headers the table actually renders.
        foreach (['Họ tên', 'Năm sinh', 'Nơi ĐKTT', 'Họ tên bố/mẹ', 'Tội danh', 'Số QĐ'] as $real) {
            $this->assertStringContainsString($real, $html);
        }
    }

    public function test_wanted_list_renders_the_table_for_both_search_and_browse(): void
    {
        // The table markup was fully duplicated between the filled-q and
        // empty-q branches; the merge must keep both paths rendering — and
        // must not regress the empty-search notice, which is the only part
        // that legitimately differs.
        $person = WantedPerson::create([
            'name' => 'Trần Thị B',
            'birth_year' => '1990',
            'address' => 'Huế',
            'parents' => 'Trần Văn C',
            'crime' => 'Cướp giật',
            'decision' => 'QĐ 02',
            'agency' => 'CQĐT',
        ]);

        // Browse: no q.
        $browse = $this->get('/wanted-list')->assertOk()->getContent();
        $this->assertStringContainsString('Trần Thị B', $browse);

        // Search that hits.
        $hit = $this->get('/wanted-list?q=Trần')->assertOk()->getContent();
        $this->assertStringContainsString('Trần Thị B', $hit);
        $this->assertStringNotContainsString('Không tìm thấy đối tượng phù hợp.', $hit);

        // Search that misses: notice shows, table does not.
        $miss = $this->get('/wanted-list?q=khongtontai')->assertOk()->getContent();
        $this->assertStringContainsString('Không tìm thấy đối tượng phù hợp.', $miss);
        $this->assertStringNotContainsString('Trần Thị B', $miss);

        // The LIKE-wildcard shapes #51 fixed still work post-merge.
        $this->assertSame(1, WantedPerson::count());
        unset($person);
    }
}
