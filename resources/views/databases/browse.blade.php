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
        @if($phpmyadmin)
        <a href="/phpmyadmin/index.php?db={{ urlencode($db) }}" target="_blank" class="ml-auto flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/10 text-xs font-semibold">
            <svg class="w-4 h-4 text-orange-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15H9v-6h2v6zm4 0h-2v-6h2v6z"/></svg>
            Open in phpMyAdmin
        </a>
        @endif
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
                                <th x-show="pk" class="px-3 py-2 border-b border-slate-200 dark:border-slate-800/60 w-16"></th>
                                <template x-for="c in columns" :key="c"><th class="text-left px-3 py-2 text-xs font-semibold text-slate-500 dark:text-slate-400 whitespace-nowrap border-b border-slate-200 dark:border-slate-800/60" x-text="c"></th></template>
                            </tr></thead>
                            <tbody>
                                <template x-for="(row,i) in rows" :key="i"><tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] border-b border-slate-100 dark:border-slate-800/40 group">
                                    <td x-show="pk" class="px-3 py-1.5 whitespace-nowrap">
                                        <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <button @click="startEdit(row)" class="p-1 rounded text-slate-400 hover:text-blue-500" title="Edit"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg></button>
                                            <button @click="delRow(row)" class="p-1 rounded text-slate-400 hover:text-red-500" title="Delete"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79"/></svg></button>
                                        </div>
                                    </td>
                                    <template x-for="c in columns" :key="c"><td class="px-3 py-1.5 font-mono text-xs whitespace-nowrap max-w-xs truncate" :class="row[c] === null ? 'text-slate-400 italic' : 'text-slate-700 dark:text-slate-300'" x-text="row[c] === null ? 'NULL' : row[c]"></td></template>
                                </tr></template>
                                <template x-if="!rows.length"><tr><td class="px-4 py-6 text-center text-sm text-slate-400" :colspan="(columns.length || 1) + (pk?1:0)">Table is empty.</td></tr></template>
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

    {{-- Edit row modal --}}
    <div x-show="edit.open" x-cloak x-transition.opacity class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="edit.open=false">
        <div class="bg-white dark:bg-surface-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-lg max-h-[85vh] flex flex-col">
            <div class="flex items-center justify-between p-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-sm font-bold text-slate-800 dark:text-white">Edit row <span class="font-mono text-cyan-500" x-text="pk + '=' + edit.pkValue"></span></h3>
                <button @click="edit.open=false" class="text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <div class="p-4 space-y-2 overflow-auto">
                <template x-for="c in columns" :key="c">
                    <div class="grid grid-cols-[140px_1fr] items-center gap-2">
                        <label class="text-xs font-mono text-slate-600 dark:text-slate-300 text-right truncate" x-text="c" :class="c === pk && 'text-amber-500'"></label>
                        <input x-model="edit.data[c]" :disabled="c === pk" class="px-3 py-2 rounded-lg bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-mono text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 disabled:opacity-50">
                    </div>
                </template>
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-slate-200 dark:border-slate-700">
                <button @click="edit.open=false" class="px-4 py-2 bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-400 rounded-xl text-sm">Cancel</button>
                <button @click="saveEdit()" :disabled="loading" class="px-4 py-2 bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-semibold rounded-xl text-sm disabled:opacity-40">Save</button>
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
        columns: [], rows: [], structure: [], form: {}, pk: '',
        edit: { open: false, pkValue: null, data: {} },
        sql: '', sqlCols: [], sqlRows: [], sqlInfo: '', sqlError: false,

        u(path) { return `/databases/${encodeURIComponent(this.db)}/table/${encodeURIComponent(this.table)}${path}`; },

        async openTable(name) { this.table = name; this.tab = 'browse'; this.error = null; await this.loadBrowse(); },
        async loadBrowse() {
            this.loading = true; this.error = null;
            try { const d = await get(this.u('')); this.columns = d.columns; this.rows = d.rows; this.pk = d.pk || ''; this.info = d.rows.length + ' rows shown (max 100)' + (this.pk ? '' : ' · no primary key (read-only)'); }
            catch (e) { this.error = e.message; } this.loading = false;
        },
        startEdit(row) { this.edit = { open: true, pkValue: row[this.pk], data: { ...row } }; },
        async saveEdit() {
            this.loading = true;
            try { await post(this.u('/update'), { pk: this.pk, pk_value: this.edit.pkValue, row: this.edit.data }); this.edit.open = false; await this.loadBrowse(); }
            catch (e) { alert(e.message); } this.loading = false;
        },
        async delRow(row) {
            if (!confirm('Delete this row?')) return;
            try { await post(this.u('/deleterow'), { pk: this.pk, pk_value: row[this.pk] }); await this.loadBrowse(); }
            catch (e) { alert(e.message); }
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
