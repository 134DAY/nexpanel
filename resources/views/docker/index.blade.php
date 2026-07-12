@extends('layouts.app')

@section('title', 'Docker')
@section('subheader', 'Containers & images')

@section('content')
<div x-data="dockerPage()" x-init="load()" class="space-y-6">

    @unless($available)
    <div class="flex items-center gap-2 px-4 py-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 text-sm text-amber-600 dark:text-amber-400">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
        <code class="mx-1">docker</code> is not installed on this system — install Docker to manage containers here.
    </div>
    @elseunless($running)
    <div class="flex items-center gap-2 px-4 py-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 text-sm text-amber-600 dark:text-amber-400">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
        Docker is installed but the daemon is not running. Start it from <a href="/services" class="underline font-medium">Service Control</a>.
    </div>
    @endunless

    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-5">
        @foreach ([['Containers','total','cyan'],['Running','running','emerald'],['Stopped','stopped','slate'],['Images','images','violet']] as [$label,$key,$color])
        <div class="bg-white dark:bg-surface-800/50 border border-slate-200 dark:border-slate-800/60 rounded-2xl p-5">
            <span class="text-sm text-slate-500 dark:text-slate-400 font-medium">{{ $label }}</span>
            <div class="text-3xl font-extrabold text-slate-800 dark:text-white mt-2" x-text="stats.{{ $key }}">{{ $stats[$key] }}</div>
        </div>
        @endforeach
    </div>

    @if($available && $running)
    {{-- Pull toolbar --}}
    <div class="flex flex-wrap items-center gap-3">
        <input type="text" x-model="pullImage" placeholder="image:tag (เช่น nginx:latest)"
               class="flex-1 min-w-[220px] px-4 py-2.5 rounded-xl bg-white dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-500 font-mono text-sm">
        <button @click="pull()" :disabled="busy || !pullImage"
                class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-600 hover:to-blue-700 text-white font-semibold rounded-xl shadow-lg shadow-cyan-500/25 text-sm disabled:opacity-40">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
            Pull Image
        </button>
        <button @click="load()" class="px-3 py-2.5 rounded-xl bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-300 text-sm font-medium hover:bg-slate-200 dark:hover:bg-white/10">Refresh</button>
    </div>

    {{-- Containers --}}
    <div class="bg-white dark:bg-surface-800/50 border border-slate-200 dark:border-slate-800/60 rounded-2xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-800/60">
            <h3 class="text-sm font-bold text-slate-800 dark:text-white">Containers</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-[11px] uppercase tracking-wide text-slate-400 border-b border-slate-100 dark:border-slate-800/60">
                    <tr><th class="text-left px-5 py-3">Name</th><th class="text-left px-5 py-3">Image</th><th class="text-left px-5 py-3">State</th><th class="text-left px-5 py-3">Status</th><th class="text-right px-5 py-3">Actions</th></tr>
                </thead>
                <tbody>
                    <template x-for="c in containers" :key="c.id">
                        <tr class="border-b border-slate-50 dark:border-slate-800/40">
                            <td class="px-5 py-3 font-medium text-slate-700 dark:text-slate-200" x-text="c.name"></td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400 font-mono text-xs" x-text="c.image"></td>
                            <td class="px-5 py-3">
                                <span :class="c.running ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-slate-100 text-slate-500 dark:bg-white/5'"
                                      class="px-2 py-0.5 rounded-full text-[11px] font-semibold" x-text="c.state"></span>
                            </td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400 text-xs" x-text="c.status"></td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                <button x-show="!c.running" @click="action(c.id,'start')" class="text-emerald-500 hover:underline text-xs mr-2">Start</button>
                                <button x-show="c.running" @click="action(c.id,'stop')" class="text-amber-500 hover:underline text-xs mr-2">Stop</button>
                                <button @click="action(c.id,'restart')" class="text-cyan-500 hover:underline text-xs mr-2">Restart</button>
                                <button @click="showLogs(c.id)" class="text-slate-500 hover:underline text-xs mr-2">Logs</button>
                                <button @click="confirmRemove(c.id)" class="text-red-500 hover:underline text-xs">Remove</button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!containers.length"><td colspan="5" class="px-5 py-8 text-center text-slate-400 text-sm">No containers.</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Images --}}
    <div class="bg-white dark:bg-surface-800/50 border border-slate-200 dark:border-slate-800/60 rounded-2xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-800/60">
            <h3 class="text-sm font-bold text-slate-800 dark:text-white">Images</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-[11px] uppercase tracking-wide text-slate-400 border-b border-slate-100 dark:border-slate-800/60">
                    <tr><th class="text-left px-5 py-3">Repository</th><th class="text-left px-5 py-3">Tag</th><th class="text-left px-5 py-3">Size</th><th class="text-left px-5 py-3">Created</th><th class="text-right px-5 py-3">Actions</th></tr>
                </thead>
                <tbody>
                    <template x-for="i in images" :key="i.id + i.tag">
                        <tr class="border-b border-slate-50 dark:border-slate-800/40">
                            <td class="px-5 py-3 font-medium text-slate-700 dark:text-slate-200 font-mono text-xs" x-text="i.repository"></td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400 text-xs" x-text="i.tag"></td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400 text-xs" x-text="i.size"></td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400 text-xs" x-text="i.created"></td>
                            <td class="px-5 py-3 text-right"><button @click="removeImage(i.id)" class="text-red-500 hover:underline text-xs">Remove</button></td>
                        </tr>
                    </template>
                    <tr x-show="!images.length"><td colspan="5" class="px-5 py-8 text-center text-slate-400 text-sm">No images.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Logs modal --}}
    <div x-show="logsOpen" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="logsOpen = false"></div>
        <div class="relative w-full max-w-3xl bg-white dark:bg-surface-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700">
            <div class="flex items-center justify-between p-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-sm font-bold text-slate-800 dark:text-white">Container logs</h3>
                <button @click="logsOpen = false" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-white/10 text-slate-400"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <pre class="max-h-[60vh] overflow-auto rounded-b-2xl bg-slate-900 text-slate-100 text-[11px] leading-relaxed p-4 whitespace-pre-wrap" x-text="logsText"></pre>
        </div>
    </div>
