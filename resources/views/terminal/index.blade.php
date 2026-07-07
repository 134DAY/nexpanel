@extends('layouts.app')

@section('title', 'Web Terminal')
@section('subheader', 'Browser-based server terminal')

@section('content')
<div x-data="terminalApp('{{ $user }}', '{{ $host }}', '{{ $cwd }}')" class="space-y-4 h-full flex flex-col">

    {{-- Terminal Toolbar --}}
    <div class="flex items-center justify-between shrink-0">
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-xs font-medium text-emerald-700 dark:text-emerald-400">Live</span>
            </div>
            <span class="text-xs text-slate-400 dark:text-slate-500 font-mono" x-text="user + '@' + host + ':' + cwd"></span>
        </div>
        <div class="flex items-center gap-2">
            <button @click="clearTerminal()" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-white/10 text-xs font-medium transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79"/></svg>
                Clear
            </button>
        </div>
    </div>

    {{-- Warning banner --}}
    <div class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 text-xs text-amber-700 dark:text-amber-400 shrink-0">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
        Commands run <strong>for real</strong> as user <code x-text="user"></code>. Each command runs in its own shell (30s timeout); use <code>cd</code> to move around.
    </div>

    {{-- Terminal Window --}}
    <div class="flex-1 bg-slate-900 rounded-2xl border border-slate-700 overflow-hidden flex flex-col min-h-[500px]">
        <div class="flex items-center gap-2 px-4 py-2.5 bg-slate-800 border-b border-slate-700">
            <div class="flex gap-1.5">
                <div class="w-3 h-3 rounded-full bg-red-500"></div>
                <div class="w-3 h-3 rounded-full bg-amber-500"></div>
                <div class="w-3 h-3 rounded-full bg-emerald-500"></div>
            </div>
            <span class="text-xs text-slate-400 ml-2 font-mono" x-text="user + '@' + host + ' — bash'"></span>
        </div>

        <div id="terminal-output" class="flex-1 p-4 overflow-y-auto font-mono text-sm leading-6" @click="$refs.cmdInput.focus()">
            <div class="text-slate-400 text-xs mb-4">
                <pre class="text-xs leading-4 text-cyan-400">  _   _           ____                  _
 | \ | | _____  _|  _ \ __ _ _ __   ___| |
 |  \| |/ _ \ \/ / |_) / _` | '_ \ / _ \ |
 | |\  |  __/>  <|  __/ (_| | | | |  __/ |
 |_| \_|\___/_/\_\_|   \__,_|_| |_|\___|_|</pre>
                <p class="mt-2">NexPanel Web Terminal — real shell. Type a command and press Enter.</p>
            </div>

            <template x-for="(entry, i) in history" :key="i">
                <div class="mb-1">
                    <div class="flex flex-wrap">
                        <span class="text-emerald-400" x-text="user + '@' + host"></span>
                        <span class="text-slate-500">:</span>
                        <span class="text-blue-400" x-text="entry.cwd"></span>
                        <span class="text-slate-500">$&nbsp;</span>
                        <span class="text-slate-200" x-text="entry.command"></span>
                    </div>
                    <div class="whitespace-pre-wrap" :class="entry.exit === 0 ? 'text-slate-400' : 'text-red-300'" x-text="entry.output"></div>
                </div>
            </template>

            <div class="flex items-center">
                <span class="text-emerald-400" x-text="user + '@' + host"></span>
                <span class="text-slate-500">:</span>
                <span class="text-blue-400" x-text="cwd"></span>
                <span class="text-slate-500">$&nbsp;</span>
                <input x-ref="cmdInput"
                       x-model="currentCmd"
                       @keydown.enter="executeCommand()"
                       @keydown.up.prevent="historyUp()"
                       @keydown.down.prevent="historyDown()"
                       :disabled="busy"
                       class="flex-1 bg-transparent text-slate-200 outline-none border-none font-mono text-sm caret-emerald-400 disabled:opacity-50"
                       spellcheck="false"
                       autocomplete="off"
                       autofocus>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function terminalApp(user, host, cwd) {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    return {
        user, host, cwd,
        currentCmd: '',
        history: [],
        cmdHistory: [],
        historyIndex: -1,
        busy: false,

        async executeCommand() {
            const cmd = this.currentCmd.trim();
            if (!cmd || this.busy) return;

            this.cmdHistory.unshift(cmd);
            this.historyIndex = -1;

            if (cmd === 'clear') { this.history = []; this.currentCmd = ''; return; }

            const startCwd = this.cwd;
            this.currentCmd = '';
            this.busy = true;
            try {
                const res = await fetch('/api/terminal/exec', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ command: cmd, cwd: this.cwd }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.error || ('HTTP ' + res.status));
                this.cwd = data.cwd || this.cwd;
                this.history.push({ command: cmd, output: data.output, cwd: startCwd, exit: data.exit });
            } catch (e) {
                this.history.push({ command: cmd, output: e.message, cwd: startCwd, exit: 1 });
            } finally {
                this.busy = false;
                this.$nextTick(() => {
                    const el = document.getElementById('terminal-output');
                    el.scrollTop = el.scrollHeight;
                    this.$refs.cmdInput.focus();
                });
            }
        },

        historyUp() {
            if (this.historyIndex < this.cmdHistory.length - 1) {
                this.historyIndex++;
                this.currentCmd = this.cmdHistory[this.historyIndex];
            }
        },
        historyDown() {
            if (this.historyIndex > 0) {
                this.historyIndex--;
                this.currentCmd = this.cmdHistory[this.historyIndex];
            } else {
                this.historyIndex = -1;
                this.currentCmd = '';
            }
        },
        clearTerminal() { this.history = []; },
    }
}
</script>
@endpush
