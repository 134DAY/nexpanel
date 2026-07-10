@extends('layouts.app')

@section('title', 'Logs')
@section('subheader', 'Panel activity, application errors and cron output')

@section('content')
<div x-data="logsPage()" x-init="load()" class="space-y-6">

    {{-- Tabs --}}
    <div class="flex items-center gap-1 border-b border-slate-200 dark:border-slate-800">
        <template x-for="t in tabs" :key="t.id">
            <button @click="tab = t.id; load()"
                    :class="tab === t.id
                        ? 'text-cyan-600 dark:text-cyan-400 border-cyan-500'
                        : 'text-slate-500 dark:text-slate-400 border-transparent hover:text-slate-800 dark:hover:text-white'"
                    class="px-4 py-2.5 -mb-px text-sm font-semibold border-b-2 transition-colors">
                <span x-text="t.label"></span>
            </button>
        </template>
    </div>

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-3">
        <button @click="load()" :disabled="loading"
                class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-600 hover:to-blue-700 text-white font-semibold rounded-xl shadow-lg shadow-cyan-500/25 transition-all text-sm disabled:opacity-40">
            <svg class="w-4 h-4" :class="loading && 'animate-spin'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
            Refresh
        </button>

        <button @click="clearLogs()" :disabled="loading || !canClear()"
                class="flex items-center gap-2 px-4 py-2.5 bg-white dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:border-rose-300 hover:text-rose-600 dark:hover:text-rose-400 font-medium rounded-xl text-sm transition-all disabled:opacity-40 disabled:cursor-not-allowed">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
            Clear logs
        </button>

        {{-- Operation-only filters --}}
        <template x-if="tab === 'operations'">
            <div class="flex flex-wrap items-center gap-3 ml-auto">
                <select x-model="level" @change="page = 1; load()"
                        class="px-3 py-2.5 rounded-xl bg-white dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-sm text-slate-600 dark:text-slate-300">
                    <option value="">All levels</option>
                    @foreach($levels as $l)
                        <option value="{{ $l }}">{{ ucfirst($l) }}</option>
                    @endforeach
                </select>
                <div class="relative">
                    <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                    <input x-model.debounce.400ms="q" @input="page = 1; load()" type="search" placeholder="Search action, details, IP…"
                           class="w-64 pl-9 pr-3 py-2.5 rounded-xl bg-white dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-sm text-slate-700 dark:text-slate-200 placeholder:text-slate-400 focus:outline-none focus:border-cyan-500">
                </div>
            </div>
        </template>
    </div>

    {{-- ---------------------------------------------------------- Operation --}}
    <div x-show="tab === 'operations'" x-cloak class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-white/5 text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400">
                    <tr>
                        <th class="px-5 py-3 text-left font-semibold">Level</th>
                        <th class="px-5 py-3 text-left font-semibold">Action</th>
                        <th class="px-5 py-3 text-left font-semibold">Details</th>
                        <th class="px-5 py-3 text-left font-semibold">User</th>
                        <th class="px-5 py-3 text-left font-semibold">IP</th>
                        <th class="px-5 py-3 text-right font-semibold">Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                    <template x-for="row in ops.rows" :key="row.id">
                        <tr class="hover:bg-slate-50 dark:hover:bg-white/5">
                            <td class="px-5 py-2.5">
                                <span :class="levelClass(row.level)" class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase" x-text="row.level"></span>
                            </td>
                            <td class="px-5 py-2.5 font-mono text-xs text-slate-700 dark:text-slate-300" x-text="row.action"></td>
                            <td class="px-5 py-2.5 text-slate-600 dark:text-slate-400 max-w-md truncate" :title="row.details" x-text="row.details"></td>
                            <td class="px-5 py-2.5 text-slate-500 dark:text-slate-400" x-text="row.user_name || '—'"></td>
                            <td class="px-5 py-2.5 font-mono text-xs text-slate-500 dark:text-slate-400" x-text="row.ip || '—'"></td>
                            <td class="px-5 py-2.5 text-right font-mono text-xs text-slate-500 dark:text-slate-400" x-text="row.created_at"></td>
                        </tr>
                    </template>
                    <tr x-show="!ops.rows.length && !loading">
                        <td colspan="6" class="px-5 py-16 text-center text-slate-400 dark:text-slate-500">No log entries.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div x-show="ops.last_page > 1" class="flex items-center justify-between px-5 py-3 border-t border-slate-200 dark:border-slate-800/60">
            <span class="text-xs text-slate-500 dark:text-slate-400">
                Total <span class="font-semibold" x-text="ops.total"></span> entries
            </span>
            <div class="flex items-center gap-1">
                <button @click="page = Math.max(1, page - 1); load()" :disabled="page <= 1" class="pg">Prev</button>
                <span class="px-3 text-xs text-slate-500 dark:text-slate-400">
                    <span x-text="ops.page"></span> / <span x-text="ops.last_page"></span>
                </span>
                <button @click="page = Math.min(ops.last_page, page + 1); load()" :disabled="page >= ops.last_page" class="pg">Next</button>
            </div>
        </div>
    </div>

    {{-- --------------------------------------------------------------- Run --}}
    <div x-show="tab === 'run'" x-cloak class="space-y-3">
        <p class="text-xs text-slate-500 dark:text-slate-400 font-mono">
            {{ $runPath }}
            <span x-show="run.modified"> · last written <span x-text="run.modified"></span></span>
        </p>
        <div class="bg-slate-900 dark:bg-surface-900 rounded-2xl border border-slate-200 dark:border-slate-800/60 overflow-hidden">
            <pre x-show="run.content" class="p-5 overflow-auto text-[12px] leading-relaxed font-mono text-slate-300 whitespace-pre" style="max-height: 60vh" x-text="run.content"></pre>
            <p x-show="!run.content" class="px-5 py-16 text-center text-slate-500">Run log is empty.</p>
        </div>
    </div>

    {{-- -------------------------------------------------------------- Cron --}}
    <div x-show="tab === 'cron'" x-cloak class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-4">
        <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 overflow-hidden">
            <template x-for="f in cron.files" :key="f.key">
                <button @click="selectCron(f.key)"
                        :class="cron.key === f.key ? 'bg-cyan-50 dark:bg-cyan-500/10 text-cyan-700 dark:text-cyan-400' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-white/5'"
                        class="w-full text-left px-4 py-3 border-b border-slate-100 dark:border-slate-800/60 last:border-0">
                    <p class="text-sm font-medium truncate" x-text="f.name"></p>
                    <p class="text-[11px] text-slate-400 dark:text-slate-500 font-mono mt-0.5" x-text="f.modified"></p>
                </button>
            </template>
            <p x-show="!cron.files.length && !loading" class="px-4 py-16 text-center text-sm text-slate-400 dark:text-slate-500">
                No cron output yet.
            </p>
        </div>

        <div class="bg-slate-900 dark:bg-surface-900 rounded-2xl border border-slate-200 dark:border-slate-800/60 overflow-hidden">
            <pre x-show="cron.content" class="p-5 overflow-auto text-[12px] leading-relaxed font-mono text-slate-300 whitespace-pre" style="max-height: 60vh" x-text="cron.content"></pre>
            <p x-show="!cron.content" class="px-5 py-16 text-center text-slate-500">
                <span x-show="cron.key">This log is empty.</span>
                <span x-show="!cron.key">Select a cron job to view its output.</span>
            </p>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.pg { padding:.35rem .75rem; border-radius:.5rem; font-size:.75rem; font-weight:600; color:#64748b; }
.pg:hover:not(:disabled) { background:#f1f5f9; }
.pg:disabled { opacity:.4; cursor:not-allowed; }
.dark .pg { color:#94a3b8; }
.dark .pg:hover:not(:disabled) { background:rgba(255,255,255,.06); }
</style>
@endpush

@push('scripts')
<script>
function logsPage() {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    return {
        tabs: [
            { id: 'operations', label: 'Operation logs' },
            { id: 'run',        label: 'Run logs' },
            { id: 'cron',       label: 'Cron logs' },
        ],
        tab: 'operations',
        loading: false,
        q: '', level: '', page: 1,
        ops:  { rows: [], total: 0, page: 1, last_page: 1 },
        run:  { content: '', modified: null },
        cron: { files: [], key: null, content: '' },

        async load() {
            this.loading = true;
            try {
                if (this.tab === 'operations') {
                    const params = new URLSearchParams({ q: this.q, level: this.level, page: this.page });
                    this.ops = await this.get('/logs/operations?' + params);
                } else if (this.tab === 'run') {
                    this.run = await this.get('/logs/run');
                } else {
                    this.cron.files = (await this.get('/logs/cron')).files;
                    // Keep the open file selected across refreshes; otherwise pick the newest.
                    const key = this.cron.files.some(f => f.key === this.cron.key)
                        ? this.cron.key
                        : this.cron.files[0]?.key;
                    if (key) await this.selectCron(key);
                    else { this.cron.key = null; this.cron.content = ''; }
                }
            } catch (e) { alert(e.message); }
            this.loading = false;
        },

        async selectCron(key) {
            this.cron.key = key;
            try {
                const data = await this.get('/logs/cron/' + key);
                this.cron.content = data.content;
            } catch (e) { this.cron.content = ''; alert(e.message); }
        },

        canClear() {
            if (this.tab === 'operations') return this.ops.total > 0;
            if (this.tab === 'run') return !!this.run.content;
            return !!this.cron.key;
        },

        async clearLogs() {
            const what = this.tab === 'operations' ? 'all operation log entries'
                       : this.tab === 'run'        ? 'the run log'
                       : 'this cron job log';
            if (!confirm('Clear ' + what + '? This cannot be undone.')) return;

            const url = this.tab === 'cron' ? '/logs/cron/' + this.cron.key : '/logs/' + this.tab;
            this.loading = true;
            try {
                const res = await fetch(url, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                });
                if (!res.ok) throw new Error((await res.json()).error || 'Failed to clear');
                if (this.tab === 'operations') this.page = 1;
            } catch (e) { alert(e.message); }
            this.loading = false;
            await this.load();
        },

        async get(url) {
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Request failed');
            return data;
        },

        levelClass(level) {
            return {
                info:    'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300',
                warning: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400',
                danger:  'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-400',
            }[level] || 'bg-slate-100 text-slate-600';
        },
    };
}
</script>
@endpush
