@extends('layouts.app')

@section('title', 'AI Assistant')
@section('header', 'AI Assistant')

@section('content')
<div x-data="aiChat()" x-init="init()" class="flex h-[calc(100vh-8rem)] -m-6">

    <!-- Chat History Sidebar -->
    <div class="w-72 shrink-0 bg-slate-50 dark:bg-white/5 border-r border-slate-200 dark:border-white/10 flex flex-col"
         x-show="showHistory" x-transition>

        <!-- Sidebar Header -->
        <div class="p-4 border-b border-slate-200 dark:border-white/10 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-600 dark:text-slate-300">Chat History</h3>
            <button @click="startNewChat()" class="p-1.5 rounded-lg bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-500 dark:text-cyan-400 transition-colors" title="New Chat">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            </button>
        </div>

        <!-- Sessions List -->
        <div class="flex-1 overflow-y-auto p-2 space-y-1">
            <template x-if="sessions.length === 0">
                <div class="px-3 py-8 text-center">
                    <svg class="w-8 h-8 mx-auto text-slate-300 dark:text-slate-600 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155"/></svg>
                    <p class="text-xs text-slate-400 dark:text-slate-500">No conversations yet</p>
                    <p class="text-xs text-slate-400 dark:text-slate-600 mt-1">Start chatting to see history here</p>
                </div>
            </template>

            <template x-for="session in sessions" :key="session.session_id">
                <div @click="loadSession(session.session_id)"
                     :class="currentSession === session.session_id
                        ? 'bg-cyan-50 dark:bg-cyan-500/10 border-cyan-300 dark:border-cyan-500/30'
                        : 'bg-white dark:bg-white/5 border-slate-200 dark:border-transparent hover:bg-slate-100 dark:hover:bg-white/10'"
                     class="group relative px-3 py-2.5 rounded-xl border cursor-pointer transition-all">
                    <p class="text-sm text-slate-800 dark:text-white truncate pr-6" x-text="session.preview"></p>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-[10px] text-slate-400 dark:text-slate-500" x-text="formatDate(session.last_active)"></span>
                        <span class="text-[10px] text-slate-400 dark:text-slate-600" x-text="session.message_count + ' messages'"></span>
                    </div>
                    <button @click.stop="deleteSession(session.session_id)"
                            class="absolute right-2 top-2 p-1 rounded-lg opacity-0 group-hover:opacity-100 hover:bg-red-500/20 text-slate-400 dark:text-slate-500 hover:text-red-500 dark:hover:text-red-400 transition-all">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                    </button>
                </div>
            </template>
        </div>
    </div>

    <!-- Main Chat Area -->
    <div class="flex-1 flex flex-col min-w-0">

        <!-- Chat Header -->
        <div class="px-6 py-3 border-b border-slate-200 dark:border-white/10 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <button @click="showHistory = !showHistory" class="p-2 rounded-lg bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-500 dark:text-slate-400 transition-colors" title="Toggle History">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                </button>
                <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-800 dark:text-white">AI Assistant</h3>
                    <p class="text-xs text-slate-400 dark:text-slate-500">Powered by <span class="text-cyan-600 dark:text-cyan-400" x-text="provider || 'AI'"></span></p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="/settings" class="p-2 rounded-lg bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-white transition-colors" title="Settings">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </a>
                <button @click="startNewChat()" class="p-2 rounded-lg bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-white transition-colors" title="New Chat">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                </button>
            </div>
        </div>

        <!-- Messages Area -->
        <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4" id="chatMessages" x-ref="chatMessages">

            <!-- Welcome Screen -->
            <template x-if="messages.length === 0 && !loading">
                <div class="flex flex-col items-center justify-center h-full">
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-cyan-500/20 to-blue-600/20 flex items-center justify-center mb-4">
                        <svg class="w-7 h-7 text-cyan-500 dark:text-cyan-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-1">How can I help you?</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Ask me anything — server management, troubleshooting, or explanations!</p>

                    <div class="grid grid-cols-2 gap-3 max-w-lg">
                        <button @click="sendQuick('สร้างเว็บไซต์ใหม่ชื่อ example.com')" class="p-4 rounded-xl bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 hover:bg-slate-50 dark:hover:bg-white/10 hover:border-cyan-300 dark:hover:border-cyan-500/30 text-left transition-all">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="w-7 h-7 rounded-lg bg-cyan-100 dark:bg-cyan-500/15 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-cyan-600 dark:text-cyan-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 010 1.972l-11.54 6.347a1.125 1.125 0 01-1.667-.986V5.653z"/></svg>
                                </div>
                                <span class="text-xs font-bold text-cyan-600 dark:text-cyan-400 uppercase tracking-wide">Execute</span>
                            </div>
                            <span class="text-sm text-slate-600 dark:text-slate-300">Create a new website</span>
                        </button>
                        <button @click="sendQuick('Analyze Nginx error log')" class="p-4 rounded-xl bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 hover:bg-slate-50 dark:hover:bg-white/10 hover:border-blue-300 dark:hover:border-blue-500/30 text-left transition-all">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="w-7 h-7 rounded-lg bg-blue-100 dark:bg-blue-500/15 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5"/></svg>
                                </div>
                                <span class="text-xs font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wide">Analyze</span>
                            </div>
                            <span class="text-sm text-slate-600 dark:text-slate-300">Read error logs & performance</span>
                        </button>
                        <button @click="sendQuick('แนะนำวิธี optimize MySQL performance')" class="p-4 rounded-xl bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 hover:bg-slate-50 dark:hover:bg-white/10 hover:border-emerald-300 dark:hover:border-emerald-500/30 text-left transition-all">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="w-7 h-7 rounded-lg bg-emerald-100 dark:bg-emerald-500/15 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.383a14.406 14.406 0 01-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 10-7.517 0c.85.493 1.509 1.333 1.509 2.316V18"/></svg>
                                </div>
                                <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wide">Advise</span>
                            </div>
                            <span class="text-sm text-slate-600 dark:text-slate-300">Optimization tips & best practices</span>
                        </button>
                        <button @click="sendQuick('อธิบาย error 502 Bad Gateway ให้หน่อย')" class="p-4 rounded-xl bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 hover:bg-slate-50 dark:hover:bg-white/10 hover:border-purple-300 dark:hover:border-purple-500/30 text-left transition-all">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="w-7 h-7 rounded-lg bg-purple-100 dark:bg-purple-500/15 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.62 48.62 0 0112 20.904a48.62 48.62 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.636 50.636 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/></svg>
                                </div>
                                <span class="text-xs font-bold text-purple-600 dark:text-purple-400 uppercase tracking-wide">Explain</span>
                            </div>
                            <span class="text-sm text-slate-600 dark:text-slate-300">Explain errors, configs & concepts</span>
                        </button>
                    </div>
                </div>
            </template>

            <!-- Message Bubbles -->
            <template x-for="(msg, index) in messages" :key="index">
                <div :class="msg.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                    <!-- AI message -->
                    <div x-show="msg.role === 'assistant'" class="flex gap-3 max-w-[80%]">
                        <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center shrink-0 mt-1">
                            <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
                        </div>
                        <div>
                            <!-- Action badge -->
                            <div x-show="msg.action && msg.action.type !== 'chat'" class="mb-1">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide"
                                    :class="{
                                        'bg-blue-100 dark:bg-blue-500/15 text-blue-700 dark:text-blue-400': msg.action?.type === 'analyze',
                                        'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-400': msg.action?.type === 'advise',
                                        'bg-purple-100 dark:bg-purple-500/15 text-purple-700 dark:text-purple-400': msg.action?.type === 'explain',
                                        'bg-cyan-100 dark:bg-cyan-500/15 text-cyan-700 dark:text-cyan-400': msg.action?.type === 'execute',
                                    }"
                                    x-text="msg.action?.type"></span>
                            </div>
                            <div x-show="msg.content" class="px-4 py-3 rounded-2xl rounded-tl-md bg-slate-100 dark:bg-white/10 border border-slate-200 dark:border-white/10">
                                <div class="text-sm text-slate-700 dark:text-slate-200 whitespace-pre-wrap prose-sm" x-html="formatMarkdown(msg.content)"></div>
                            </div>

                            <!-- Action confirmation card -->
                            <template x-if="msg.proposedAction && !msg.executed">
                                <div class="mt-2 rounded-2xl border overflow-hidden"
                                     :class="{
                                        'border-amber-300 dark:border-amber-500/40': msg.proposedAction.level === 'caution',
                                        'border-red-300 dark:border-red-500/40': msg.proposedAction.level === 'dangerous' || msg.proposedAction.level === 'blocked',
                                        'border-emerald-300 dark:border-emerald-500/40': msg.proposedAction.level === 'safe',
                                     }">
                                    <div class="px-4 py-2.5 flex items-center gap-2"
                                         :class="{
                                            'bg-amber-50 dark:bg-amber-500/10': msg.proposedAction.level === 'caution',
                                            'bg-red-50 dark:bg-red-500/10': msg.proposedAction.level === 'dangerous' || msg.proposedAction.level === 'blocked',
                                            'bg-emerald-50 dark:bg-emerald-500/10': msg.proposedAction.level === 'safe',
                                         }">
                                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                                        <span class="text-xs font-bold uppercase tracking-wide" x-text="msg.proposedAction.level"></span>
                                        <span class="text-xs text-slate-500 dark:text-slate-400">— confirm to run</span>
                                    </div>
                                    <div class="px-4 py-3 bg-white dark:bg-white/5">
                                        <p class="text-sm font-medium text-slate-800 dark:text-white mb-1" x-text="msg.proposedAction.summary"></p>
                                        <code class="block text-[11px] font-mono text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-black/20 rounded-lg px-2 py-1.5 mb-3 break-all"
                                              x-text="msg.proposedAction.tool + '(' + JSON.stringify(msg.proposedAction.args) + ')'"></code>
                                        <div class="flex gap-2">
                                            <button @click="runAction(msg)" :disabled="msg.running || !msg.proposedAction.allowed"
                                                class="px-4 py-1.5 rounded-lg text-xs font-semibold text-white bg-gradient-to-r from-cyan-500 to-blue-600 hover:shadow-lg disabled:opacity-40 disabled:cursor-not-allowed">
                                                <span x-show="!msg.running">▶ Run</span>
                                                <span x-show="msg.running">Running…</span>
                                            </button>
                                            <button @click="msg.executed = true" :disabled="msg.running"
                                                class="px-4 py-1.5 rounded-lg text-xs font-medium bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-white/20">Cancel</button>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Action result -->
                            <template x-if="msg.result">
                                <div class="mt-2 rounded-2xl border overflow-hidden"
                                     :class="msg.result.ok ? 'border-emerald-300 dark:border-emerald-500/30' : 'border-red-300 dark:border-red-500/30'">
                                    <div class="px-4 py-2 text-xs font-bold flex items-center gap-1.5"
                                         :class="msg.result.ok ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400'">
                                        <span x-text="msg.result.ok ? '✅ Done' : '❌ Failed'"></span>
                                    </div>
                                    <pre class="px-4 py-3 text-[11px] font-mono text-slate-600 dark:text-slate-300 bg-slate-900/5 dark:bg-black/20 whitespace-pre-wrap max-h-56 overflow-auto" x-text="msg.result.output"></pre>
                                </div>
                            </template>
                        </div>
                    </div>
                    <!-- User message -->
                    <div x-show="msg.role === 'user'" class="max-w-[80%]">
                        <div class="px-4 py-3 rounded-2xl rounded-tr-md bg-gradient-to-r from-cyan-500 to-blue-600">
                            <p class="text-sm text-white" x-text="msg.content"></p>
                        </div>
                    </div>
                    <!-- Error message -->
                    <div x-show="msg.role === 'error'" class="flex gap-3 max-w-[80%]">
                        <div class="w-7 h-7 rounded-lg bg-red-100 dark:bg-red-500/20 flex items-center justify-center shrink-0 mt-1">
                            <svg class="w-3.5 h-3.5 text-red-500 dark:text-red-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                        </div>
                        <div class="px-4 py-3 rounded-2xl rounded-tl-md bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20">
                            <p class="text-sm text-red-600 dark:text-red-300" x-text="msg.content"></p>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Typing Indicator -->
            <div x-show="loading" class="flex gap-3">
                <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center shrink-0">
                    <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
                </div>
                <div class="px-4 py-3 rounded-2xl rounded-tl-md bg-slate-100 dark:bg-white/10 border border-slate-200 dark:border-white/10">
                    <div class="flex gap-1.5">
                        <div class="w-2 h-2 rounded-full bg-cyan-500 dark:bg-cyan-400 animate-bounce" style="animation-delay: 0ms"></div>
                        <div class="w-2 h-2 rounded-full bg-cyan-500 dark:bg-cyan-400 animate-bounce" style="animation-delay: 150ms"></div>
                        <div class="w-2 h-2 rounded-full bg-cyan-500 dark:bg-cyan-400 animate-bounce" style="animation-delay: 300ms"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Input Area -->
        <div class="px-6 py-4 border-t border-slate-200 dark:border-white/10 shrink-0">
            <form @submit.prevent="sendMessage()" class="flex gap-3">
                <div class="flex-1 relative">
                    <textarea x-model="input" @keydown.enter.prevent="if(!$event.shiftKey) sendMessage()"
                        rows="1"
                        placeholder="Ask anything... (Thai or English)"
                        class="w-full px-4 py-3 pr-12 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/10 focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 outline-none text-sm text-slate-800 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 transition-all resize-none"
                        :disabled="loading"
                        style="min-height: 46px; max-height: 120px;"
                        @input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 120) + 'px'"></textarea>
                </div>
                <button type="submit" :disabled="loading || !input.trim()"
                    class="px-4 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 text-white hover:shadow-lg hover:shadow-cyan-500/25 transition-all disabled:opacity-50 disabled:cursor-not-allowed shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg>
                </button>
            </form>
            <p class="text-center text-[10px] text-slate-400 dark:text-slate-600 mt-2">Press Enter to send, Shift+Enter for new line</p>
        </div>
    </div>
