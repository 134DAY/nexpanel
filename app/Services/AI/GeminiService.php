<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

class GeminiService implements AIServiceInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gemini-2.0-flash'
    ) {}

    public function chat(array $messages, string $systemPrompt): string
    {
        $contents = [];

        foreach ($messages as $msg) {
            $contents[] = [
                'role' => $msg['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $msg['content']]],
            ];
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $response = Http::timeout(60)->post($url, [
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => $contents,
            'generationConfig' => [
                'maxOutputTokens' => 2048,
                'temperature' => 0.7,
            ],
        ]);

        if ($response->failed()) {
            $error = $response->json('error.message', 'Unknown error');
            throw new \RuntimeException("Gemini API error: {$error}");
        }

        return $response->json('candidates.0.content.parts.0.text', 'No response received.');
    }

    public function getProviderName(): string
    {
        return 'Gemini';
    }
}
