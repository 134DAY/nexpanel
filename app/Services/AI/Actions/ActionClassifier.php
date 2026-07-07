<?php

namespace App\Services\AI\Actions;

/**
 * Classifies AI responses into action types: analyze, advise, explain, execute
 * Currently supports: analyze, advise, explain (execute is Phase 3+)
 */
class ActionClassifier
{
    /**
     * Available action types and their keywords
     */
    private static array $patterns = [
        'analyze' => [
            'keywords' => ['analyze', 'วิเคราะห์', 'check', 'ตรวจสอบ', 'log', 'error log', 'status', 'สถานะ', 'monitor', 'usage', 'performance', 'ดู', 'อ่าน', 'scan', 'inspect', 'diagnose'],
            'description' => 'Read & analyze logs, metrics, and server state',
        ],
        'advise' => [
            'keywords' => ['optimize', 'แนะนำ', 'recommend', 'suggest', 'improve', 'best practice', 'ปรับปรุง', 'tune', 'ควร', 'แก้ไข', 'fix', 'solve', 'วิธี', 'ทำยังไง', 'how to', 'tips'],
            'description' => 'Suggest fixes, optimizations, and best practices',
        ],
        'explain' => [
            'keywords' => ['explain', 'อธิบาย', 'what is', 'คืออะไร', 'หมายความ', 'mean', 'why', 'ทำไม', 'how does', 'ทำงานยังไง', 'difference', 'ต่างกัน', 'concept', 'แนวคิด', 'คือ'],
            'description' => 'Explain errors, configs, and technical concepts',
        ],
        'execute' => [
            'keywords' => ['create', 'สร้าง', 'delete', 'ลบ', 'install', 'ติดตั้ง', 'restart', 'start', 'stop', 'enable', 'disable', 'backup', 'restore', 'add', 'เพิ่ม', 'remove', 'setup'],
            'description' => 'Execute server commands (requires confirmation)',
        ],
    ];

    /**
     * Classify user message into an action type
     */
    public static function classify(string $message): array
    {
        $message = mb_strtolower(trim($message));
        $scores = [];

        foreach (self::$patterns as $type => $config) {
            $score = 0;
            foreach ($config['keywords'] as $keyword) {
                if (str_contains($message, mb_strtolower($keyword))) {
                    $score += strlen($keyword); // longer keyword = more specific match
                }
            }
            $scores[$type] = $score;
        }

        // Get the best match
        arsort($scores);
        $bestType = array_key_first($scores);
        $bestScore = $scores[$bestType];

        // If no keywords matched, it's likely a general chat
        if ($bestScore === 0) {
            return [
                'type' => 'chat',
                'confidence' => 'low',
                'description' => 'General conversation',
                'requires_confirm' => false,
            ];
        }

        return [
            'type' => $bestType,
            'confidence' => $bestScore >= 10 ? 'high' : 'medium',
            'description' => self::$patterns[$bestType]['description'],
            'requires_confirm' => $bestType === 'execute',
        ];
    }

    /**
     * Get all supported action types
     */
    public static function getActionTypes(): array
    {
        return array_map(fn($type, $config) => [
            'type' => $type,
            'description' => $config['description'],
            'enabled' => $type !== 'execute', // Execute is not enabled yet
        ], array_keys(self::$patterns), array_values(self::$patterns));
    }
}