</div>

<script>
function aiChat() {
    return {
        messages: [],
        input: '',
        loading: false,
        currentSession: null,
        sessions: [],
        provider: '{{ $providerName ?? "" }}',
        showHistory: true,

        async init() {
            this.currentSession = this.generateUUID();
            await this.loadSessions();
        },

        generateUUID() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
                const r = Math.random() * 16 | 0;
                return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
            });
        },

        formatMarkdown(text) {
            if (!text) return '';
            // Basic markdown: bold, code blocks, inline code, line breaks
            return text
                .replace(/```(\w*)\n([\s\S]*?)```/g, '<pre class="bg-slate-200 dark:bg-black/30 rounded-lg p-3 my-2 overflow-x-auto text-xs font-mono">$2</pre>')
                .replace(/`([^`]+)`/g, '<code class="bg-slate-200 dark:bg-black/30 px-1.5 py-0.5 rounded text-xs font-mono">$1</code>')
                .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                .replace(/\n/g, '<br>');
        },

        async loadSessions() {
            try {
                const res = await fetch('/api/ai/sessions');
                if (res.ok) this.sessions = await res.json();
            } catch (e) { console.error('Failed to load sessions:', e); }
        },

        async loadSession(sessionId) {
            this.currentSession = sessionId;
            this.messages = [];
            this.loading = true;
            try {
                const res = await fetch(`/api/ai/history?session_id=${sessionId}`);
                if (res.ok) this.messages = await res.json();
            } catch (e) { console.error('Failed to load session:', e); }
            this.loading = false;
            this.$nextTick(() => this.scrollToBottom());
        },

        async startNewChat() {
            this.currentSession = this.generateUUID();
            this.messages = [];
            this.input = '';
        },

        async deleteSession(sessionId) {
            if (!confirm('Delete this conversation?')) return;
            try {
                await fetch('/api/ai/session', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ session_id: sessionId })
                });
                this.sessions = this.sessions.filter(s => s.session_id !== sessionId);
                if (this.currentSession === sessionId) this.startNewChat();
            } catch (e) { console.error('Failed to delete session:', e); }
        },

        sendQuick(text) { this.input = text; this.sendMessage(); },

        async runAction(msg) {
            if (msg.running) return;
            const pa = msg.proposedAction;
            if (pa.level === 'dangerous' && !confirm('⚠️ This is a destructive action:\n\n' + pa.summary + '\n\nRun it anyway?')) return;
            msg.running = true;
            try {
                const res = await fetch('/api/ai/execute', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ tool: pa.tool, args: pa.args, session_id: this.currentSession })
                });
                const data = await res.json();
                msg.result = { ok: !!data.ok, output: data.output || (data.ok ? 'Done.' : 'Failed.') };
            } catch (e) {
                msg.result = { ok: false, output: 'Connection error: ' + e.message };
            }
            msg.running = false;
            msg.executed = true;
            await this.loadSessions();
            this.scrollToBottom();
        },

        async sendMessage() {
            const text = this.input.trim();
            if (!text || this.loading) return;

            this.messages.push({ role: 'user', content: text });
            this.input = '';
            this.loading = true;
            this.scrollToBottom();

            try {
                const res = await fetch('/api/ai/chat', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ message: text, session_id: this.currentSession })
                });

                const data = await res.json();

                if (data.error) {
                    this.messages.push({ role: 'error', content: data.error });
                } else {
                    this.messages.push({
                        role: 'assistant',
                        content: data.response,
                        action: data.action || null,
                        proposedAction: data.proposedAction || null,
                        executed: false,
                        running: false,
                        result: null
                    });
                }

                await this.loadSessions();
            } catch (e) {
                this.messages.push({ role: 'error', content: 'Connection error. Please try again.' });
            }

            this.loading = false;
            this.scrollToBottom();
        },

        scrollToBottom() {
            this.$nextTick(() => {
                const el = this.$refs.chatMessages;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },

        formatDate(dateStr) {
            const d = new Date(dateStr);
            const now = new Date();
            const diffMs = now - d;
            const diffMin = Math.floor(diffMs / 60000);
            const diffHr = Math.floor(diffMs / 3600000);
            const diffDay = Math.floor(diffMs / 86400000);
            if (diffMin < 1) return 'Just now';
            if (diffMin < 60) return diffMin + 'm ago';
            if (diffHr < 24) return diffHr + 'h ago';
            if (diffDay < 7) return diffDay + 'd ago';
            return d.toLocaleDateString();
        }
    }
}
</script>
@endsection
