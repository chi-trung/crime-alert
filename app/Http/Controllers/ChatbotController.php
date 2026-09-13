<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    /**
     * Answer a user question using the configured AI provider.
     *
     * Provider (and therefore API key + endpoint + model pairing) is chosen
     * via CHATBOT_PROVIDER in .env; each provider's key lives under
     * services.ai.<provider> in config/services.php. Keys are NEVER
     * hardcoded here.
     */
    public function ask(Request $request)
    {
        $validated = $request->validate([
            'question' => 'required|string|max:2000',
        ]);

        $question = $validated['question'];

        $provider = config('services.ai.provider', 'openrouter');
        $settings = config("services.ai.providers.$provider");

        if (! is_array($settings)) {
            Log::error("Chatbot: unknown provider '{$provider}'.");

            return response()->json([
                'answer' => 'Trợ lý AI hiện không khả dụng. Vui lòng thử lại sau.',
            ]);
        }

        if (empty($settings['key'])) {
            Log::warning("Chatbot: provider '{$provider}' has no API key configured.");

            return response()->json([
                'answer' => 'Trợ lý AI hiện chưa được cấu hình. Vui lòng thử lại sau.',
            ]);
        }

        $answer = match ($provider) {
            'gemini' => $this->askGemini($settings, $question),
            default => $this->askChatCompletions($settings, $question, $provider),
        };

        return response()->json(['answer' => $answer]);
    }

    /**
     * Google Gemini (generateContent) provider.
     */
    private function askGemini(array $settings, string $question): string
    {
        $url = $settings['endpoint'].'?key='.$settings['key'];

        // Issue #135: an unreachable host (DNS failure, refused connection,
        // cURL timeout) throws ConnectionException BEFORE any $response
        // exists — the !successful() branch below only sees HTTP-level
        // failures. Without this catch the widget got a 500 HTML page to
        // JSON-parse, defeating fallbackMessage's exact "cannot connect"
        // purpose. Same shape as CrawlerTest's host-unreachable case.
        try {
            $response = Http::timeout(30)->post($url, [
                'contents' => [
                    ['parts' => [['text' => $this->systemPrompt()."\n\nCâu hỏi: {$question}"]]],
                ],
            ]);
        } catch (ConnectionException $e) {
            // getMessage() is deliberately NOT logged here: Guzzle's
            // connection-error text embeds the request URL, and the Gemini
            // URL carries the API key as a query param (issue #32 pairing —
            // keys stay out of logs).
            Log::error('Chatbot: Gemini connection error ('.get_class($e).')');

            return $this->fallbackMessage();
        }

        if (! $response->successful()) {
            Log::error('Chatbot Gemini API error', [
                'status' => $response->status(),
                'body' => mb_substr((string) $response->body(), 0, 500),
            ]);

            return $this->fallbackMessage();
        }

        return $response->json('candidates.0.content.parts.0.text') ?? $this->fallbackMessage();
    }

    /**
     * OpenAI-compatible chat completions (OpenAI, DeepSeek, OpenRouter).
     */
    private function askChatCompletions(array $settings, string $question, string $provider): string
    {
        $headers = [
            'Authorization' => 'Bearer '.$settings['key'],
            'Content-Type' => 'application/json',
        ];

        // OpenRouter's attribution headers (optional, non-secret).
        if (! empty($settings['referer'])) {
            $headers['HTTP-Referer'] = $settings['referer'];
            $headers['X-Title'] = config('app.name', 'Crime Alert');
        }

        // Issue #135: same unguarded-Http shape as askGemini — the key rides
        // in a header, so Guzzle's connection-error text (which embeds the
        // URL) is safe to log, unlike Gemini's query-param key.
        try {
            $response = Http::timeout(30)
                ->withHeaders($headers)
                ->post($settings['endpoint'], [
                    'model' => $settings['model'],
                    'messages' => [
                        ['role' => 'system', 'content' => $this->systemPrompt()],
                        ['role' => 'user', 'content' => $question],
                    ],
                    'max_tokens' => 512,
                    'temperature' => 0.7,
                ]);
        } catch (ConnectionException $e) {
            Log::error('Chatbot connection error', [
                'provider' => $provider,
                'message' => $e->getMessage(),
            ]);

            return $this->fallbackMessage();
        }

        if (! $response->successful()) {
            Log::error('Chatbot API error', [
                'provider' => $provider,
                'status' => $response->status(),
                'body' => mb_substr((string) $response->body(), 0, 500),
            ]);

            return $this->fallbackMessage();
        }

        return $response->json('choices.0.message.content') ?? $this->fallbackMessage();
    }

    private function systemPrompt(): string
    {
        return 'Bạn là trợ lý AI hỗ trợ về an ninh, cảnh báo tội phạm và hướng dẫn cộng đồng. Trả lời ngắn gọn, bằng tiếng Việt.';
    }

    private function fallbackMessage(): string
    {
        return 'Xin lỗi, hiện tôi không thể kết nối tới trợ lý AI. Vui lòng thử lại sau.';
    }
}
