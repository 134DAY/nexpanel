@extends('layouts.app')

@section('title', 'SSL Certificates')
@section('subheader', "Let's Encrypt SSL management")

@section('content')
<div x-data="sslPage()" class="space-y-6">

    @if(session('success'))
    <div class="flex items-center gap-3 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-sm">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="flex items-start gap-3 p-4 rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 text-red-700 dark:text-red-400 text-sm">
        <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
        <span class="font-mono text-xs break-all">{{ session('error') }}</span>
    </div>
    @endif

    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-5">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Certs</p>
            <p class="text-2xl font-bold text-slate-800 dark:text-white mt-1">{{ $stats['total'] }}</p>
        </div>
        <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-5">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Valid</p>
            <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ $stats['valid'] }}</p>
        </div>
        <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-5">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Expiring Soon</p>
            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ $stats['expiring'] }}</p>
        </div>
        <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-5">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Expired</p>
            <p class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1">{{ $stats['expired'] }}</p>
        </div>
    </div>

    @unless($available)
    <div class="flex items-center gap-2 px-4 py-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 text-sm text-amber-600 dark:text-amber-400">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
        No certificates found and <code>certbot</code> is not installed — SSL management is unavailable on this machine.
    </div>
    @endunless

    {{-- Toolbar --}}
    <div class="flex items-center justify-end">
        <button @click="showCreate = true" @disabled(!$certbotExists) class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-600 hover:to-blue-700 text-white font-semibold rounded-xl shadow-lg shadow-cyan-500/25 transition-all text-sm disabled:opacity-40 disabled:cursor-not-allowed">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Issue Certificate
        </button>
    </div>

    {{-- Certificates List --}}
    <div class="space-y-3">
        @forelse($certs as $cert)
        <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-5 hover:border-cyan-300 dark:hover:border-cyan-500/30 transition-colors">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center {{ $cert['status'] === 'valid' ? 'bg-emerald-50 dark:bg-emerald-500/10' : ($cert['status'] === 'expiring_soon' ? 'bg-amber-50 dark:bg-amber-500/10' : 'bg-red-50 dark:bg-red-500/10') }}">
                        <svg class="w-6 h-6 {{ $cert['status'] === 'valid' ? 'text-emerald-500' : ($cert['status'] === 'expiring_soon' ? 'text-amber-500' : 'text-red-500') }}" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-bold text-slate-800 dark:text-white">{{ $cert['domain'] }}</h3>
                            <span class="px-2 py-0.5 text-[10px] font-medium rounded-full {{ $cert['status'] === 'valid' ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : ($cert['status'] === 'expiring_soon' ? 'bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400' : 'bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400') }}">
                                {{ $cert['status'] === 'valid' ? $cert['days_left'].' days left' : ($cert['status'] === 'expiring_soon' ? $cert['days_left'].' days left' : 'Expired') }}
                            </span>
                            @if($cert['type'] === 'Wildcard')
                            <span class="px-2 py-0.5 text-[10px] font-medium rounded-full bg-violet-50 dark:bg-violet-500/10 text-violet-600 dark:text-violet-400">Wildcard</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-4 mt-1 text-xs text-slate-400 dark:text-slate-500">
                            <span>{{ $cert['issuer'] }}</span>
                            <span>Issued: {{ $cert['issued'] }}</span>
                            <span>Expires: {{ $cert['expiry'] }}</span>
                            @if($cert['auto_renew'])
                            <span class="flex items-center gap-1 text-emerald-500">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182"/></svg>
                                Auto-renew
                            </span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="renew('{{ $cert['domain'] }}')" class="px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-white/10 transition-colors">Renew</button>
                    <button @click="revoke('{{ $cert['domain'] }}')" class="p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-500/10 text-slate-400 hover:text-red-500 transition-colors" title="Delete certificate">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79"/></svg>
                    </button>
                </div>
            </div>
        </div>
        @empty
        <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-10 text-center text-sm text-slate-400 dark:text-slate-500">
            No certificates installed.
        </div>
        @endforelse
    </div>

    {{-- hidden forms --}}
    <form x-ref="renewForm" method="POST" action="/ssl/renew" class="hidden">@csrf<input type="hidden" name="domain" x-ref="renewDomain"></form>
    <form x-ref="revokeForm" method="POST" action="/ssl/revoke" class="hidden">@csrf<input type="hidden" name="domain" x-ref="revokeDomain"></form>

    {{-- Issue Certificate Modal --}}
    <div x-show="showCreate" x-cloak x-transition.opacity class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showCreate = false">
        <div class="bg-white dark:bg-surface-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-lg">
            <div class="flex items-center justify-between p-6 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white">Issue SSL Certificate</h3>
                <button @click="showCreate = false" class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-white/10 text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <form method="POST" action="/ssl/issue" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Domain</label>
                    <input type="text" name="domain" placeholder="example.com" required class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent">
                    <p class="mt-2 text-xs text-slate-400">certbot will validate domain ownership. The domain must already point to this server.</p>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="flex-1 px-4 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-semibold rounded-xl shadow-lg shadow-cyan-500/25 transition-all text-sm">Issue Certificate</button>
                    <button type="button" @click="showCreate = false" class="px-4 py-2.5 bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-400 font-medium rounded-xl text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function sslPage() {
    return {
        showCreate: false,
        renew(domain) {
            if (!confirm('Run renewal for "' + domain + '"?')) return;
            this.$refs.renewDomain.value = domain;
            this.$refs.renewForm.submit();
        },
        revoke(domain) {
            if (!confirm('Delete the certificate for "' + domain + '"? This removes it from certbot.')) return;
            this.$refs.revokeDomain.value = domain;
            this.$refs.revokeForm.submit();
        },
    }
}
</script>
@endpush
