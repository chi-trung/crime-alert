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
        // Issue #190: #129's email-verification invariant missed this
        // endpoint because the chatbot notifies nobody — but unlike the
        // notification-spam class it gates, every accepted request spends
        // the OPERATOR's paid provider key. Registration auto-logs-in with
        // an unverified (fake) email, so unverified throwaway accounts could
        // make up to 20 billable completions per minute each, across
        // unlimited accounts; the #33 throttle bounds volume per account,
        // never account count. The gate mirrors the six sibling action
        // endpoints (AlertController::store:35, CommentController::store:19,
        // LikeController::store:68, ...) and every other response here is
        // JSON, so the 403 is JSON unconditionally.
        if (! $request->user()->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn cần xác thực email để dùng trợ lý AI.',
            ], 403);
        }

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

        // Issue #321: a key pasted from a Windows/PDF file can carry CR/LF
        // (and other control bytes) around the real value. On the header
        // path askChatCompletions concatenates it into 'Authorization:
        // Bearer <key>', and Guzzle validates header VALUES at request-build
        // time via PSR-7 assertValue: a control byte throws
        // InvalidArgumentException whose message EMBEDS the offending value —
        // the raw key. That exception is NOT a ConnectionException, so it
        // escapes the #135 catch, reaches the framework (HTTP 500 into the
        // widget, breaking the #135/#190/#208/#223 always-a-string guarantee)
        // AND the framework logs the exception message, writing the key into
        // laravel.log (violating the #32 keys-never-to-logs mandate). The
        // Gemini twin is immune only because its key rides the URL, where the
        // control byte surfaces as a ConnectionException the #135 catch
        // already handles.
        //
        // Sanitize ONCE here so both providers get a clean key. Keep only
        // printable ASCII (\x21-\x7E) — a strict subset of PSR-7's accepted
        // set [\x20\x09\x21-\x7E\x80-\xFF], so a real key (alphanumerics,
        // '-', '_') is byte-identical and a sanitized value can NEVER
        // re-trigger the throw. Stripping the surrounding CRLF also silently
        // REPAIRS the common copy-paste case: the provider then receives the
        // correct key instead of failing. A key that is ONLY control bytes
        // collapses to '', which the empty() gate below reports as the
        // honest "not configured" outcome — never a bare 'Bearer '.
        $settings['key'] = preg_replace('/[^\x21-\x7E]/', '', (string) ($settings['key'] ?? ''));

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

        // Issue #329: the key used to ride the URL as ?key=<secret>, so
        // EVERY transport failure (DNS, refused, timeout, TLS mismatch,
        // proxy) carried it inside the exception message — verified by
        // probe: a ConnectionException against the real endpoint embeds the
        // full URL, key included. The #135 catch below stayed safe only by
        // deliberately NOT logging getMessage(); one refactor adding that
        // single line (as askChatCompletions does at its own catch) would
        // have written the key into laravel.log, breaking the #32 mandate
        // by habit rather than by structure. Google accepts the same key in
        // the x-goog-api-key header, so move it there: the URL is clean, and
        // the exception message, effectiveUri(), and any error page that
        // echoes the request URL are all key-free. askChatCompletions
        // already used the header path — both providers now share one shape.
        $url = $settings['endpoint'];

        // Issue #135: an unreachable host (DNS failure, refused connection,
        // cURL timeout) throws ConnectionException BEFORE any $response
        // exists — the !successful() branch below only sees HTTP-level
        // failures. Without this catch the widget got a 500 HTML page to
        // JSON-parse, defeating fallbackMessage's exact "cannot connect"
        // purpose. Same shape as CrawlerTest's host-unreachable case.
        try {
            $response = Http::timeout(30)
                ->withHeaders(['x-goog-api-key' => $settings['key']])
                ->post($url, [
                    'contents' => [
                        ['parts' => [['text' => $this->systemPrompt()."\n\nCâu hỏi: {$question}"]]],
                    ],
                ]);
        } catch (ConnectionException $e) {
            // Issue #329: with the key out of the URL this message is
            // key-free and can finally be logged like askChatCompletions'
            // own catch — the "deliberately not logged" coupling is gone.
            Log::error('Chatbot: Gemini connection error', [
                'message' => $e->getMessage(),
            ]);

            return $this->fallbackMessage();
        }

        if (! $response->successful()) {
            Log::error('Chatbot Gemini API error', [
                'status' => $response->status(),
                'body' => mb_substr((string) $response->body(), 0, 500),
            ]);

            return $this->fallbackMessage();
        }

        // Issue #223: ?? only guards null. OpenRouter's own OpenAPI spec
        // types message.content as string | ChatContentItems[] | null, so a
        // LEGITIMATE 200 can carry the multimodal parts-array here; the
        // array passed ?? and killed this `: string` method with an
        // uncaught TypeError -> HTTP 500 into the widget, breaking the
        // #135/#190/#208 guarantee that /chatbot/ask always answers with a
        // usable string. Objects crash identically; numbers are coerced
        // harmlessly (no strict_types), and the null paths still hit ??.
        $text = $response->json('candidates.0.content.parts.0.text');

        return is_string($text) ? $text : $this->fallbackMessage();
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

        // Issue #223: the OpenAI-compatible twin of the Gemini guard above —
        // documented OpenRouter content-parts arrays (and any provider
        // returning an object where a string is expected) must take the
        // fallback path, not a TypeError 500.
        $text = $response->json('choices.0.message.content');

        return is_string($text) ? $text : $this->fallbackMessage();
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
