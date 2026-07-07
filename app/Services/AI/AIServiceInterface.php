<?php

namespace App\Services\AI;

interface AIServiceInterface
{
    /**
     * Send a chat message and get a response.
     *
     * @param array $messages  Chat history [['role' => 'user|assistant', 'content' => '...']]
     * @param string $systemPrompt  System prompt with server context
     * @return string  AI response text
     */
    public function chat(array $messages, string $systemPrompt): string;

    /**
     * Get the provider name.
     */
    public function getProviderName(): string;
}
