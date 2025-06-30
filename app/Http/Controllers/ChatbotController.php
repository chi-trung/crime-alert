<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChatbotController extends Controller
{
    public function askGemini(Request $request)
    {
        $question = $request->input('question');
        $apiKey = env('GEMINI_API_KEY', 'AIzaSyBYy6vxDfWZ0q1VwqBKOwv2pUAye3GU25g');

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $apiKey, [
            'contents' => [
                ['parts' => [['text' => $question]]]
            ]
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $answer = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Xin lỗi, tôi chưa có câu trả lời.';
            return response()->json(['answer' => $answer]);
        } else {
            return response()->json(['answer' => 'Lỗi khi kết nối Gemini API.'], 500);
        }
    }

    public function askOpenAI(\Illuminate\Http\Request $request)
    {
        $question = $request->input('question');
        $apiKey = 'sk-c8b099407d9f470d995910de0558d30c';

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => 'Bạn là trợ lý AI hỗ trợ về an ninh, cảnh báo, hướng dẫn cộng đồng.'],
                ['role' => 'user', 'content' => $question],
            ],
            'max_tokens' => 512,
            'temperature' => 0.7,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $answer = $data['choices'][0]['message']['content'] ?? 'Xin lỗi, tôi chưa có câu trả lời.';
            return response()->json(['answer' => $answer]);
        } else {
            return response()->json(['answer' => 'Lỗi khi kết nối OpenAI API.'], 500);
        }
    }

    public function askDeepSeek(\Illuminate\Http\Request $request)
    {
        $question = $request->input('question');
        $apiKey = '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';

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.deepseek.com/v1/chat/completions', [
            'model' => 'deepseek-chat',
            'messages' => [
                ['role' => 'system', 'content' => 'Bạn là trợ lý AI hỗ trợ về an ninh, cảnh báo, hướng dẫn cộng đồng.'],
                ['role' => 'user', 'content' => $question],
            ],
            'max_tokens' => 512,
            'temperature' => 0.7,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $answer = $data['choices'][0]['message']['content'] ?? 'Xin lỗi, tôi chưa có câu trả lời.';
            return response()->json(['answer' => $answer]);
        } else {
            return response()->json([
                'answer' => 'Lỗi khi kết nối DeepSeek API: ' . $response->body()
            ], 500);
        }
    }

    public function askOpenRouter(\Illuminate\Http\Request $request)
    {
        $question = $request->input('question');
        $apiKey = env('OPENROUTER_API_KEY');

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
            'HTTP-Referer' => 'http://127.0.0.1:8000',
            'X-Title' => 'crime-alerts',
        ])->post('https://openrouter.ai/api/v1/chat/completions', [
            'model' => 'agentica-org/deepcoder-14b-preview:free',
            'messages' => [
                ['role' => 'user', 'content' => $question],
            ],
            'max_tokens' => 512,
            'temperature' => 0.7,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            \Log::info('OpenRouter API response:', $data);
            $answer = $data['choices'][0]['message']['content'] ?? json_encode($data);
            return response()->json(['answer' => $answer]);
        } else {
            return response()->json([
                'answer' => 'Lỗi khi kết nối OpenRouter API: ' . $response->body()
            ], 500);
        }
    }
} 