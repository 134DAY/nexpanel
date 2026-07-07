<?php

namespace App\Services\AI;

use App\Models\AISetting;
use Illuminate\Support\Facades\Crypt;

class AIServiceFactory
{
    public static function make(AISetting $setting): AIServiceInterface
    {
        $apiKey = Crypt::decryptString($setting->api_key);
        $model = $setting->model ?? '';

        return match ($setting->provider) {
            'claude' => new ClaudeService($apiKey, $model ?: 'claude-sonnet-4-20250514'),
            'gemini' => new GeminiService($apiKey, $model ?: 'gemini-2.0-flash'),
            'openai' => new OpenAIService($apiKey, $model ?: 'gpt-4o-mini'),
            'groq'   => new GroqService($apiKey, $model ?: 'llama-3.3-70b-versatile'),
            default  => throw new \Exception("Unsupported AI provider: {$setting->provider}"),
        };
    }
}
