@extends('layouts.app')

@section('title', 'Settings')
@section('subheader', 'Manage your panel configuration')

@section('content')
<div x-data="settingsPage()" class="max-w-4xl mx-auto space-y-6">

    {{-- Success/Error Messages --}}
    @if(session('success'))
    <div class="flex items-center gap-3 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-sm">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div class="flex items-center gap-3 p-4 rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 text-red-700 dark:text-red-400 text-sm">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
        {{ $errors->first() }}
    </div>
    @endif

    {{-- Tab Navigation --}}
    <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 overflow-hidden">
        <div class="flex border-b border-slate-200 dark:border-slate-800/60">
            <a href="/settings?tab=ai"
               class="flex items-center gap-2 px-6 py-4 text-sm font-medium border-b-2 transition-colors {{ $tab === 'ai' ? 'border-cyan-500 text-cyan-600 dark:text-cyan-400 bg-cyan-50/50 dark:bg-cyan-500/5' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456z"/></svg>
                AI Provider
            </a>
            <a href="/settings?tab=panel"
               class="flex items-center gap-2 px-6 py-4 text-sm font-medium border-b-2 transition-colors {{ $tab === 'panel' ? 'border-cyan-500 text-cyan-600 dark:text-cyan-400 bg-cyan-50/50 dark:bg-cyan-500/5' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75"/></svg>
                Panel Settings
            </a>
            <a href="/settings?tab=security"
               class="flex items-center gap-2 px-6 py-4 text-sm font-medium border-b-2 transition-colors {{ $tab === 'security' ? 'border-cyan-500 text-cyan-600 dark:text-cyan-400 bg-cyan-50/50 dark:bg-cyan-500/5' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                Security
            </a>
        </div>

        <div class="p-6">

            {{-- ═══════ TAB: AI Provider ═══════ --}}
            @if($tab === 'ai')
            <div>
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-slate-800 dark:text-white">AI Provider</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Bring Your Own Key (BYOK)</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('settings.update') }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    {{-- Provider Cards --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-3">Select Provider</label>
                        <div class="grid grid-cols-4 gap-3">
                            @php
                                $providers = [
                                    'claude' => ['name' => 'Claude', 'sub' => 'Anthropic', 'color' => 'from-orange-400 to-orange-600', 'letter' => 'C'],
                                    'gemini' => ['name' => 'Gemini', 'sub' => 'Google', 'color' => 'from-blue-400 to-blue-600', 'letter' => 'G'],
                                    'openai' => ['name' => 'GPT', 'sub' => 'OpenAI', 'color' => 'from-emerald-400 to-emerald-600', 'letter' => 'O'],
                                    'groq'   => ['name' => 'Groq', 'sub' => 'Groq Cloud', 'color' => 'from-pink-400 to-rose-600', 'letter' => 'Q'],
                                ];
                            @endphp
                            @foreach($providers as $key => $p)
                            <label class="relative cursor-pointer">
                                <input type="radio" name="provider" value="{{ $key }}" class="peer sr-only"
                                    {{ ($setting->provider ?? 'gemini') === $key ? 'checked' : '' }}
                                    @change="selectedProvider = '{{ $key }}'">
                                <div class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 border-slate-200 dark:border-slate-700 peer-checked:border-cyan-500 dark:peer-checked:border-cyan-500 hover:border-slate-300 dark:hover:border-slate-600 transition-all bg-white dark:bg-white/5 peer-checked:bg-cyan-50/50 dark:peer-checked:bg-cyan-500/5">
                                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br {{ $p['color'] }} flex items-center justify-center text-white font-bold text-sm">{{ $p['letter'] }}</div>
                                    <span class="text-sm font-semibold text-slate-800 dark:text-white">{{ $p['name'] }}</span>
                                    <span class="text-xs text-slate-400 dark:text-slate-500 -mt-1">{{ $p['sub'] }}</span>
                                </div>
                            </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- API Key --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">API Key</label>
                        <div class="relative">
                            <input :type="showKey ? 'text' : 'password'" name="api_key"
                                   value="{{ $setting->api_key ? str_repeat('•', 32) : '' }}"
                                   placeholder="Enter your API key"
                                   class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all">
                            <button type="button" @click="showKey = !showKey" class="absolute right-3 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition-colors">
                                <svg x-show="!showKey" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <svg x-show="showKey" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">
                            Get your key at
                            <span x-show="selectedProvider === 'claude'"><a href="https://console.anthropic.com" target="_blank" class="text-cyan-500 hover:underline">console.anthropic.com</a></span>
                            <span x-show="selectedProvider === 'gemini'"><a href="https://aistudio.google.com/apikey" target="_blank" class="text-cyan-500 hover:underline">aistudio.google.com</a></span>
                            <span x-show="selectedProvider === 'openai'"><a href="https://platform.openai.com/api-keys" target="_blank" class="text-cyan-500 hover:underline">platform.openai.com</a></span>
                            <span x-show="selectedProvider === 'groq'"><a href="https://console.groq.com" target="_blank" class="text-cyan-500 hover:underline">console.groq.com</a></span>
                        </p>
                    </div>

                    {{-- Model Select --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Model</label>
                        <select name="model" class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all">
                            {{-- Claude --}}
                            <template x-if="selectedProvider === 'claude'">
                                <optgroup label="Claude Models">
                                    <option value="claude-sonnet-4-6" {{ ($setting->model ?? '') === 'claude-sonnet-4-6' ? 'selected' : '' }}>Claude Sonnet 4.6 — Recommended</option>
                                    <option value="claude-opus-4-8" {{ ($setting->model ?? '') === 'claude-opus-4-8' ? 'selected' : '' }}>Claude Opus 4.8 — Best quality</option>
                                    <option value="claude-haiku-4-5-20251001" {{ ($setting->model ?? '') === 'claude-haiku-4-5-20251001' ? 'selected' : '' }}>Claude Haiku 4.5 — Fast &amp; cheap</option>
                                </optgroup>
                            </template>
                            {{-- Gemini --}}
                            <template x-if="selectedProvider === 'gemini'">
                                <optgroup label="Gemini Models">
                                    <option value="gemini-2.0-flash" {{ ($setting->model ?? '') === 'gemini-2.0-flash' ? 'selected' : '' }}>Gemini 2.0 Flash — Fast, free tier</option>
                                    <option value="gemini-1.5-pro" {{ ($setting->model ?? '') === 'gemini-1.5-pro' ? 'selected' : '' }}>Gemini 1.5 Pro — Best quality</option>
                                </optgroup>
                            </template>
                            {{-- OpenAI --}}
                            <template x-if="selectedProvider === 'openai'">
                                <optgroup label="GPT Models">
                                    <option value="gpt-4o-mini" {{ ($setting->model ?? '') === 'gpt-4o-mini' ? 'selected' : '' }}>GPT-4o Mini — Fast & affordable</option>
                                    <option value="gpt-4o" {{ ($setting->model ?? '') === 'gpt-4o' ? 'selected' : '' }}>GPT-4o — Best quality</option>
                                </optgroup>
                            </template>
                            {{-- Groq --}}
                            <template x-if="selectedProvider === 'groq'">
                                <optgroup label="Groq Models">
                                    <option value="llama-3.3-70b-versatile" {{ ($setting->model ?? '') === 'llama-3.3-70b-versatile' ? 'selected' : '' }}>Llama 3.3 70B — Best quality, free</option>
                                    <option value="llama-3.1-8b-instant" {{ ($setting->model ?? '') === 'llama-3.1-8b-instant' ? 'selected' : '' }}>Llama 3.1 8B — Ultra fast</option>
                                    <option value="gemma2-9b-it" {{ ($setting->model ?? '') === 'gemma2-9b-it' ? 'selected' : '' }}>Gemma 2 9B — Lightweight</option>
                                </optgroup>
                            </template>
                        </select>
                        <p class="mt-2 text-xs text-cyan-500">
                            <span x-show="selectedProvider === 'groq'">* Recommended for getting started — free & fast</span>
                            <span x-show="selectedProvider === 'gemini'">* Gemini Flash has a generous free tier</span>
                            <span x-show="selectedProvider === 'claude'">* Anthropic API requires billing setup</span>
                            <span x-show="selectedProvider === 'openai'">* OpenAI API requires billing setup</span>
                        </p>
                    </div>

                    {{-- Save Button --}}
                    <div class="flex items-center gap-4">
                        <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-600 hover:to-blue-700 text-white font-semibold rounded-xl shadow-lg shadow-cyan-500/25 hover:shadow-cyan-500/40 transition-all text-sm">
                            Save Settings
                        </button>
                        @if($setting->api_key)
                        <span class="flex items-center gap-1.5 text-sm text-emerald-600 dark:text-emerald-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            API key configured
                        </span>
                        @endif
                    </div>
                </form>

                {{-- Encryption notice --}}
                <div class="mt-6 flex items-start gap-3 p-4 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-800/60">
                    <svg class="w-5 h-5 text-slate-400 dark:text-slate-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                    <div>
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">Your API key is encrypted</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Stored using AES-256-CBC encryption. Your key is never sent to anyone except the selected AI provider.</p>
                    </div>
                </div>
            </div>
            @endif

            {{-- ═══════ TAB: Panel Settings ═══════ --}}
            @if($tab === 'panel')
            <div>
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75"/></svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-slate-800 dark:text-white">Panel Settings</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">General panel configuration</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('settings.updatePanel') }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    {{-- Panel Name --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Panel Name</label>
                        <input type="text" name="panel_name" value="{{ $panelSetting->panel_name ?? 'NexPanel' }}"
                               class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all"
                               placeholder="NexPanel">
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Displayed in the sidebar and browser tab</p>
                    </div>

                    {{-- Timezone --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Timezone</label>
                        <select name="timezone" class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all">
                            @foreach($timezones as $tz => $label)
                            <option value="{{ $tz }}" {{ ($panelSetting->timezone ?? 'Asia/Bangkok') === $tz ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Session Timeout --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Auto-Logout Timeout</label>
                        <div class="flex items-center gap-3">
                            <select name="session_timeout" class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all">
                                <option value="30" {{ ($panelSetting->session_timeout ?? 120) == 30 ? 'selected' : '' }}>30 minutes</option>
                                <option value="60" {{ ($panelSetting->session_timeout ?? 120) == 60 ? 'selected' : '' }}>1 hour</option>
                                <option value="120" {{ ($panelSetting->session_timeout ?? 120) == 120 ? 'selected' : '' }}>2 hours</option>
                                <option value="480" {{ ($panelSetting->session_timeout ?? 120) == 480 ? 'selected' : '' }}>8 hours</option>
                                <option value="1440" {{ ($panelSetting->session_timeout ?? 120) == 1440 ? 'selected' : '' }}>24 hours</option>
                            </select>
                        </div>
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Automatically log out after this period of inactivity</p>
                    </div>

                    {{-- Language --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Language</label>
                        <select name="language" class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all">
                            <option value="en" selected>English</option>
                        </select>
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">More languages coming in future updates</p>
                    </div>

                    {{-- Save --}}
                    <div>
                        <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-violet-500 to-purple-600 hover:from-violet-600 hover:to-purple-700 text-white font-semibold rounded-xl shadow-lg shadow-violet-500/25 hover:shadow-violet-500/40 transition-all text-sm">
                            Save Panel Settings
                        </button>
                    </div>
                </form>
            </div>
            @endif

            {{-- ═══════ TAB: Security ═══════ --}}
            @if($tab === 'security')
            <div>
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-rose-500 to-red-600 flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-slate-800 dark:text-white">Security</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Manage your account security</p>
                    </div>
                </div>

                {{-- Change Password --}}
                <form method="POST" action="{{ route('settings.updatePassword') }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div class="p-5 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-800/60 space-y-5">
                        <h3 class="text-sm font-semibold text-slate-800 dark:text-white flex items-center gap-2">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/></svg>
                            Change Password
                        </h3>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Current Password</label>
                            <input type="password" name="current_password" required
                                   class="w-full px-4 py-3 rounded-xl bg-white dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all"
                                   placeholder="Enter current password">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">New Password</label>
                            <input type="password" name="password" required minlength="8"
                                   class="w-full px-4 py-3 rounded-xl bg-white dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all"
                                   placeholder="Minimum 8 characters">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Confirm New Password</label>
                            <input type="password" name="password_confirmation" required
                                   class="w-full px-4 py-3 rounded-xl bg-white dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all"
                                   placeholder="Confirm new password">
                        </div>
                    </div>

                    <div>
                        <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-rose-500 to-red-600 hover:from-rose-600 hover:to-red-700 text-white font-semibold rounded-xl shadow-lg shadow-rose-500/25 hover:shadow-rose-500/40 transition-all text-sm">
                            Update Password
                        </button>
                    </div>
                </form>

                {{-- Session Info --}}
                <div class="mt-8 p-5 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-800/60">
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-white flex items-center gap-2 mb-4">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                        Account Info
                    </h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between py-2 border-b border-slate-200 dark:border-slate-800/60">
                            <span class="text-sm text-slate-500 dark:text-slate-400">Username</span>
                            <span class="text-sm font-medium text-slate-800 dark:text-white">{{ Auth::user()->name }}</span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-slate-200 dark:border-slate-800/60">
                            <span class="text-sm text-slate-500 dark:text-slate-400">Email</span>
                            <span class="text-sm font-medium text-slate-800 dark:text-white">{{ Auth::user()->email }}</span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-slate-200 dark:border-slate-800/60">
                            <span class="text-sm text-slate-500 dark:text-slate-400">Account Created</span>
                            <span class="text-sm font-medium text-slate-800 dark:text-white">{{ Auth::user()->created_at->format('M d, Y') }}</span>
                        </div>
                        <div class="flex items-center justify-between py-2">
                            <span class="text-sm text-slate-500 dark:text-slate-400">Current IP</span>
                            <span class="text-sm font-medium text-slate-800 dark:text-white">{{ request()->ip() }}</span>
                        </div>
                    </div>
                </div>
            </div>
            @endif

        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function settingsPage() {
    return {
        selectedProvider: '{{ $setting->provider ?? "gemini" }}',
        showKey: false,
    }
}
</script>
@endpush
