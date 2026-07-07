<?php

namespace App\Http\Controllers;

use App\Models\AISetting;
use App\Models\ChatHistory;
use App\Services\AI\AIServiceFactory;
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

            // Save AI response
            ChatHistory::create([
                'user_id' => Auth::id(),
                'session_id' => $request->session_id,
                'role' => 'assistant',
                'content' => $response,
            ]);

            return response()->json([
                'response' => $response,
                'action' => $classification,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'AI Error: ' . $e->getMessage()], 500);
        }
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
