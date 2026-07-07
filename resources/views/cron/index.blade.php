@extends('layouts.app')

@section('title', 'Cron Jobs')
@section('subheader', 'Manage scheduled tasks')

@section('content')
<div x-data="cronManager()" class="space-y-6">

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-5">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Jobs</p>
            <p class="text-2xl font-bold text-slate-800 dark:text-white mt-1">{{ $stats['total'] }}</p>
        </div>
        <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-5">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Active</p>
            <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ $stats['active'] }}</p>
        </div>
        <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-5">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Paused</p>
            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ $stats['paused'] }}</p>
        </div>
    </div>

    @unless($available)
    <div class="flex items-center gap-2 px-4 py-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 text-sm text-amber-600 dark:text-amber-400">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
        <code>crontab</code> is not installed on this system — cron management is unavailable.
    </div>
    @endunless

    {{-- Toolbar --}}
    <div class="flex items-center justify-end">
        <button @click="showCreate = true" @disabled(!$available) class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-600 hover:to-blue-700 text-white font-semibold rounded-xl shadow-lg shadow-cyan-500/25 transition-all text-sm disabled:opacity-40 disabled:cursor-not-allowed">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Add Cron Job
        </button>
    </div>

    {{-- Jobs List --}}
    <div class="space-y-3">
        @forelse($jobs as $job)
        <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-5 hover:border-cyan-300 dark:hover:border-cyan-500/30 transition-colors">
            <div class="flex items-start justify-between">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-3 mb-2">
                        <h3 class="text-sm font-bold text-slate-800 dark:text-white">{{ $job['name'] }}</h3>
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ $job['status'] === 'active' ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400' }}">
                            {{ ucfirst($job['status']) }}
                        </span>
                    </div>

                    <div class="px-3 py-2 rounded-lg bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-800/60 mb-3">
                        <code class="text-xs text-slate-600 dark:text-slate-400 font-mono break-all">{{ $job['command'] }}</code>
                    </div>

                    <div class="flex items-center gap-6 text-xs text-slate-400 dark:text-slate-500">
                        <span class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ $job['schedule_human'] }}
                        </span>
                        <span class="font-mono text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-white/5 px-2 py-0.5 rounded">{{ $job['schedule'] }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-1 ml-4 shrink-0">
                    <button @click="runNow({{ $job['id'] }})" class="p-2 rounded-lg hover:bg-cyan-50 dark:hover:bg-cyan-500/10 text-slate-400 hover:text-cyan-500 transition-colors" title="Run Now">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z"/></svg>
                    </button>
                    <button @click="toggle({{ $job['id'] }})" class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-white/5 text-slate-400 hover:text-amber-500 transition-colors" title="Enable / Pause">
                        @if($job['status'] === 'active')
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25v13.5m-7.5-13.5v13.5"/></svg>
                        @else
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z"/></svg>
                        @endif
                    </button>
                    <button @click="remove({{ $job['id'] }})" class="p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-500/10 text-slate-400 hover:text-red-500 transition-colors" title="Delete">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79"/></svg>
                    </button>
                </div>
            </div>
        </div>
        @empty
        <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-10 text-center text-sm text-slate-400 dark:text-slate-500">
            No cron jobs yet. Click <span class="font-semibold text-slate-500 dark:text-slate-300">Add Cron Job</span> to create one.
        </div>
        @endforelse
    </div>

    {{-- Run output --}}
    <div x-show="output !== null" x-cloak class="bg-slate-900 rounded-2xl border border-slate-700 p-4">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Run Output <span x-text="'(exit ' + exitCode + ')'"></span></span>
            <button @click="output = null" class="text-slate-500 hover:text-slate-300"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <pre class="text-xs text-slate-200 font-mono whitespace-pre-wrap max-h-64 overflow-auto" x-text="output || '(no output)'"></pre>
    </div>

    {{-- Create Modal --}}
    <div x-show="showCreate" x-cloak x-transition.opacity class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showCreate = false">
        <div class="bg-white dark:bg-surface-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-lg">
            <div class="flex items-center justify-between p-6 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white">Add Cron Job</h3>
                <button @click="showCreate = false" class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-white/10 text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Job Name <span class="text-slate-400 font-normal">(optional)</span></label>
                    <input type="text" x-model="draft.name" placeholder="Database Backup" class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Command</label>
                    <input type="text" x-model="draft.command" placeholder="/usr/bin/php /var/www/artisan schedule:run" class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent font-mono text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Schedule</label>
                    <select x-model="draft.schedule" class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent">
                        <option value="* * * * *">Every minute</option>
                        <option value="*/5 * * * *">Every 5 minutes</option>
                        <option value="*/30 * * * *">Every 30 minutes</option>
                        <option value="0 * * * *">Every hour</option>
                        <option value="0 0 * * *" selected>Every day at midnight</option>
                        <option value="0 0 * * 0">Every Sunday</option>
                        <option value="0 0 1 * *">Monthly</option>
                    </select>
                    <input type="text" x-model="draft.schedule" class="mt-2 w-full px-4 py-2 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white font-mono text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500" placeholder="or type a custom expression">
                </div>
                <div class="flex gap-3 pt-2">
                    <button @click="save()" class="flex-1 px-4 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-semibold rounded-xl shadow-lg shadow-cyan-500/25 transition-all text-sm">Create Job</button>
                    <button @click="showCreate = false" class="px-4 py-2.5 bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-400 font-medium rounded-xl text-sm">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function cronManager() {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    return {
        showCreate: false,
        output: null,
        exitCode: 0,
        draft: { name: '', command: '', schedule: '0 0 * * *' },

        async call(url, method = 'POST') {
            const res = await fetch(url, { method, headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.error || ('HTTP ' + res.status));
            return data;
        },
        async save() {
            if (!this.draft.command.trim()) { alert('Command is required'); return; }
            const fd = new FormData();
            Object.entries(this.draft).forEach(([k, v]) => fd.append(k, v));
            try {
                const res = await fetch('/cron', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: fd });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.error || ('HTTP ' + res.status));
                location.reload();
            } catch (e) { alert(e.message); }
        },
        async toggle(id) { try { await this.call('/cron/' + id + '/toggle'); location.reload(); } catch (e) { alert(e.message); } },
        async remove(id) { if (!confirm('Delete this cron job?')) return; try { await this.call('/cron/' + id, 'DELETE'); location.reload(); } catch (e) { alert(e.message); } },
        async runNow(id) {
            this.output = 'Running…'; this.exitCode = 0;
            try { const data = await this.call('/cron/' + id + '/run'); this.output = data.output; this.exitCode = data.exit; }
            catch (e) { this.output = e.message; this.exitCode = -1; }
        },
    }
}
</script>
@endpush
