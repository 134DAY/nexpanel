@extends('layouts.app')

@section('title', 'Websites')
@section('subheader', 'Manage Nginx virtual hosts')

@section('content')
<div x-data="websitesPage()" class="space-y-6">

    @if(session('success'))
    <div class="flex items-center gap-3 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-sm">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="flex items-center gap-3 p-4 rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 text-red-700 dark:text-red-400 text-sm">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
        {{ session('error') }}
    </div>
    @endif

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-5">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Sites</p>
            <p class="text-2xl font-bold text-slate-800 dark:text-white mt-1">{{ $stats['total'] }}</p>
        </div>
        <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-5">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Active</p>
            <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ $stats['active'] }}</p>
        </div>
        <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-5">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">SSL Enabled</p>
            <p class="text-2xl font-bold text-cyan-600 dark:text-cyan-400 mt-1">{{ $stats['ssl_enabled'] }}</p>
        </div>
        <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-5">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Disabled</p>
            <p class="text-2xl font-bold text-slate-400 dark:text-slate-500 mt-1">{{ $stats['disabled'] }}</p>
        </div>
    </div>

    @unless($available)
    <div class="flex items-center gap-2 px-4 py-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 text-sm text-amber-600 dark:text-amber-400">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
        Nginx is not installed (or <code>/etc/nginx/sites-available</code> is missing) — website management is unavailable on this machine.
    </div>
    @endunless

    {{-- Toolbar --}}
    <div class="flex items-center justify-end">
        <button @click="showCreate = true" @disabled(!$available) class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-600 hover:to-blue-700 text-white font-semibold rounded-xl shadow-lg shadow-cyan-500/25 transition-all text-sm disabled:opacity-40 disabled:cursor-not-allowed">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Add Website
        </button>
    </div>

    {{-- Sites List --}}
    <div class="space-y-3">
        @forelse($sites as $site)
        <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-5 hover:border-cyan-300 dark:hover:border-cyan-500/30 transition-colors">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl {{ $site['status'] === 'active' ? 'bg-emerald-50 dark:bg-emerald-500/10' : 'bg-slate-100 dark:bg-white/5' }} flex items-center justify-center">
                        <svg class="w-5 h-5 {{ $site['status'] === 'active' ? 'text-emerald-500' : 'text-slate-400' }}" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3"/></svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-bold text-slate-800 dark:text-white">{{ $site['domain'] }}</h3>
                            @if($site['ssl'])
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/20">SSL</span>
                            @else
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-500/20">No SSL</span>
                            @endif
                            <span class="px-2 py-0.5 text-[10px] font-medium rounded-full {{ $site['status'] === 'active' ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400' }}">{{ ucfirst($site['status']) }}</span>
                        </div>
                        <div class="flex items-center gap-4 mt-1">
                            <span class="text-xs text-slate-400 dark:text-slate-500 font-mono">{{ $site['document_root'] }}</span>
                            <span class="text-xs text-slate-400 dark:text-slate-500">PHP {{ $site['php_version'] }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button @click="viewConfig('{{ $site['id'] }}')" class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-white/5 text-slate-400 hover:text-blue-500 transition-colors" title="View config">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5"/></svg>
                    </button>
                    <button @click="toggle('{{ $site['id'] }}')" class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-white/5 text-slate-400 hover:text-amber-500 transition-colors" title="Enable / Disable">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/></svg>
                    </button>
                    <button @click="remove('{{ $site['id'] }}', '{{ $site['domain'] }}')" class="p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-500/10 text-slate-400 hover:text-red-500 transition-colors" title="Delete">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79"/></svg>
                    </button>
                </div>
            </div>
        </div>
        @empty
        <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-10 text-center text-sm text-slate-400 dark:text-slate-500">
            No virtual hosts found.
        </div>
        @endforelse
    </div>

    {{-- Config viewer modal --}}
    <div x-show="config.open" x-cloak x-transition.opacity class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="config.open = false">
        <div class="bg-white dark:bg-surface-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-3xl flex flex-col" style="max-height:85vh">
            <div class="flex items-center justify-between p-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-sm font-bold text-slate-800 dark:text-white font-mono" x-text="config.site"></h3>
                <button @click="config.open = false" class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-white/10 text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <pre class="flex-1 overflow-auto p-4 text-xs font-mono bg-slate-50 dark:bg-surface-900 text-slate-700 dark:text-slate-300 whitespace-pre-wrap" x-text="config.content"></pre>
        </div>
    </div>

    {{-- Create Website Modal --}}
    <div x-show="showCreate" x-cloak x-transition.opacity class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showCreate = false">
        <div class="bg-white dark:bg-surface-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-lg">
            <div class="flex items-center justify-between p-6 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white">Add Website</h3>
                <button @click="showCreate = false" class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-white/10 text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <form method="POST" action="/websites" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Domain Name</label>
                    <input type="text" name="domain" placeholder="example.com" required class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Document Root <span class="text-slate-400 font-normal">(optional)</span></label>
                    <input type="text" name="document_root" placeholder="/var/www/example.com/public" class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">PHP Version</label>
                    <select name="php_version" class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent">
                        <option value="8.3">PHP 8.3</option>
                        <option value="8.2" selected>PHP 8.2</option>
                        <option value="8.1">PHP 8.1</option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="ssl" value="1" id="ssl" class="w-4 h-4 rounded border-slate-300 dark:border-slate-600 text-cyan-500 focus:ring-cyan-500">
                    <label for="ssl" class="text-sm text-slate-700 dark:text-slate-300">Request SSL via Let's Encrypt (certbot)</label>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="flex-1 px-4 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-600 hover:to-blue-700 text-white font-semibold rounded-xl shadow-lg shadow-cyan-500/25 transition-all text-sm">Create Website</button>
                    <button type="button" @click="showCreate = false" class="px-4 py-2.5 bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-400 font-medium rounded-xl hover:bg-slate-200 dark:hover:bg-white/20 transition-colors text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function websitesPage() {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    return {
        showCreate: false,
        config: { open: false, site: '', content: '' },

        async call(url, method = 'POST') {
            const res = await fetch(url, { method, headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.error || ('HTTP ' + res.status));
            return data;
        },
        async toggle(id) { try { await this.call('/websites/' + encodeURIComponent(id) + '/toggle'); location.reload(); } catch (e) { alert(e.message); } },
        async remove(id, domain) { if (!confirm('Delete vhost "' + domain + '"? The config file will be removed.')) return; try { await this.call('/websites/' + encodeURIComponent(id), 'DELETE'); location.reload(); } catch (e) { alert(e.message); } },
        async viewConfig(id) {
            try {
                const res = await fetch('/websites/' + encodeURIComponent(id) + '/config', { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (!res.ok) throw new Error(data.error);
                this.config = { open: true, site: id, content: data.config };
            } catch (e) { alert(e.message); }
        },
    }
}
</script>
@endpush
