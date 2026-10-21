<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Services\AiAssistantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/** Floating "Ask" chat (permission: use ai assistant) and its settings (permission: manage settings). */
class AiAssistantController extends Controller
{
    public function __construct(protected AiAssistantService $ai) {}

    public function chat(Request $request)
    {
        $data = $request->validate(['question' => 'required|string|max:1000']);
        $cfg = $this->ai->config();
        if (!$cfg['enabled']) {
            return response()->json(['error' => 'The AI assistant is switched off in Settings.'], 422);
        }
        $countKey = 'ai_count_' . auth()->id() . '_' . today()->toDateString();
        if (Cache::get($countKey, 0) >= $cfg['limit']) {
            return response()->json(['error' => "You've reached today's limit of {$cfg['limit']} questions."], 429);
        }

        $history = session('ai_chat', []);
        try {
            $answer = $this->ai->ask(auth()->user(), $data['question'], $history);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
        Cache::put($countKey, Cache::get($countKey, 0) + 1, now()->endOfDay());

        // Keep the plain turns (member numbers, no names) for follow-up questions
        $history[] = ['role' => 'user', 'content' => $data['question']];
        $history[] = ['role' => 'assistant', 'content' => $answer['text']];
        session(['ai_chat' => array_slice($history, -12)]);
        AuditLog::record('ai_question', 'Asked the AI assistant: ' . mb_strimwidth($data['question'], 0, 200, '…'), 'ai');

        return response()->json(['html' => $answer['html']]);
    }

    public function reset()
    {
        session()->forget('ai_chat');
        return response()->json(['ok' => true]);
    }

    public function settings()
    {
        $cfg = $this->ai->config();
        return view('settings.ai', [
            'cfg' => $cfg, 'presets' => AiAssistantService::PRESETS,
            'provider' => SystemSetting::get('ai_provider', 'groq'), 'hasKey' => $cfg['key'] !== '',
        ]);
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate([
            'ai_enabled'     => 'nullable|boolean',
            'ai_provider'    => 'required|in:' . implode(',', array_keys(AiAssistantService::PRESETS)),
            'ai_base_url'    => 'required|url|max:255',
            'ai_model'       => 'required|string|max:120',
            'ai_api_key'     => 'nullable|string|max:400',
            'ai_daily_limit' => 'required|integer|min:1|max:1000',
        ]);
        SystemSetting::set('ai_enabled', $request->boolean('ai_enabled') ? 1 : 0);
        SystemSetting::set('ai_provider', $data['ai_provider']);
        SystemSetting::set('ai_base_url', rtrim($data['ai_base_url'], '/'));
        SystemSetting::set('ai_model', trim($data['ai_model']));
        SystemSetting::set('ai_daily_limit', $data['ai_daily_limit']);
        if (!empty($data['ai_api_key'])) {
            SystemSetting::set('ai_api_key', Crypt::encryptString(trim($data['ai_api_key'])));   // stored encrypted, never shown again
        }
        AuditLog::record('ai_settings', "AI assistant settings saved ({$data['ai_provider']}, {$data['ai_model']}, " . ($request->boolean('ai_enabled') ? 'on' : 'off') . ')', 'settings');

        return back()->with('success', 'AI assistant settings saved.');
    }

    public function test()
    {
        try {
            $reply = $this->ai->test();
        } catch (\RuntimeException $e) {
            return back()->with('error', 'Test failed: ' . $e->getMessage());
        }
        return back()->with('success', 'Connected — the model replied: "' . mb_strimwidth($reply, 0, 80, '…') . '"');
    }
}