</div>

@push('scripts')
<script>
function dockerPage() {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const post = (url, body) => fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(r => r.json());
    return {
        containers: [], images: [], stats: @json($stats), busy: false,
        pullImage: '', logsOpen: false, logsText: '',
        async load() {
            try { const d = await fetch('/docker/data').then(r => r.json());
                this.containers = d.containers; this.images = d.images; this.stats = d.stats; } catch (e) {}
        },
        async action(id, act) {
            if (this.busy) return; this.busy = true;
            const d = await post('/docker/action', { id, action: act });
            this.busy = false;
            if (!d.ok) alert('❌ ' + (d.error || 'failed'));
            this.load();
        },
        confirmRemove(id) { if (confirm('ลบ container นี้? (rm -f)')) this.action(id, 'remove'); },
        async showLogs(id) { this.logsText = 'Loading…'; this.logsOpen = true;
            const d = await post('/docker/logs', { id }); this.logsText = d.logs || '(no output)'; },
        async pull() {
            if (this.busy || !this.pullImage) return; this.busy = true;
            const d = await post('/docker/pull', { image: this.pullImage });
            this.busy = false;
            alert(d.ok ? '✅ pulled ' + this.pullImage : '❌ ' + (d.error || 'failed'));
            if (d.ok) { this.pullImage = ''; this.load(); }
        },
        async removeImage(id) {
            if (!confirm('ลบ image นี้?')) return;
            const d = await post('/docker/image/remove', { id });
            if (!d.ok) alert('❌ ' + (d.error || 'failed')); this.load();
        },
    };
}
</script>
@endpush
@endsection
