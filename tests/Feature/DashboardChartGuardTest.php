<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #372: two re-entry guards in public/js/dashboard.js read each chart
 * instance one line before the handler assigned it for the first and only
 * time. DOMContentLoaded fires once per page load and nothing re-enters the
 * block, so the guards were provably null and destroy() could never fire.
 *
 * Dead JS is not falsifiable from a rendered page, so the tests pin the shape
 * of the source: the guards are gone, the assignments they guarded survive,
 * and the canvases the script targets still render on the dashboard.
 */
class DashboardChartGuardTest extends TestCase
{
    use RefreshDatabase;

    private function script(): string
    {
        $script = file_get_contents(public_path('js/dashboard.js'));
        $this->assertNotEmpty($script, 'dashboard.js must exist and be non-empty');

        return $script;
    }

    private function withoutComments(string $code): string
    {
        // Strip // line comments so an explanatory note that quotes the removed
        // call cannot satisfy the negative assertions.
        return preg_replace('/^[ \t]*\/\/.*$/m', '', $code);
    }

    public function test_the_destroy_guards_are_gone(): void
    {
        $js = $this->withoutComments($this->script());

        $this->assertStringNotContainsString(
            'alertsChartInstance.destroy()',
            $js,
            'the unreachable bar-chart guard must be gone'
        );
        $this->assertStringNotContainsString(
            'alertsPieChartInstance.destroy()',
            $js,
            'the unreachable donut-chart guard must be gone'
        );
    }

    public function test_the_charts_are_still_created(): void
    {
        // Positive control: removing the guards must not take the chart
        // construction with them — that is what made the guards read dead.
        $js = $this->withoutComments($this->script());

        $this->assertStringContainsString(
            'alertsChartInstance = new Chart(ctx,',
            $js,
            'the bar chart must still be constructed'
        );
        $this->assertStringContainsString(
            'alertsPieChartInstance = new Chart(pieCtx,',
            $js,
            'the donut chart must still be constructed'
        );
        // The canvases the constructors target.
        $this->assertStringContainsString("getElementById('alertsChart')", $js);
        $this->assertStringContainsString("getElementById('alertsPieChart')", $js);
    }

    public function test_the_dashboards_data_is_still_published_to_the_script(): void
    {
        // The script only draws when these window keys are set. They ship
        // inside an admin-only block, so the assertions need an admin — and
        // Blade compiles @json() before the response ships, so they match the
        // rendered assignment rather than the directive.
        $user = tap(User::factory()->create(), fn ($u) => $u->forceFill([
            'email_verified_at' => now(),
            'isAdmin' => true,
        ])->save());

        $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('window.createdData =', $html);
        $this->assertStringContainsString('window.approvedData =', $html);
        $this->assertStringContainsString('window.typePercentsAdmin =', $html);
        $this->assertStringContainsString('js/dashboard.js', $html);
        $this->assertStringContainsString('id="alertsChart"', $html);
        $this->assertStringContainsString('id="alertsPieChart"', $html);
    }
}
