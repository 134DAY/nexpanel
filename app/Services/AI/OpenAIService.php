<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

class OpenAIService implements AIServiceInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gpt-4o-mini'
    ) {}

    public function chat(array $messages, string $systemPrompt): string
    {
        $formatted = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($messages as $msg) {
            $formatted[] = [
                'role' => $msg['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => $msg['content'],
            ];
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $this->model,
            'messages' => $formatted,
            'max_tokens' => 2048,
            'temperature' => 0.7,
        ]);

        if ($response->failed()) {
            $error = $response->json('error.message', 'Unknown error');
            throw new \RuntimeException("OpenAI API error: {$error}");
        }

        return $response->json('choices.0.message.content', 'No response received.');
    }

    public function getProviderName(): string
    {
        return 'GPT';
    }
}
