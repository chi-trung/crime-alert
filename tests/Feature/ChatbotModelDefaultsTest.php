<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Issue #166: the chatbot's stock defaults were dead models. The OpenRouter
 * default was agentica-org/deepcoder-14b-preview:free — delisted (the live
 * GET /api/v1/models re-check at fix time: 445 ids, zero agentica/deepcoder
 * matches) — and the Gemini model was FROZEN inside the endpoint URL as
 * 'gemini-pro', retired by Google, with no *_MODEL env to override it like
 * the openai/deepseek/openrouter siblings have. These pins:
 *   1. the shipped defaults are catalog-live slugs (and the retired
 *      'gemini-pro' substring is gone from the endpoint);
 *   2. the Gemini endpoint string tracks the model knob — the controller
 *      posts to whatever providers.gemini.endpoint holds (a probe URL
 *      proves it is no longer a hardcoded literal), and the model is now
 *      exposed as config for parity with the other three providers.
 * Env-level override is deliberately NOT tested: env() reads through
 * Illuminate\Support\Env's process-wide repository, which poisons across
 * test order; the *_MODEL wiring is the identical framework mechanism the
 * sibling providers (OPENAI_MODEL/DEEPSEEK_MODEL, likewise untested at env
 * level) already use.
 */
class ChatbotModelDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_openrouter_default_model_is_a_live_catalog_slug(): void
    {
        // Pre-fix this was 'agentica-org/deepcoder-14b-preview:free' — zero
        // matches in the live 445-model catalog, so a stock .env.example
        // deploy 4xx'd every question and fell back to the canned reply.
        $this->assertStringNotContainsString('agentica', (string) config('services.ai.providers.openrouter.model'));
        $this->assertStringNotContainsString('deepcoder', (string) config('services.ai.providers.openrouter.model'));
        $this->assertStringEndsWith(':free', (string) config('services.ai.providers.openrouter.model'));
    }

    public function test_gemini_default_endpoint_no_longer_pins_retired_gemini_pro(): void
    {
        $endpoint = (string) config('services.ai.providers.gemini.endpoint');
        // 'gemini-pro' (Gemini 1.0 Pro) is retired — Google changelog
        // 2025-02-18; the string surviving here is the whole bug.
        $this->assertStringNotContainsString('gemini-pro', $endpoint);
        $this->assertStringContainsString(':generateContent', $endpoint);
        $this->assertStringContainsString('/v1beta/models/', $endpoint);
    }

    public function test_gemini_model_is_exposed_as_config_like_the_siblings(): void
    {
        // The asymmetry half of #166: openai/deepseek/openrouter all had a
        // *_MODEL config entry; gemini's model existed only inside the URL.
        // It now has its own key, derived from the same GEMINI_MODEL knob
        // that builds the endpoint.
        $model = config('services.ai.providers.gemini.model');
        $this->assertIsString($model);
        $this->assertNotSame('', $model);
        $this->assertStringContainsString('/models/'.$model.':generateContent', (string) config('services.ai.providers.gemini.endpoint'));
    }

    public function test_gemini_provider_posts_to_the_endpoint_config_value(): void
    {
        config([
            'services.ai.provider' => 'gemini',
            'services.ai.providers.gemini.key' => 'gm-test-key',
            'services.ai.providers.gemini.endpoint' => 'https://probe.invalid/v1beta/models/probe-model:generateContent',
        ]);

        Http::fake([
            'probe.invalid/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Ok']]]]],
            ], 200),
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson('/chatbot/ask', ['question' => 'Hello'])
            ->assertOk()
            ->assertJson(['answer' => 'Ok']);

        // Pre-fix the controller's URL came from the frozen services.php
        // literal and a config override changed nothing; now the config is
        // the single source of truth (the probe host answering at all is
        // what the fake's URL pattern pins).
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://probe.invalid/v1beta/models/probe-model:generateContent'));
    }

    public function test_openrouter_default_slug_still_listed_in_the_live_catalog(): void
    {
        // Guards the one thing a fixed default cannot guarantee: the catalog
        // moves. Skips when offline (CI self-hosted has no guaranteed egress).
        $payload = @file_get_contents('https://openrouter.ai/api/v1/models', false, stream_context_create(['http' => ['timeout' => 10]]));
        if ($payload === false) {
            $this->markTestSkipped('OpenRouter catalog unreachable from this runner.');
        }
        $catalog = json_decode($payload, true);
        $ids = array_column($catalog['data'] ?? [], 'id');
        // A non-array body means a proxy error page, not the catalog.
        if ($ids === []) {
            $this->markTestSkipped('OpenRouter catalog response was not usable JSON.');
        }
        $this->assertContains((string) config('services.ai.providers.openrouter.model'), $ids, 'the shipped default must exist in the live OpenRouter model list');
    }
}
