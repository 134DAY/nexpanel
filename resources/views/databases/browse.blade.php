@extends('layouts.app')

@section('title', 'Database: ' . $db)
@section('subheader', 'Browse tables and run SQL')

@section('content')
<div x-data="dbBrowser('{{ $db }}')" class="space-y-4">

    <div class="flex items-center gap-3">
        <a href="/databases" class="p-2 rounded-lg bg-slate-100 dark:bg-white/5 text-slate-500 hover:text-slate-800 dark:hover:text-white transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
        </a>
        <h2 class="text-lg font-bold text-slate-800 dark:text-white font-mono">{{ $db }}</h2>
        <span class="text-xs text-slate-400">{{ count($tables) }} tables</span>
    </div>

    <div class="grid grid-cols-[260px_1fr] gap-4">
        {{-- Tables sidebar --}}
        <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-800/60 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tables</div>
            <div class="max-h-[70vh] overflow-y-auto p-2 space-y-1">
                @forelse($tables as $t)
                <button @click="openTable('{{ $t['name'] }}')" :class="table === '{{ $t['name'] }}' ? 'bg-cyan-50 dark:bg-cyan-500/10 text-cyan-700 dark:text-cyan-400' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5'"
                    class="w-full text-left px-3 py-2 rounded-lg transition-colors">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0 opacity-60" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z"/></svg>
                        <span class="text-sm font-mono truncate">{{ $t['name'] }}</span>
                    </div>
                    <div class="text-[10px] text-slate-400 dark:text-slate-500 pl-6">{{ number_format($t['rows']) }} rows · {{ $t['size'] }}</div>
                </button>
                @empty
                <p class="px-3 py-6 text-center text-sm text-slate-400">No tables in this database.</p>
                @endforelse
            </div>
        </div>

        {{-- Main: SQL console + results --}}
        <div class="space-y-4 min-w-0">
            {{-- SQL console --}}
            <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">SQL Console</span>
                    <span class="text-[10px] text-amber-600 dark:text-amber-400">⚠ runs on <code>{{ $db }}</code></span>
                </div>
                <textarea x-model="sql" spellcheck="false" placeholder="SELECT * FROM ... "
                    class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-mono text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 resize-y" style="min-height:72px"></textarea>
                <div class="flex justify-end mt-2">
                    <button @click="run()" :disabled="loading" class="px-4 py-1.5 rounded-lg text-xs font-semibold text-white bg-gradient-to-r from-cyan-500 to-blue-600 hover:shadow-lg disabled:opacity-40">
                        <span x-show="!loading">▶ Run</span><span x-show="loading">Running…</span>
                    </button>
                </div>
            </div>

            {{-- Results --}}
            <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 overflow-hidden">
                <div class="px-4 py-2.5 border-b border-slate-200 dark:border-slate-800/60 flex items-center justify-between">
                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-200" x-text="title"></span>
                    <span class="text-xs text-slate-400" x-text="info"></span>
                </div>
                <div class="overflow-auto max-h-[60vh]">
                    <template x-if="error">
                        <p class="p-4 text-sm text-red-600 dark:text-red-400 font-mono" x-text="error"></p>
                    </template>
                    <template x-if="!error && columns.length">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0">
                                <tr class="bg-slate-50 dark:bg-white/5">
                                    <template x-for="c in columns" :key="c">
                                        <th class="text-left px-3 py-2 text-xs font-semibold text-slate-500 dark:text-slate-400 whitespace-nowrap border-b border-slate-200 dark:border-slate-800/60" x-text="c"></th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(row, i) in rows" :key="i">
                                    <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] border-b border-slate-100 dark:border-slate-800/40">
                                        <template x-for="c in columns" :key="c">
                                            <td class="px-3 py-1.5 text-slate-700 dark:text-slate-300 font-mono text-xs whitespace-nowrap max-w-xs truncate" x-text="row[c] === null ? 'NULL' : row[c]" :class="row[c] === null && 'text-slate-400 italic'"></td>
                                        </template>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </template>
                    <template x-if="!error && !columns.length">
                        <p class="p-6 text-center text-sm text-slate-400" x-text="emptyMsg"></p>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function dbBrowser(db) {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    return {
        db, table: '', sql: '', loading: false, error: null,
        columns: [], rows: [], title: 'Result', info: '', emptyMsg: 'Pick a table on the left, or run a query.',

        async openTable(name) {
            this.table = name; this.sql = `SELECT * FROM \`${name}\` LIMIT 100`;
            this.loading = true; this.error = null;
            try {
                const res = await fetch(`/databases/${encodeURIComponent(this.db)}/table/${encodeURIComponent(name)}`, { headers: { 'Accept': 'application/json' } });
                const d = await res.json();
                if (!res.ok) throw new Error(d.error || 'Error');
                this.columns = d.columns; this.rows = d.rows;
                this.title = name; this.info = d.rows.length + ' rows (max 100)';
                this.emptyMsg = 'Table is empty.';
            } catch (e) { this.error = e.message; this.columns = []; this.rows = []; }
            this.loading = false;
        },
        async run() {
            if (!this.sql.trim() || this.loading) return;
            this.loading = true; this.error = null;
            try {
                const res = await fetch(`/databases/${encodeURIComponent(this.db)}/sql`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ sql: this.sql })
                });
                const d = await res.json();
                if (!res.ok) throw new Error(d.error || 'Query failed');
                this.columns = d.columns || []; this.rows = d.rows || [];
                this.title = 'Query result';
                this.info = d.affected !== null && d.affected !== undefined ? (d.affected + ' rows affected') : (this.rows.length + ' rows');
                this.emptyMsg = d.affected !== null ? 'OK — ' + d.affected + ' rows affected.' : 'Query returned no rows.';
            } catch (e) { this.error = e.message; this.columns = []; this.rows = []; }
            this.loading = false;
        },
    }
}
</script>
@endpush
