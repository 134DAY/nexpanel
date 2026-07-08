<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

class ClaudeService implements AIServiceInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-sonnet-4-6'
    ) {}

    public function chat(array $messages, string $systemPrompt): string
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->model,
            'max_tokens' => 2048,
            'system' => $systemPrompt,
            'messages' => $this->formatMessages($messages),
        ]);

        if ($response->failed()) {
            $error = $response->json('error.message', 'Unknown error');
            throw new \RuntimeException("Claude API error: {$error}");
        }

        return $response->json('content.0.text', 'No response received.');
    }

    public function getProviderName(): string
    {
        return 'Claude';
    }

    private function formatMessages(array $messages): array
    {
        return array_map(fn($m) => [
            'role' => $m['role'] === 'assistant' ? 'assistant' : 'user',
            'content' => $m['content'],
        ], $messages);
    }
}
