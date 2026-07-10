@extends('layouts.app')

@section('title', 'Security')
@section('subheader', 'Firewall rules and port access')

@section('content')
<div x-data="firewall()" x-init="load()" class="space-y-6">

    {{-- Tabs (only Firewall for now) --}}
    <div class="flex items-center gap-1 border-b border-slate-200 dark:border-slate-800">
        <button class="px-4 py-2.5 -mb-px text-sm font-semibold border-b-2 border-cyan-500 text-cyan-600 dark:text-cyan-400">Firewall</button>
    </div>

    @unless($available)
    <div class="flex items-center gap-2 px-4 py-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 text-sm text-amber-600 dark:text-amber-400">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
        <code class="mx-1">ufw</code> is not installed on this system — firewall management is unavailable.
    </div>
    @endunless

    {{-- Master switch --}}
    <div class="flex flex-wrap items-center gap-4 bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-5">
        <div class="flex items-center gap-3">
            <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Firewall</span>
            <button @click="toggle()" :disabled="busy || !state.available"
                    :class="state.enabled ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-700'"
                    class="relative w-11 h-6 rounded-full transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                <span :class="state.enabled ? 'translate-x-5' : 'translate-x-0.5'"
                      class="absolute top-0.5 left-0 w-5 h-5 bg-white rounded-full shadow transition-transform"></span>
            </button>
            <span :class="state.enabled ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'"
                  class="text-xs font-bold uppercase tracking-wider" x-text="state.enabled ? 'Active' : 'Inactive'"></span>
        </div>

        <p class="text-xs text-slate-500 dark:text-slate-400 ml-auto">
            Protected ports (never blocked):
            @foreach($protectedPorts as $p)
                <code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-300 ml-1">{{ $p }}</code>
            @endforeach
        </p>
    </div>

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-3">
        <button @click="showPort = true" @disabled(!$available)
                class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-600 hover:to-blue-700 text-white font-semibold rounded-xl shadow-lg shadow-cyan-500/25 text-sm disabled:opacity-40">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Add Port Rule
        </button>
        <button @click="showIp = true" @disabled(!$available)
                class="flex items-center gap-2 px-4 py-2.5 bg-white dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 font-medium rounded-xl text-sm disabled:opacity-40">
            Add IP Rule
        </button>
        <button @click="load()" :disabled="busy"
                class="flex items-center gap-2 px-4 py-2.5 bg-white dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 font-medium rounded-xl text-sm disabled:opacity-40">
            <svg class="w-4 h-4" :class="busy && 'animate-spin'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
            Refresh
        </button>

        <div class="ml-auto flex items-center gap-1 p-1 rounded-xl bg-slate-100 dark:bg-white/5">
            <template x-for="f in ['all', 'port', 'ip']" :key="f">
                <button @click="filter = f"
                        :class="filter === f ? 'bg-white dark:bg-white/10 text-slate-800 dark:text-white shadow-sm' : 'text-slate-500 dark:text-slate-400'"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold capitalize" x-text="f === 'all' ? 'All rules' : f + ' rules'"></button>
            </template>
        </div>
    </div>

    {{-- Rules --}}
    <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-white/5 text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400">
                    <tr>
                        <th class="px-5 py-3 text-left font-semibold">#</th>
                        <th class="px-5 py-3 text-left font-semibold">Protocol</th>
                        <th class="px-5 py-3 text-left font-semibold">Port / Target</th>
                        <th class="px-5 py-3 text-left font-semibold">Status</th>
                        <th class="px-5 py-3 text-left font-semibold">Strategy</th>
                        <th class="px-5 py-3 text-left font-semibold">Direction</th>
                        <th class="px-5 py-3 text-left font-semibold">Source IP</th>
                        <th class="px-5 py-3 text-right font-semibold">Operate</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                    <template x-for="r in visibleRules()" :key="r.n">
                        <tr class="hover:bg-slate-50 dark:hover:bg-white/5">
                            <td class="px-5 py-2.5 font-mono text-xs text-slate-400" x-text="r.n"></td>
                            <td class="px-5 py-2.5 font-mono text-xs text-slate-600 dark:text-slate-400" x-text="r.proto || '—'"></td>
                            <td class="px-5 py-2.5">
                                <span class="font-mono text-xs text-slate-700 dark:text-slate-300" x-text="r.port || r.to"></span>
                                <span x-show="r.protected" class="ml-2 px-1.5 py-0.5 rounded text-[10px] font-bold bg-cyan-100 text-cyan-700 dark:bg-cyan-500/15 dark:text-cyan-400">PROTECTED</span>
                            </td>
                            <td class="px-5 py-2.5">
                                <span x-show="r.kind === 'port'" :class="r.listening ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'"
                                      class="text-xs font-medium" x-text="r.listening ? 'Listening' : 'Not Listening'"></span>
                                <span x-show="r.kind !== 'port'" class="text-xs text-slate-400">—</span>
                            </td>
                            <td class="px-5 py-2.5">
                                <span :class="strategyClass(r.action)" class="px-2 py-0.5 rounded-full text-[10px] font-bold" x-text="r.action"></span>
                            </td>
                            <td class="px-5 py-2.5 text-xs text-slate-600 dark:text-slate-400" x-text="r.direction === 'IN' ? 'Inbound' : 'Outbound'"></td>
                            <td class="px-5 py-2.5 text-xs text-slate-600 dark:text-slate-400" x-text="r.from"></td>
                            <td class="px-5 py-2.5 text-right">
                                <button @click="del(r)" :disabled="busy || (r.protected && r.action === 'ALLOW')"
                                        :title="r.protected && r.action === 'ALLOW' ? 'Protected — deleting this would lock you out' : 'Delete rule'"
                                        class="text-xs font-semibold text-rose-500 hover:text-rose-600 disabled:opacity-30 disabled:cursor-not-allowed">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!visibleRules().length && !busy">
                        <td colspan="8" class="px-5 py-16 text-center text-slate-400 dark:text-slate-500">No firewall rules.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Add Port Rule modal --}}
    <div x-show="showPort" x-cloak @keydown.escape.window="showPort = false" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-surface-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-md p-6 space-y-4">
            <h3 class="text-base font-bold text-slate-800 dark:text-white">Add Port Rule</h3>
            <div class="space-y-3">
                <div>
                    <label class="fw-label">Port <span class="text-slate-400 font-normal">(e.g. 3306, or 39000:40000)</span></label>
                    <input x-model="port.port" type="text" placeholder="3306" class="fw-input">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="fw-label">Protocol</label>
                        <select x-model="port.proto" class="fw-input">
                            <option value="tcp">TCP</option>
                            <option value="udp">UDP</option>
                            <option value="both">TCP + UDP</option>
                        </select>
                    </div>
                    <div>
                        <label class="fw-label">Strategy</label>
                        <select x-model="port.action" class="fw-input">
                            <option value="allow">Allow</option>
                            <option value="deny">Deny</option>
                            <option value="reject">Reject</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="fw-label">Source IP <span class="text-slate-400 font-normal">(blank = anywhere)</span></label>
                    <input x-model="port.from" type="text" placeholder="192.168.1.10 or 10.0.0.0/8" class="fw-input">
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button @click="showPort = false" class="px-4 py-2 bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-400 font-medium rounded-xl text-sm">Cancel</button>
                <button @click="addPort()" :disabled="busy || !port.port" class="px-4 py-2 bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-semibold rounded-xl text-sm disabled:opacity-40">Add rule</button>
            </div>
        </div>
    </div>

    {{-- Add IP Rule modal --}}
    <div x-show="showIp" x-cloak @keydown.escape.window="showIp = false" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-surface-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-md p-6 space-y-4">
            <h3 class="text-base font-bold text-slate-800 dark:text-white">Add IP Rule</h3>
            <div class="space-y-3">
                <div>
                    <label class="fw-label">IP or CIDR</label>
                    <input x-model="ip.ip" type="text" placeholder="203.0.113.9 or 10.0.0.0/8" class="fw-input">
                </div>
                <div>
                    <label class="fw-label">Strategy</label>
                    <select x-model="ip.action" class="fw-input">
                        <option value="deny">Deny</option>
                        <option value="reject">Reject</option>
                        <option value="allow">Allow</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button @click="showIp = false" class="px-4 py-2 bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-400 font-medium rounded-xl text-sm">Cancel</button>
                <button @click="addIp()" :disabled="busy || !ip.ip" class="px-4 py-2 bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-semibold rounded-xl text-sm disabled:opacity-40">Add rule</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.fw-label { display:block; font-size:.75rem; font-weight:600; color:#64748b; margin-bottom:.35rem; }
.dark .fw-label { color:#94a3b8; }
.fw-input { width:100%; padding:.6rem .75rem; border-radius:.75rem; border:1px solid #e2e8f0; background:#fff;
            font-size:.875rem; color:#334155; }
.fw-input:focus { outline:none; border-color:#06b6d4; }
.dark .fw-input { background:rgba(255,255,255,.05); border-color:#334155; color:#e2e8f0; }
</style>
@endpush

@push('scripts')
<script>
function firewall() {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    return {
        state: { available: false, enabled: false, rules: [], protected: [] },
        busy: false,
        filter: 'all',
        showPort: false, showIp: false,
        port: { port: '', proto: 'tcp', action: 'allow', from: '' },
        ip:   { ip: '', action: 'deny' },

        visibleRules() {
            if (this.filter === 'all') return this.state.rules;
            return this.state.rules.filter(r => r.kind === this.filter);
        },

        async load() {
            this.busy = true;
            try { this.state = await this.get('/security/firewall'); }
            catch (e) { alert(e.message); }
            this.busy = false;
        },

        async toggle() {
            const on = !this.state.enabled;
            if (on && !confirm(
                'Enable the firewall?\n\nPorts ' + this.state.protected.join(', ') +
                ' will be allowed first so you keep SSH and panel access.'
            )) return;
            await this.post('/security/firewall/toggle', { enabled: on });
        },

        async addPort() {
            if (await this.post('/security/firewall/port', this.port)) {
                this.showPort = false;
                this.port = { port: '', proto: 'tcp', action: 'allow', from: '' };
            }
        },

        async addIp() {
            if (await this.post('/security/firewall/ip', this.ip)) {
                this.showIp = false;
                this.ip = { ip: '', action: 'deny' };
            }
        },

        async del(rule) {
            if (!confirm('Delete rule #' + rule.n + ' (' + rule.action + ' ' + rule.to + ')?')) return;
            this.busy = true;
            try {
                const res = await fetch('/security/firewall/' + rule.n, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                });
                if (!res.ok) throw new Error((await res.json()).error || 'Failed');
            } catch (e) { alert(e.message); }
            this.busy = false;
            await this.load();
        },

        async post(url, body) {
            this.busy = true;
            let ok = false;
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify(body),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.error || Object.values(data.errors || {}).flat().join('\n') || 'Failed');
                ok = true;
            } catch (e) { alert(e.message); }
            this.busy = false;
            await this.load();
            return ok;
        },

        async get(url) {
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Request failed');
            return data;
        },

        strategyClass(action) {
            return {
                ALLOW:  'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400',
                DENY:   'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-400',
                REJECT: 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-400',
                LIMIT:  'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400',
            }[action] || 'bg-slate-100 text-slate-600';
        },
    };
}
</script>
@endpush
