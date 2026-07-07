<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

class GroqService implements AIServiceInterface
{
    public function __construct(
        private string $apiKey,
        private string $model = 'llama-3.3-70b-versatile'
    ) {}

    public function chat(array $messages, string $systemPrompt): string
    {
        $payload = [
            'model' => $this->model,
            'messages' => array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $messages
            ),
            'max_tokens' => 2048,
            'temperature' => 0.7,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post('https://api.groq.com/openai/v1/chat/completions', $payload);

        if ($response->failed()) {
            $error = $response->json('error.message', 'Unknown error');
            throw new \Exception("Groq API error: {$error}");
        }

        return $response->json('choices.0.message.content', 'No response from Groq');
    }

    public function getProviderName(): string
    {
        return 'Groq';
    }
}
