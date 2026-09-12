<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
}
