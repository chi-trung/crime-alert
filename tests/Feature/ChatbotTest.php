<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ChatbotTest extends TestCase
{
    use RefreshDatabase;

    private function configuredOpenRouter(): void
    {
        config([
            'services.ai.provider' => 'openrouter',
            'services.ai.providers.openrouter.key' => 'test-key',
        ]);
    }

    public function test_guest_cannot_use_the_chatbot(): void
    {
        $this->postJson('/chatbot/ask', ['question' => 'Xin chao'])->assertUnauthorized();
    }

    public function test_question_is_required(): void
    {
        $this->configuredOpenRouter();

        $this->actingAs(User::factory()->create())
            ->postJson('/chatbot/ask', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['question']);
    }

    public function test_it_returns_generic_message_when_no_key_configured(): void
    {
        config([
            'services.ai.provider' => 'openrouter',
            'services.ai.providers.openrouter.key' => null,
        ]);

        Http::fake();

        $this->actingAs(User::factory()->create())
            ->postJson('/chatbot/ask', ['question' => 'Xin chao'])
            ->assertOk()
            ->assertJsonStructure(['answer']);

        Http::assertNothingSent();
    }

    public function test_it_calls_the_configured_provider_and_returns_the_answer(): void
    {
        $this->configuredOpenRouter();

        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [['message' => ['content' => 'Xin chao!']]],
            ], 200),
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson('/chatbot/ask', ['question' => 'Hello'])
            ->assertOk()
            ->assertJson(['answer' => 'Xin chao!']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'openrouter.ai')
                && $request['messages'][1]['content'] === 'Hello';
        });
    }

    public function test_provider_key_and_endpoint_are_paired_and_no_key_leaks_to_client(): void
    {
        // Regression: keys used to be hardcoded and crossed between providers
        // (DeepSeek-format key sent to OpenAI endpoint and vice versa).
        $this->assertSame(
            'https://api.deepseek.com/v1/chat/completions',
            config('services.ai.providers.deepseek.endpoint')
        );
        $this->assertSame(
            'https://api.openai.com/v1/chat/completions',
            config('services.ai.providers.openai.endpoint')
        );

        config([
            'services.ai.provider' => 'deepseek',
            'services.ai.providers.deepseek.key' => 'ds-test-key',
        ]);

        Http::fake([
            'api.deepseek.com/*' => Http::response(['error' => 'insufficient credits'], 401),
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->postJson('/chatbot/ask', ['question' => 'Hello'])
            ->assertOk();

        $body = $response->json('answer');
        $this->assertIsString($body);
        // Upstream error bodies (which can echo request info) must not reach the client.
        $this->assertStringNotContainsString('insufficient credits', $body);
        $this->assertStringNotContainsString('ds-test-key', $response->getContent());
    }

    public function test_gemini_provider_uses_key_query_param(): void
    {
        config([
            'services.ai.provider' => 'gemini',
            'services.ai.providers.gemini.key' => 'gm-test-key',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Chao']]]]],
            ], 200),
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson('/chatbot/ask', ['question' => 'Hello'])
            ->assertOk()
            ->assertJson(['answer' => 'Chao']);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'generativelanguage.googleapis.com')
            && str_contains($request->url(), 'key=gm-test-key'));
    }

    public function test_chatbot_is_throttled_at_twenty_requests_per_minute(): void
    {
        $this->configuredOpenRouter();

        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [['message' => ['content' => 'Ok']]],
            ], 200),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        // Route middleware is throttle:20,1 — the 21st call within the window
        // must be rate-limited rather than forwarded upstream.
        for ($i = 1; $i <= 20; $i++) {
            $this->postJson('/chatbot/ask', ['question' => 'Hello'])->assertOk();
        }

        $this->postJson('/chatbot/ask', ['question' => 'Hello'])->assertStatus(429);
    }

    public function test_chat_completions_host_unreachable_answers_fallback_not_500(): void
    {
        // Issue #135: ConnectionException is thrown before any $response
        // exists, so the !successful() fallback branch never sees it — the
        // user got a 500 HTML page. Same host-unreachable shape as
        // CrawlerTest's crawl:news coverage.
        $this->configuredOpenRouter();

        Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect'));

        $this->actingAs(User::factory()->create())
            ->postJson('/chatbot/ask', ['question' => 'Hello'])
            ->assertOk()
            ->assertJson(['answer' => 'Xin lỗi, hiện tôi không thể kết nối tới trợ lý AI. Vui lòng thử lại sau.']);
    }

    public function test_gemini_host_unreachable_answers_fallback_without_leaking_key(): void
    {
        config([
            'services.ai.provider' => 'gemini',
            'services.ai.providers.gemini.key' => 'gm-test-key',
        ]);

        Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect to https://generativelanguage.googleapis.com/v1beta?key=gm-test-key'));
        $logged = [];
        Log::shouldReceive('error')->andReturnUsing(function ($message, $context = []) use (&$logged): void {
            $logged[] = $message.' '.json_encode($context);
        });

        $response = $this->actingAs(User::factory()->create())
            ->postJson('/chatbot/ask', ['question' => 'Hello'])
            ->assertOk();

        // The Gemini key rides the URL, which Guzzle embeds in connection
        // error text — it must appear neither in the client body nor the log.
        $this->assertStringNotContainsString('gm-test-key', $response->getContent());
        $this->assertNotEmpty($logged, 'the connection failure must still be logged');
        foreach ($logged as $line) {
            $this->assertStringNotContainsString('gm-test-key', $line);
        }
    }
}
