@extends('layouts.app')

@section('title', 'Databases')
@section('subheader', 'Manage MySQL databases and users')

@section('content')
<div x-data="databasePage()" class="space-y-6">

    @if(session('success'))
    <div class="flex items-start gap-3 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-sm">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span class="break-all">{{ session('success') }}</span>
    </div>
    @endif
    @if(session('error'))
    <div class="flex items-start gap-3 p-4 rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 text-red-700 dark:text-red-400 text-sm">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
        <span class="break-all">{{ session('error') }}</span>
    </div>
    @endif

    {{-- Stats --}}
    <div class="grid grid-cols-4 gap-4">
        <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-5"><p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Databases</p><p class="text-2xl font-bold text-slate-800 dark:text-white mt-1">{{ count($databases) }}</p></div>
        <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-5"><p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Size</p><p class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">{{ $totalSize }}</p></div>
        <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-5"><p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Users</p><p class="text-2xl font-bold text-violet-600 dark:text-violet-400 mt-1">{{ count($users) }}</p></div>
        <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-5"><p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Engine</p><p class="text-2xl font-bold {{ $available ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400' }} mt-1">{{ $available ? 'MySQL' : 'Offline' }}</p></div>
    </div>

    @unless($available)
    <div class="flex items-start gap-2 px-4 py-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 text-sm text-amber-600 dark:text-amber-400">
        <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
        <div>Cannot connect to MySQL.@if($connError)<div class="mt-1 font-mono text-xs opacity-80">{{ $connError }}</div>@endif</div>
    </div>
    @endunless

    @php $btn = 'flex items-center gap-2 px-3.5 py-2.5 bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/10 font-semibold rounded-xl transition-all text-sm disabled:opacity-40 disabled:cursor-not-allowed'; @endphp

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex bg-slate-100 dark:bg-white/5 rounded-xl p-1">
            <button @click="tab='databases'" :class="tab==='databases' ? 'bg-white dark:bg-surface-800 shadow text-slate-800 dark:text-white' : 'text-slate-500 dark:text-slate-400'" class="px-4 py-2 rounded-lg text-sm font-medium transition-all">Databases</button>
            <button @click="tab='users'" :class="tab==='users' ? 'bg-white dark:bg-surface-800 shadow text-slate-800 dark:text-white' : 'text-slate-500 dark:text-slate-400'" class="px-4 py-2 rounded-lg text-sm font-medium transition-all">Users</button>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button @click="rp.open=true; rp.value=''" @disabled(!$available) class="{{ $btn }}">
                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                Root password
            </button>

            @if($phpmyadmin)
            <a href="/databases-pma" target="_blank" class="{{ $btn }}">
                <svg class="w-4 h-4 text-orange-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15H9v-6h2v6zm4 0h-2v-6h2v6z"/></svg>
                phpMyAdmin
            </a>
            @endif

            <button @click="$refs.syncForm.submit()" @disabled(!$available) class="{{ $btn }}">
                <svg class="w-4 h-4 text-cyan-500" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992V4.356m-4.992 4.992l3.181-3.183a8.25 8.25 0 00-13.803 3.7M4.031 9.865v4.992h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7"/></svg>
                Sync all
            </button>

            <button @click="openRecycle()" class="{{ $btn }} relative">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79"/></svg>
                Recycle Bin
                <span x-show="rb.count > 0" x-cloak class="ml-0.5 min-w-[1.25rem] px-1 py-0.5 rounded-full bg-slate-200 dark:bg-white/10 text-[11px] font-bold leading-none text-slate-600 dark:text-slate-300" x-text="rb.count"></span>
            </button>

            {{-- Engine version + service control --}}
            <div class="relative" x-data="{ menu: false }" @click.outside="menu=false">
                <button @click="menu=!menu" class="{{ $btn }}">
                    <span class="w-2 h-2 rounded-full {{ $available ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                    <span class="font-mono">{{ $version ? 'MySQL ' . \Illuminate\Support\Str::before($version, '-') : 'MySQL offline' }}</span>
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                </button>
                <div x-show="menu" x-cloak x-transition class="absolute right-0 mt-2 w-40 py-1 bg-white dark:bg-surface-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-xl z-30">
                    <button @click="menu=false; svcAction('start')" class="w-full text-left px-4 py-2 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5">Start</button>
                    <button @click="menu=false; svcAction('restart')" class="w-full text-left px-4 py-2 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5">Restart</button>
                    <button @click="menu=false; svcAction('stop')" class="w-full text-left px-4 py-2 text-sm text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10">Stop</button>
                </div>
            </div>

            <button @click="showCreate=true" @disabled(!$available) class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-600 hover:to-blue-700 text-white font-semibold rounded-xl shadow-lg shadow-cyan-500/25 transition-all text-sm disabled:opacity-40 disabled:cursor-not-allowed">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                <span x-text="tab==='databases' ? 'Create Database' : 'Add User'"></span>
            </button>
        </div>
    </div>

    {{-- Databases table (aaPanel-style) --}}
    <div x-show="tab==='databases'" class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 overflow-x-auto">
        <table class="w-full min-w-[900px]">
            <thead><tr class="border-b border-slate-200 dark:border-slate-800/60">
                <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Database</th>
                <th class="text-left px-3 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Username</th>
                <th class="text-left px-3 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Password</th>
                <th class="text-left px-3 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Size</th>
                <th class="text-left px-3 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tables</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Operate</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/40">
                @forelse($databases as $db)
                <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors" x-data="{ show:false }">
                    <td class="px-5 py-3">
                        <a @if($phpmyadmin) href="/databases/{{ urlencode($db['name']) }}/pma" target="_blank" @endif class="flex items-center gap-2.5 text-sm font-semibold text-slate-800 dark:text-white font-mono @if($phpmyadmin) hover:text-cyan-600 dark:hover:text-cyan-400 @endif">
                            <span class="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center shrink-0"><svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375"/></svg></span>
                            {{ $db['name'] }}
                        </a>
                    </td>
                    <td class="px-3 py-3 text-sm text-slate-600 dark:text-slate-400 font-mono">{{ $db['username'] ?? '—' }}</td>
                    <td class="px-3 py-3">
                        @if($db['password'])
                        <div class="flex items-center gap-1.5">
                            <span class="text-sm font-mono text-slate-600 dark:text-slate-400" x-text="show ? @js($db['password']) : '••••••••'"></span>
                            <button @click="show=!show" class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" :title="show?'hide':'show'"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg></button>
                            <button @click="navigator.clipboard.writeText(@js($db['password']))" class="p-1 text-slate-400 hover:text-cyan-500" title="copy"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184"/></svg></button>
                        </div>
                        @else
                        <span class="text-sm text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-3 text-sm text-slate-600 dark:text-slate-400">{{ $db['size'] }}</td>
                    <td class="px-3 py-3 text-sm text-slate-600 dark:text-slate-400">{{ $db['tables'] }}</td>
                    <td class="px-5 py-3">
                        <div class="flex items-center justify-end gap-1 text-slate-400">
                            @if($phpmyadmin)
                            <a href="/databases/{{ urlencode($db['name']) }}/pma" target="_blank" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-white/5 hover:text-orange-500" title="Open in phpMyAdmin"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15H9v-6h2v6zm4 0h-2v-6h2v6z"/></svg></a>
                            @endif
                            <button @click="openImport('{{ $db['name'] }}')" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-white/5 hover:text-blue-500" title="Import .sql"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg></button>
                            <button @click="openBackup('{{ $db['name'] }}')" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-white/5 hover:text-emerald-500" title="Backup"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg></button>
                            <button @click="openPerm('{{ $db['name'] }}')" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-white/5 hover:text-violet-500" title="Permission"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/></svg></button>
                            <button @click="openPwd('{{ $db['name'] }}')" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-white/5 hover:text-amber-500" title="Change password"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg></button>
                            <button @click="dropDb('{{ $db['name'] }}')" class="p-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-500/10 hover:text-red-500" title="Delete"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79"/></svg></button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-5 py-10 text-center text-sm text-slate-400 dark:text-slate-500">No databases.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Users table --}}
    <div x-show="tab==='users'" x-cloak class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 overflow-hidden">
        <table class="w-full">
            <thead><tr class="border-b border-slate-200 dark:border-slate-800/60">
                <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Username</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Host</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Privileges</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/40">
                @forelse($users as $user)
                <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02]">
                    <td class="px-5 py-4 text-sm font-semibold text-slate-800 dark:text-white font-mono">{{ $user['username'] }}</td>
                    <td class="px-5 py-4 text-sm text-slate-600 dark:text-slate-400 font-mono">{{ $user['host'] }}</td>
                    <td class="px-5 py-4"><span class="text-xs px-2 py-1 rounded-full {{ $user['privileges'] === 'ALL PRIVILEGES' ? 'bg-violet-50 dark:bg-violet-500/10 text-violet-600 dark:text-violet-400' : 'bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400' }}">{{ $user['privileges'] }}</span></td>
                    <td class="px-5 py-4 text-right"><button @click="dropUser('{{ $user['username'] }}','{{ $user['host'] }}')" class="p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-500/10 text-slate-400 hover:text-red-500" title="Drop user"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79"/></svg></button></td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-5 py-10 text-center text-sm text-slate-400 dark:text-slate-500">No users.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- hidden forms --}}
    <form x-ref="dropDbForm" method="POST" action="" class="hidden">@csrf @method('DELETE')<input type="hidden" name="permanent" x-ref="dropPermanent" value="0"></form>
    <form x-ref="dropUserForm" method="POST" action="/databases/users/drop" class="hidden">@csrf<input type="hidden" name="username" x-ref="duUser"><input type="hidden" name="host" x-ref="duHost"></form>
    <form x-ref="syncForm" method="POST" action="/databases-sync" class="hidden">@csrf</form>

    {{-- Root Password Modal --}}
    <div x-show="rp.open" x-cloak x-transition.opacity class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="rp.open=false">
        <div class="bg-white dark:bg-surface-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-md">
            <div class="flex items-center justify-between p-6 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white">MySQL <span class="font-mono text-amber-500">{{ $adminUser }}</span> password</h3>
                <button @click="rp.open=false" class="text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <form method="POST" action="/databases-root-password" class="p-6 space-y-4">
                @csrf
                <div class="flex gap-1">
                    <input type="text" name="password" x-model="rp.value" minlength="8" placeholder="New password (min 8 chars)" required class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white font-mono text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                    <button type="button" @click="rp.value=gen()" class="px-3 rounded-xl bg-slate-100 dark:bg-white/10 text-slate-500 text-xs shrink-0" title="Generate">🎲</button>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">This changes the password of the MySQL admin account the panel connects with, and saves it to <code class="font-mono">.env</code>. Anything else using the old password — other apps, scripts, cron jobs — will stop connecting.</p>
                <div class="flex gap-3">
                    <button type="submit" :disabled="rp.value.length < 8" class="flex-1 px-4 py-2.5 bg-gradient-to-r from-amber-500 to-orange-600 text-white font-semibold rounded-xl text-sm disabled:opacity-40 disabled:cursor-not-allowed">Change password</button>
                    <button type="button" @click="rp.open=false" class="px-4 py-2.5 bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-400 rounded-xl text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Recycle Bin Modal --}}
    <div x-show="rb.open" x-cloak x-transition.opacity class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="rb.open=false">
        <div class="bg-white dark:bg-surface-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-2xl flex flex-col" style="max-height:85vh">
            <div class="flex items-center justify-between p-5 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white">Recycle Bin</h3>
                <button @click="rb.open=false" class="text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <div class="overflow-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0"><tr class="bg-slate-50 dark:bg-white/5">
                        <th class="text-left px-5 py-2.5 text-xs font-semibold text-slate-500 dark:text-slate-400">Database</th>
                        <th class="text-left px-3 py-2.5 text-xs font-semibold text-slate-500 dark:text-slate-400">Size</th>
                        <th class="text-left px-3 py-2.5 text-xs font-semibold text-slate-500 dark:text-slate-400">Deleted</th>
                        <th class="text-right px-5 py-2.5 text-xs font-semibold text-slate-500 dark:text-slate-400">Operate</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/40">
                        <template x-for="i in rb.items" :key="i.id">
                            <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02]">
                                <td class="px-5 py-2.5 font-mono text-xs text-slate-700 dark:text-slate-300" x-text="i.db"></td>
                                <td class="px-3 py-2.5 text-xs text-slate-500" x-text="i.size"></td>
                                <td class="px-3 py-2.5 text-xs text-slate-500" x-text="i.deleted_at"></td>
                                <td class="px-5 py-2.5 text-right whitespace-nowrap">
                                    <button @click="rbRestore(i)" class="text-xs font-medium text-cyan-600 dark:text-cyan-400 hover:underline">Restore</button>
                                    <a :href="'/databases-recycle/download?id='+encodeURIComponent(i.id)" class="text-xs font-medium text-slate-500 hover:underline ml-2">Download</a>
                                    <button @click="rbPurge(i)" class="text-xs font-medium text-red-500 hover:underline ml-2">Delete forever</button>
                                </td>
                            </tr>
                        </template>
                        <template x-if="!rb.items.length"><tr><td colspan="4" class="px-5 py-8 text-center text-sm text-slate-400">The recycle bin is empty.</td></tr></template>
                    </tbody>
                </table>
            </div>
            <p class="px-5 py-3 border-t border-slate-200 dark:border-slate-700 text-xs text-slate-400">Deleted databases are kept here as compressed dumps until you delete them forever.</p>
        </div>
    </div>

    {{-- Create Modal --}}
    <div x-show="showCreate" x-cloak x-transition.opacity class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showCreate=false">
        <div class="bg-white dark:bg-surface-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-lg">
            <div class="flex items-center justify-between p-6 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white" x-text="tab==='databases' ? 'Create Database' : 'Add User'"></h3>
                <button @click="showCreate=false" class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-white/10 text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <form method="POST" action="/databases" class="p-6 space-y-4">
                @csrf
                <template x-if="tab==='databases'">
                    <div class="space-y-4">
                        <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Database Name</label>
                            <input type="text" name="name" x-model="c.name" @input="if(!c.userEdited)c.username=c.name" placeholder="my_database" required class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white font-mono focus:outline-none focus:ring-2 focus:ring-cyan-500"></div>
                        <div class="grid grid-cols-2 gap-3">
                            <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Username</label>
                                <input type="text" name="username" x-model="c.username" @input="c.userEdited=true" placeholder="(same as db)" class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white font-mono text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500"></div>
                            <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Password</label>
                                <div class="flex gap-1">
                                    <input type="text" name="password" x-model="c.password" placeholder="(auto-generate)" class="w-full px-3 py-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white font-mono text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500">
                                    <button type="button" @click="c.password=gen()" class="px-2 rounded-xl bg-slate-100 dark:bg-white/10 text-slate-500 text-xs shrink-0" title="Generate">🎲</button>
                                </div></div>
                        </div>
                        <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Character Set</label>
                            <select name="charset" class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-cyan-500"><option value="utf8mb4">utf8mb4 (Recommended)</option><option value="utf8">utf8</option><option value="latin1">latin1</option></select></div>
                        <p class="text-xs text-slate-400">A dedicated user with full access to this database will be created. Password auto-generates if left blank.</p>
                    </div>
                </template>
                <template x-if="tab==='users'">
                    <div class="space-y-4">
                        <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Username</label><input type="text" name="username" placeholder="db_user" class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white font-mono focus:outline-none focus:ring-2 focus:ring-cyan-500"></div>
                        <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Password</label><input type="password" name="password" placeholder="Strong password" class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-cyan-500"></div>
                    </div>
                </template>
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="flex-1 px-4 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-semibold rounded-xl text-sm" x-text="tab==='databases' ? 'Create Database' : 'Add User'"></button>
                    <button type="button" @click="showCreate=false" class="px-4 py-2.5 bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-400 font-medium rounded-xl text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Import Modal --}}
    <div x-show="imp.open" x-cloak x-transition.opacity class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="imp.open=false">
        <div class="bg-white dark:bg-surface-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-md">
            <div class="flex items-center justify-between p-6 border-b border-slate-200 dark:border-slate-700"><h3 class="text-lg font-bold text-slate-800 dark:text-white">Import into <span class="font-mono text-cyan-500" x-text="imp.db"></span></h3><button @click="imp.open=false" class="text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button></div>
            <form :action="'/databases/'+encodeURIComponent(imp.db)+'/import'" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                @csrf
                <input type="file" name="file" accept=".sql,.txt,.gz,.zip,.tgz" required class="w-full text-sm text-slate-600 dark:text-slate-300 file:mr-3 file:px-4 file:py-2 file:rounded-lg file:border-0 file:bg-cyan-500 file:text-white file:text-sm file:font-medium">
                <p class="text-xs text-slate-400">Supports .sql, .gz, .tar.gz, .zip (max 500 MB). It will run against this database.</p>
                <div class="flex gap-3"><button type="submit" class="flex-1 px-4 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-semibold rounded-xl text-sm">Import</button><button type="button" @click="imp.open=false" class="px-4 py-2.5 bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-400 rounded-xl text-sm">Cancel</button></div>
            </form>
        </div>
    </div>

    {{-- Change Password Modal --}}
    <div x-show="pwd.open" x-cloak x-transition.opacity class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="pwd.open=false">
        <div class="bg-white dark:bg-surface-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-md">
            <div class="flex items-center justify-between p-6 border-b border-slate-200 dark:border-slate-700"><h3 class="text-lg font-bold text-slate-800 dark:text-white">Change password — <span class="font-mono text-cyan-500" x-text="pwd.db"></span></h3><button @click="pwd.open=false" class="text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button></div>
            <form :action="'/databases/'+encodeURIComponent(pwd.db)+'/password'" method="POST" class="p-6 space-y-4">
                @csrf @method('PUT')
                <div class="flex gap-1"><input type="text" name="password" x-model="pwd.value" placeholder="New password" required class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white font-mono text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500"><button type="button" @click="pwd.value=gen()" class="px-3 rounded-xl bg-slate-100 dark:bg-white/10 text-slate-500 text-xs shrink-0">🎲</button></div>
                <div class="flex gap-3"><button type="submit" class="flex-1 px-4 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-semibold rounded-xl text-sm">Update Password</button><button type="button" @click="pwd.open=false" class="px-4 py-2.5 bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-400 rounded-xl text-sm">Cancel</button></div>
            </form>
        </div>
    </div>

    {{-- Permission Modal --}}
    <div x-show="perm.open" x-cloak x-transition.opacity class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="perm.open=false">
        <div class="bg-white dark:bg-surface-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-lg">
            <div class="flex items-center justify-between p-6 border-b border-slate-200 dark:border-slate-700"><h3 class="text-lg font-bold text-slate-800 dark:text-white">Permissions — <span class="font-mono text-violet-500" x-text="perm.username"></span></h3><button @click="perm.open=false" class="text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button></div>
            <div class="p-6 space-y-4">
                <div><p class="text-xs font-semibold text-slate-500 uppercase mb-2">Current grants</p>
                    <div class="space-y-1 max-h-40 overflow-auto">
                        <template x-for="g in perm.grants" :key="g"><code class="block text-[11px] font-mono text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-black/20 rounded px-2 py-1 break-all" x-text="g"></code></template>
                        <template x-if="!perm.grants.length"><p class="text-sm text-slate-400">No grants found.</p></template>
                    </div>
                </div>
                <div><p class="text-xs font-semibold text-slate-500 uppercase mb-2">Grant access to another database</p>
                    <div class="flex gap-2">
                        <select x-model="perm.newDb" class="flex-1 px-3 py-2 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white text-sm">
                            <template x-for="d in perm.databases" :key="d"><option :value="d" x-text="d"></option></template>
                        </select>
                        <button @click="doGrant('grant')" class="px-3 py-2 rounded-xl bg-emerald-500 text-white text-xs font-semibold">Grant</button>
                        <button @click="doGrant('revoke')" class="px-3 py-2 rounded-xl bg-red-500 text-white text-xs font-semibold">Revoke</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Backup Manager Modal --}}
    <div x-show="bk.open" x-cloak x-transition.opacity class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="bk.open=false">
        <div class="bg-white dark:bg-surface-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-2xl flex flex-col" style="max-height:85vh">
            <div class="flex items-center justify-between p-5 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white">Backups — <span class="font-mono text-emerald-500" x-text="bk.db"></span></h3>
                <div class="flex items-center gap-2">
                    <button @click="doBackup()" :disabled="bk.busy" class="flex items-center gap-1.5 px-4 py-2 rounded-lg bg-gradient-to-r from-emerald-500 to-teal-600 text-white text-sm font-semibold disabled:opacity-50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        <span x-text="bk.busy ? 'Backing up…' : 'Backup now'"></span>
                    </button>
                    <button @click="bk.open=false" class="text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
                </div>
            </div>
            <div class="overflow-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0"><tr class="bg-slate-50 dark:bg-white/5">
                        <th class="text-left px-5 py-2.5 text-xs font-semibold text-slate-500 dark:text-slate-400">File</th>
                        <th class="text-left px-3 py-2.5 text-xs font-semibold text-slate-500 dark:text-slate-400">Size</th>
                        <th class="text-left px-3 py-2.5 text-xs font-semibold text-slate-500 dark:text-slate-400">Time</th>
                        <th class="text-right px-5 py-2.5 text-xs font-semibold text-slate-500 dark:text-slate-400">Operate</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/40">
                        <template x-for="f in bk.files" :key="f.name">
                            <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02]">
                                <td class="px-5 py-2.5 font-mono text-xs text-slate-700 dark:text-slate-300 max-w-xs truncate" x-text="f.name"></td>
                                <td class="px-3 py-2.5 text-xs text-slate-500" x-text="f.size"></td>
                                <td class="px-3 py-2.5 text-xs text-slate-500" x-text="f.time"></td>
                                <td class="px-5 py-2.5 text-right whitespace-nowrap">
                                    <button @click="doRestore(f.name)" class="text-xs font-medium text-cyan-600 dark:text-cyan-400 hover:underline">Recover</button>
                                    <a :href="'/databases/'+encodeURIComponent(bk.db)+'/backups/download?file='+encodeURIComponent(f.name)" class="text-xs font-medium text-slate-500 hover:underline ml-2">Download</a>
                                    <button @click="doDeleteBackup(f.name)" class="text-xs font-medium text-red-500 hover:underline ml-2">Delete</button>
                                </td>
                            </tr>
                        </template>
                        <template x-if="!bk.files.length"><tr><td colspan="4" class="px-5 py-8 text-center text-sm text-slate-400">No backups yet. Click “Backup now”.</td></tr></template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Two-step Delete Modal --}}
    <div x-show="del.open" x-cloak x-transition.opacity class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="del.open=false">
        <div class="bg-white dark:bg-surface-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-md">
            <div class="flex items-center gap-3 p-5 border-b border-slate-200 dark:border-slate-700">
                <div class="w-9 h-9 rounded-full bg-red-100 dark:bg-red-500/15 flex items-center justify-center shrink-0"><svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg></div>
                <h3 class="text-lg font-bold text-slate-800 dark:text-white">Delete database</h3>
            </div>
            <div class="p-5 space-y-3">
                <template x-if="!del.permanent">
                    <p class="text-sm text-slate-600 dark:text-slate-300">The database will be dumped into the <strong>recycle bin</strong> and then dropped. You can restore it later. Type <code class="px-1 rounded bg-slate-100 dark:bg-white/10 text-red-500" x-text="del.db"></code> to confirm.</p>
                </template>
                <template x-if="del.permanent">
                    <p class="text-sm text-slate-600 dark:text-slate-300">The data will be <strong class="text-red-500">completely deleted and cannot be recovered</strong>. Type <code class="px-1 rounded bg-slate-100 dark:bg-white/10 text-red-500" x-text="del.db"></code> to confirm.</p>
                </template>
                <input type="text" x-model="del.confirm" @keydown.enter="confirmDrop()" placeholder="Type the database name" class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white font-mono text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400 select-none">
                    <input type="checkbox" x-model="del.permanent" class="rounded border-slate-300 dark:border-slate-600 text-red-500 focus:ring-red-500">
                    Skip the recycle bin — delete permanently
                </label>
                <div class="flex justify-end gap-2 pt-1">
                    <button @click="del.open=false" class="px-4 py-2 bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-400 rounded-xl text-sm">Cancel</button>
                    <button @click="confirmDrop()" :disabled="del.confirm !== del.db" class="px-4 py-2 bg-red-500 text-white font-semibold rounded-xl text-sm disabled:opacity-40 disabled:cursor-not-allowed">Delete</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function databasePage() {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    return {
        tab: 'databases', showCreate: false,
        c: { name: '', username: '', password: '', userEdited: false },
        imp: { open: false, db: '' },
        pwd: { open: false, db: '', value: '' },
        perm: { open: false, db: '', username: '', grants: [], databases: [], newDb: '' },
        bk: { open: false, db: '', files: [], busy: false },
        del: { open: false, db: '', confirm: '', permanent: false },
        rp: { open: false, value: '' },
        rb: { open: false, items: [], count: {{ $recycled }} },

        gen() { return Array.from(crypto.getRandomValues(new Uint8Array(12))).map(b => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'[b % 62]).join(''); },

        async svcAction(action) {
            if (action === 'stop' && !confirm('Stop MySQL? The panel will lose its database connection.')) return;
            try {
                const res = await fetch('/api/services/action', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ service: 'mysql', action }),
                });
                const d = await res.json();
                if (!d.success) throw new Error(d.message || 'Command failed');
                alert(d.message);
                if (action !== 'stop') location.reload();
            } catch (e) { alert(e.message); }
        },

        async openRecycle() {
            this.rb.open = true;
            await this.loadRecycled();
        },
        async loadRecycled() {
            try {
                const res = await fetch('/databases-recycle', { headers: { 'Accept': 'application/json' } });
                const d = await res.json();
                this.rb.items = d.items || [];
                this.rb.count = this.rb.items.length;
            } catch (e) { alert(e.message); }
        },
        async rbCall(path, id) {
            const res = await fetch('/databases-recycle' + path, {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ id }),
            });
            const d = await res.json(); if (!res.ok) throw new Error(d.error || 'Error'); return d;
        },
        async rbRestore(item) {
            if (!confirm('Restore database "' + item.db + '"?')) return;
            try {
                const d = await this.rbCall('/restore', item.id);
                this.rb.items = d.items || []; this.rb.count = this.rb.items.length;
                location.reload();
            } catch (e) { alert(e.message); }
        },
        async rbPurge(item) {
            if (!confirm('Permanently delete the dump of "' + item.db + '"? This cannot be undone.')) return;
            try {
                const d = await this.rbCall('/purge', item.id);
                this.rb.items = d.items || []; this.rb.count = this.rb.items.length;
            } catch (e) { alert(e.message); }
        },
        openImport(db) { this.imp = { open: true, db }; },
        openPwd(db) { this.pwd = { open: true, db, value: this.gen() }; },
        async openPerm(db) {
            this.perm = { open: true, db, username: '…', grants: [], databases: [], newDb: '' };
            try {
                const res = await fetch(`/databases/${encodeURIComponent(db)}/permission`, { headers: { 'Accept': 'application/json' } });
                const d = await res.json();
                if (!res.ok) throw new Error(d.error);
                this.perm.username = d.username; this.perm.grants = d.grants; this.perm.databases = d.databases; this.perm.newDb = d.databases[0] || '';
            } catch (e) { this.perm.username = '(error)'; alert(e.message); }
        },
        async doGrant(action) {
            try {
                const res = await fetch(`/databases/${encodeURIComponent(this.perm.db)}/grant`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: JSON.stringify({ db: this.perm.newDb, action }) });
                const d = await res.json(); if (!res.ok) throw new Error(d.error);
                await this.openPerm(this.perm.db);
            } catch (e) { alert(e.message); }
        },
        dropDb(name) { this.del = { open: true, db: name, confirm: '', permanent: false }; },
        confirmDrop() {
            if (this.del.confirm !== this.del.db) return;
            const f = this.$refs.dropDbForm;
            this.$refs.dropPermanent.value = this.del.permanent ? '1' : '0';
            f.action = '/databases/' + encodeURIComponent(this.del.db);
            f.submit();
        },

        async openBackup(db) {
            this.bk = { open: true, db, files: [], busy: false };
            await this.loadBackups();
        },
        async loadBackups() {
            try {
                const res = await fetch(`/databases/${encodeURIComponent(this.bk.db)}/backups`, { headers: { 'Accept': 'application/json' } });
                const d = await res.json(); this.bk.files = d.backups || [];
            } catch (e) { alert(e.message); }
        },
        async bkCall(path, body) {
            const res = await fetch(`/databases/${encodeURIComponent(this.bk.db)}/backups${path}`, {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify(body || {})
            });
            const d = await res.json(); if (!res.ok) throw new Error(d.error || 'Error'); return d;
        },
        async doBackup() {
            this.bk.busy = true;
            try { const d = await this.bkCall('', {}); this.bk.files = d.backups || []; }
            catch (e) { alert(e.message); } this.bk.busy = false;
        },
        async doRestore(file) {
            if (!confirm('Recover from "' + file + '"? Current data in this database will be overwritten.')) return;
            try { await this.bkCall('/restore', { file }); alert('Database recovered from ' + file); }
            catch (e) { alert(e.message); }
        },
        async doDeleteBackup(file) {
            if (!confirm('Delete backup "' + file + '"?')) return;
            try { const d = await this.bkCall('/delete', { file }); this.bk.files = d.backups || []; }
            catch (e) { alert(e.message); }
        },
        dropUser(u, h) { if (!confirm('Drop user "' + u + '@' + h + '"?')) return; this.$refs.duUser.value = u; this.$refs.duHost.value = h; this.$refs.dropUserForm.submit(); },
    }
}
</script>
@endpush
