@extends('layouts.app')

@section('title', 'Database: ' . $db)
@section('subheader', 'Browse tables, edit structure and run SQL')

@section('content')
<div x-data="dbBrowser('{{ $db }}')" class="space-y-4">

    <div class="flex items-center gap-3">
        <a href="/databases" class="p-2 rounded-lg bg-slate-100 dark:bg-white/5 text-slate-500 hover:text-slate-800 dark:hover:text-white transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
        </a>
        <h2 class="text-lg font-bold text-slate-800 dark:text-white font-mono">{{ $db }}</h2>
        <span class="text-xs text-slate-400">{{ count($tables) }} tables</span>
    </div>

    <div class="grid grid-cols-[240px_1fr] gap-4 items-start">
        {{-- Tables sidebar --}}
        <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-800/60 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tables</div>
            <div class="max-h-[75vh] overflow-y-auto p-2 space-y-0.5">
                @forelse($tables as $t)
                <button @click="openTable('{{ $t['name'] }}')" :class="table === '{{ $t['name'] }}' ? 'bg-cyan-50 dark:bg-cyan-500/10 text-cyan-700 dark:text-cyan-400' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5'"
                    class="w-full text-left px-3 py-1.5 rounded-lg transition-colors">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0 opacity-60" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z"/></svg>
                        <span class="text-sm font-mono truncate">{{ $t['name'] }}</span>
                    </div>
                    <div class="text-[10px] text-slate-400 dark:text-slate-500 pl-6">{{ number_format($t['rows']) }} rows · {{ $t['size'] }}</div>
                </button>
                @empty
                <p class="px-3 py-6 text-center text-sm text-slate-400">No tables.</p>
                @endforelse
            </div>
        </div>

        <div class="space-y-4 min-w-0">
            {{-- Table view with tabs --}}
            <template x-if="table">
                <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 overflow-hidden">
                    <div class="flex items-center justify-between px-4 pt-3 gap-2 flex-wrap">
                        <div class="flex items-center gap-1">
                            <span class="font-mono text-sm font-bold text-slate-800 dark:text-white mr-2" x-text="table"></span>
                            <template x-for="t in ['browse','structure','insert']" :key="t">
                                <button @click="switchTab(t)" :class="tab === t ? 'bg-cyan-500 text-white' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-white/5'"
                                    class="px-3 py-1.5 rounded-lg text-xs font-semibold capitalize transition-colors" x-text="t"></button>
                            </template>
                        </div>
                        <div class="flex items-center gap-1">
                            <button @click="emptyTable()" class="px-2.5 py-1.5 rounded-lg text-xs font-medium text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10">Empty</button>
                            <button @click="dropTable()" class="px-2.5 py-1.5 rounded-lg text-xs font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10">Drop</button>
                        </div>
                    </div>
                    <div class="px-4 pb-1 pt-2 text-xs text-slate-400" x-text="info"></div>

                    {{-- error --}}
                    <template x-if="error"><p class="px-4 py-3 text-sm text-red-600 dark:text-red-400 font-mono" x-text="error"></p></template>

                    {{-- BROWSE --}}
                    <div x-show="tab === 'browse' && !error" class="overflow-auto max-h-[60vh]">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0"><tr class="bg-slate-50 dark:bg-white/5">
                                <template x-for="c in columns" :key="c"><th class="text-left px-3 py-2 text-xs font-semibold text-slate-500 dark:text-slate-400 whitespace-nowrap border-b border-slate-200 dark:border-slate-800/60" x-text="c"></th></template>
                            </tr></thead>
                            <tbody>
                                <template x-for="(row,i) in rows" :key="i"><tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] border-b border-slate-100 dark:border-slate-800/40">
                                    <template x-for="c in columns" :key="c"><td class="px-3 py-1.5 font-mono text-xs whitespace-nowrap max-w-xs truncate" :class="row[c] === null ? 'text-slate-400 italic' : 'text-slate-700 dark:text-slate-300'" x-text="row[c] === null ? 'NULL' : row[c]"></td></template>
                                </tr></template>
                                <template x-if="!rows.length"><tr><td class="px-4 py-6 text-center text-sm text-slate-400" :colspan="columns.length || 1">Table is empty.</td></tr></template>
                            </tbody>
                        </table>
                    </div>

                    {{-- STRUCTURE --}}
                    <div x-show="tab === 'structure' && !error" class="overflow-auto max-h-[60vh]">
                        <table class="w-full text-sm">
                            <thead><tr class="bg-slate-50 dark:bg-white/5">
                                <template x-for="h in ['Field','Type','Null','Key','Default','Extra']" :key="h"><th class="text-left px-3 py-2 text-xs font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800/60" x-text="h"></th></template>
                            </tr></thead>
                            <tbody>
                                <template x-for="(col,i) in structure" :key="i"><tr class="border-b border-slate-100 dark:border-slate-800/40">
                                    <td class="px-3 py-1.5 font-mono text-xs font-semibold text-slate-800 dark:text-white" x-text="col.Field"></td>
                                    <td class="px-3 py-1.5 font-mono text-xs text-cyan-600 dark:text-cyan-400" x-text="col.Type"></td>
                                    <td class="px-3 py-1.5 text-xs text-slate-500" x-text="col.Null"></td>
                                    <td class="px-3 py-1.5 text-xs" x-text="col.Key" :class="col.Key === 'PRI' && 'text-amber-500 font-bold'"></td>
                                    <td class="px-3 py-1.5 text-xs text-slate-500 font-mono" x-text="col.Default === null ? 'NULL' : col.Default"></td>
                                    <td class="px-3 py-1.5 text-xs text-slate-500" x-text="col.Extra"></td>
                                </tr></template>
                            </tbody>
                        </table>
                    </div>

                    {{-- INSERT --}}
                    <div x-show="tab === 'insert' && !error" class="p-4 space-y-3">
                        <template x-for="col in structure" :key="col.Field">
                            <div class="grid grid-cols-[160px_1fr] items-center gap-3">
                                <label class="text-xs font-mono text-slate-600 dark:text-slate-300 text-right">
                                    <span x-text="col.Field"></span>
                                    <span class="text-[10px] text-slate-400 block" x-text="col.Type"></span>
                                </label>
                                <input x-model="form[col.Field]" :placeholder="col.Extra === 'auto_increment' ? '(auto)' : (col.Null === 'YES' ? 'NULL' : '')"
                                    class="px-3 py-2 rounded-lg bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-mono text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500">
                            </div>
                        </template>
                        <div class="flex justify-end pt-1">
                            <button @click="doInsert()" :disabled="loading" class="px-4 py-1.5 rounded-lg text-xs font-semibold text-white bg-gradient-to-r from-cyan-500 to-blue-600 hover:shadow-lg disabled:opacity-40">Insert row</button>
                        </div>
                    </div>
                </div>
            </template>

            {{-- SQL console --}}
            <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">SQL Console</span>
                    <span class="text-[10px] text-amber-600 dark:text-amber-400">⚠ runs on <code>{{ $db }}</code></span>
                </div>
                <textarea x-model="sql" spellcheck="false" placeholder="SELECT * FROM ..."
                    class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-mono text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 resize-y" style="min-height:64px"></textarea>
                <div class="flex items-center justify-between mt-2">
                    <span class="text-xs" x-text="sqlInfo" :class="sqlError ? 'text-red-500' : 'text-slate-400'"></span>
                    <button @click="run()" :disabled="loading" class="px-4 py-1.5 rounded-lg text-xs font-semibold text-white bg-gradient-to-r from-cyan-500 to-blue-600 hover:shadow-lg disabled:opacity-40">▶ Run</button>
                </div>
                <template x-if="sqlRows.length || sqlCols.length">
                    <div class="overflow-auto max-h-[40vh] mt-3 border border-slate-200 dark:border-slate-800/60 rounded-xl">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0"><tr class="bg-slate-50 dark:bg-white/5">
                                <template x-for="c in sqlCols" :key="c"><th class="text-left px-3 py-2 text-xs font-semibold text-slate-500 dark:text-slate-400 whitespace-nowrap" x-text="c"></th></template>
                            </tr></thead>
                            <tbody>
                                <template x-for="(row,i) in sqlRows" :key="i"><tr class="border-b border-slate-100 dark:border-slate-800/40">
                                    <template x-for="c in sqlCols" :key="c"><td class="px-3 py-1.5 font-mono text-xs whitespace-nowrap max-w-xs truncate" x-text="row[c] === null ? 'NULL' : row[c]"></td></template>
                                </tr></template>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function dbBrowser(db) {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const post = (url, body) => fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: JSON.stringify(body || {}) }).then(async r => { const d = await r.json(); if (!r.ok) throw new Error(d.error || 'Error'); return d; });
    const get = (url) => fetch(url, { headers: { 'Accept': 'application/json' } }).then(async r => { const d = await r.json(); if (!r.ok) throw new Error(d.error || 'Error'); return d; });
    return {
        db, table: '', tab: 'browse', loading: false, error: null, info: '',
        columns: [], rows: [], structure: [], form: {},
        sql: '', sqlCols: [], sqlRows: [], sqlInfo: '', sqlError: false,

        u(path) { return `/databases/${encodeURIComponent(this.db)}/table/${encodeURIComponent(this.table)}${path}`; },

        async openTable(name) { this.table = name; this.tab = 'browse'; this.error = null; await this.loadBrowse(); },
        async loadBrowse() {
            this.loading = true; this.error = null;
            try { const d = await get(this.u('')); this.columns = d.columns; this.rows = d.rows; this.info = d.rows.length + ' rows shown (max 100)'; }
            catch (e) { this.error = e.message; } this.loading = false;
        },
        async switchTab(t) {
            this.tab = t; this.error = null;
            if ((t === 'structure' || t === 'insert') && !this.structure.length) await this.loadStructure();
            if (t === 'browse') await this.loadBrowse();
        },
        async loadStructure() {
            this.loading = true;
            try { this.structure = await get(this.u('/structure')); this.form = {}; }
            catch (e) { this.error = e.message; } this.loading = false;
        },
        async doInsert() {
            this.loading = true;
            try { await post(this.u('/insert'), { row: this.form }); this.form = {}; this.tab = 'browse'; await this.loadBrowse(); }
            catch (e) { alert(e.message); } this.loading = false;
        },
        async emptyTable() {
            if (!confirm(`Empty (TRUNCATE) table "${this.table}"? All rows will be deleted.`)) return;
            try { await post(this.u('/truncate')); await this.loadBrowse(); } catch (e) { alert(e.message); }
        },
        async dropTable() {
            if (!confirm(`DROP table "${this.table}"? This permanently deletes the table and its data.`)) return;
            try { await post(this.u('/drop')); location.reload(); } catch (e) { alert(e.message); }
        },
        async run() {
            if (!this.sql.trim() || this.loading) return;
            this.loading = true; this.sqlError = false; this.sqlInfo = 'Running…';
            try {
                const d = await post(`/databases/${encodeURIComponent(this.db)}/sql`, { sql: this.sql });
                this.sqlCols = d.columns || []; this.sqlRows = d.rows || [];
                this.sqlInfo = d.affected !== null && d.affected !== undefined ? (d.affected + ' rows affected') : (this.sqlRows.length + ' rows returned');
            } catch (e) { this.sqlError = true; this.sqlInfo = e.message; this.sqlCols = []; this.sqlRows = []; }
            this.loading = false;
        },
    }
}
</script>
@endpush
