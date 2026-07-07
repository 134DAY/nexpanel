<?php

namespace App\Http\Controllers;

use App\Models\AISetting;
use App\Models\ChatHistory;
use App\Services\AI\AIServiceFactory;
use App\Services\AI\AIExecutor;
use App\Services\AI\SystemPromptBuilder;
use App\Services\AI\Actions\ActionClassifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AIController extends Controller
{
    public function index()
    {
        $setting = AISetting::where('user_id', Auth::id())->first();
        $providerName = $setting ? ucfirst($setting->provider) : null;
        $modelName = $setting->model ?? null;

        return view('ai.index', compact('providerName', 'modelName'));
    }

    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:5000',
            'session_id' => 'required|string',
        ]);

        $setting = AISetting::where('user_id', Auth::id())->first();

        if (!$setting || !$setting->api_key) {
            return response()->json(['error' => 'Please configure your AI provider in Settings first.'], 400);
        }

        // Classify the user's message
        $classification = ActionClassifier::classify($request->message);

        // Save user message with action type
        ChatHistory::create([
            'user_id' => Auth::id(),
            'session_id' => $request->session_id,
            'role' => 'user',
            'content' => $request->message,
        ]);

        // Get conversation history for context
        $history = ChatHistory::where('user_id', Auth::id())
            ->where('session_id', $request->session_id)
            ->orderBy('created_at')
            ->get()
            ->map(fn($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        try {
            $service = AIServiceFactory::make($setting);

            // Build action-aware system prompt
            $systemPrompt = SystemPromptBuilder::build($classification['type']);
            $response = $service->chat($history, $systemPrompt);

            // Extract a proposed action (if the AI wants to DO something).
            [$cleanResponse, $proposedAction] = $this->parseAction($response);

            // Save the (cleaned) AI response.
            ChatHistory::create([
                'user_id' => Auth::id(),
                'session_id' => $request->session_id,
                'role' => 'assistant',
                'content' => $cleanResponse,
            ]);

            return response()->json([
                'response' => $cleanResponse,
                'action' => $classification,
                'proposedAction' => $proposedAction,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'AI Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Confirm-and-run an action the AI proposed. Called only after the user
     * clicks "Run" on the confirmation card.
     */
    public function execute(Request $request)
    {
        $data = $request->validate([
            'tool' => 'required|string',
            'args' => 'array',
            'session_id' => 'required|string',
        ]);
        $tool = $data['tool'];
        $args = $data['args'] ?? [];

        $assessment = AIExecutor::assess($tool, $args);
        if (! $assessment['allowed']) {
            return response()->json(['ok' => false, 'output' => $assessment['message'], 'level' => $assessment['level']], 403);
        }

        $result = AIExecutor::run($tool, $args);

        // Record the outcome in the conversation so it shows in history.
        ChatHistory::create([
            'user_id' => Auth::id(),
            'session_id' => $data['session_id'],
            'role' => 'assistant',
            'content' => ($result['ok'] ? '✅ ' : '❌ ') . AIExecutor::describe($tool, $args) . "\n\n```\n" . $result['output'] . "\n```",
        ]);

        return response()->json([
            'ok' => $result['ok'],
            'output' => $result['output'],
            'level' => $assessment['level'],
        ]);
    }

    /**
     * Pull a ```action {json}``` block out of the AI response.
     * Returns [cleaned_text, proposedAction|null].
     */
    private function parseAction(string $response): array
    {
        if (! preg_match('/```action\s*(.+?)```/s', $response, $m)) {
            return [$response, null];
        }
        $json = json_decode(trim($m[1]), true);
        $cleaned = trim(str_replace($m[0], '', $response));

        if (! is_array($json) || empty($json['tool'])) {
            return [$cleaned, null];
        }
        $tool = $json['tool'];
        $args = $json['args'] ?? [];
        $assessment = AIExecutor::assess($tool, $args);

        return [$cleaned, [
            'tool'    => $tool,
            'args'    => $args,
            'summary' => AIExecutor::describe($tool, $args),
            'level'   => $assessment['level'],
            'allowed' => $assessment['allowed'],
        ]];
    }

    public function newSession()
    {
        return response()->json(['session_id' => (string) Str::uuid()]);
    }

    public function history(Request $request)
    {
        $messages = ChatHistory::where('user_id', Auth::id())
            ->where('session_id', $request->session_id)
            ->orderBy('created_at')
            ->get(['role', 'content', 'created_at']);

        return response()->json($messages);
    }

    public function sessions()
    {
        $sessions = ChatHistory::where('user_id', Auth::id())
            ->selectRaw('session_id, MIN(content) as preview, MAX(created_at) as last_active, COUNT(*) as message_count')
            ->groupBy('session_id')
            ->orderByDesc('last_active')
            ->get();

        // Get first user message as preview
        $sessions = $sessions->map(function ($s) {
            $firstMsg = ChatHistory::where('user_id', Auth::id())
                ->where('session_id', $s->session_id)
                ->where('role', 'user')
                ->orderBy('created_at')
                ->first();

            $s->preview = $firstMsg
                ? Str::limit($firstMsg->content, 50)
                : 'New conversation';

            return $s;
        });

        return response()->json($sessions);
    }

    public function deleteSession(Request $request)
    {
        $request->validate(['session_id' => 'required|string']);

        ChatHistory::where('user_id', Auth::id())
            ->where('session_id', $request->session_id)
            ->delete();

        return response()->json(['success' => true]);
    }
}
