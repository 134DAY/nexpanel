@extends('layouts.app')

@section('title', 'Databases')
@section('subheader', 'Manage MySQL databases and users')

@section('content')
<div x-data="databasePage()" class="space-y-6">

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

    {{-- Stats --}}
    <div class="grid grid-cols-4 gap-4">
        <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-5">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Databases</p>
            <p class="text-2xl font-bold text-slate-800 dark:text-white mt-1">{{ count($databases) }}</p>
        </div>
        <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-5">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Size</p>
            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">{{ $totalSize }}</p>
        </div>
        <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-5">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Users</p>
            <p class="text-2xl font-bold text-violet-600 dark:text-violet-400 mt-1">{{ count($users) }}</p>
        </div>
        <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-5">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Engine</p>
            <p class="text-2xl font-bold {{ $available ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400' }} mt-1">{{ $available ? 'MySQL' : 'Offline' }}</p>
        </div>
    </div>

    @unless($available)
    <div class="flex items-start gap-2 px-4 py-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 text-sm text-amber-600 dark:text-amber-400">
        <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
        <div>
            Cannot connect to MySQL. Set <code>DB_ADMIN_USER</code> / <code>DB_ADMIN_PASSWORD</code> in <code>.env</code> if the server is running.
            @if($connError)<div class="mt-1 font-mono text-xs opacity-80">{{ $connError }}</div>@endif
        </div>
    </div>
    @endunless

    {{-- Tabs --}}
    <div class="flex items-center justify-between">
        <div class="flex bg-slate-100 dark:bg-white/5 rounded-xl p-1">
            <button @click="tab = 'databases'" :class="tab === 'databases' ? 'bg-white dark:bg-surface-800 shadow text-slate-800 dark:text-white' : 'text-slate-500 dark:text-slate-400'" class="px-4 py-2 rounded-lg text-sm font-medium transition-all">Databases</button>
            <button @click="tab = 'users'" :class="tab === 'users' ? 'bg-white dark:bg-surface-800 shadow text-slate-800 dark:text-white' : 'text-slate-500 dark:text-slate-400'" class="px-4 py-2 rounded-lg text-sm font-medium transition-all">Users</button>
        </div>
        <button @click="showCreate = true" @disabled(!$available) class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-600 hover:to-blue-700 text-white font-semibold rounded-xl shadow-lg shadow-cyan-500/25 transition-all text-sm disabled:opacity-40 disabled:cursor-not-allowed">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            <span x-text="tab === 'databases' ? 'Create Database' : 'Add User'"></span>
        </button>
    </div>

    {{-- Databases Table --}}
    <div x-show="tab === 'databases'" class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="border-b border-slate-200 dark:border-slate-800/60">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Name</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Size</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tables</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Charset</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/40">
                @forelse($databases as $db)
                <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center">
                                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375"/></svg>
                            </div>
                            <a href="/databases/{{ urlencode($db['name']) }}/browse" class="text-sm font-semibold text-slate-800 dark:text-white font-mono hover:text-cyan-600 dark:hover:text-cyan-400 transition-colors">{{ $db['name'] }}</a>
                        </div>
                    </td>
                    <td class="px-5 py-4 text-sm text-slate-600 dark:text-slate-400">{{ $db['size'] }}</td>
                    <td class="px-5 py-4 text-sm text-slate-600 dark:text-slate-400">{{ $db['tables'] }}</td>
                    <td class="px-5 py-4 text-sm text-slate-600 dark:text-slate-400">{{ $db['charset'] }}</td>
                    <td class="px-5 py-4 text-right">
                        <div class="flex items-center justify-end gap-1">
                            <a href="/databases/{{ urlencode($db['name']) }}/browse" class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-white/5 text-slate-400 hover:text-cyan-500 transition-colors inline-block" title="Browse tables & run SQL">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z"/></svg>
                            </a>
                            <a href="/databases/{{ urlencode($db['name']) }}/backup" class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-white/5 text-slate-400 hover:text-emerald-500 transition-colors inline-block" title="Backup (mysqldump)">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                            </a>
                            <button @click="dropDb('{{ $db['name'] }}')" class="p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-500/10 text-slate-400 hover:text-red-500 transition-colors" title="Drop database">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-5 py-10 text-center text-sm text-slate-400 dark:text-slate-500">No databases.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Users Table --}}
    <div x-show="tab === 'users'" x-cloak class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="border-b border-slate-200 dark:border-slate-800/60">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Username</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Host</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Privileges</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/40">
                @forelse($users as $user)
                <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                    <td class="px-5 py-4"><span class="text-sm font-semibold text-slate-800 dark:text-white font-mono">{{ $user['username'] }}</span></td>
                    <td class="px-5 py-4 text-sm text-slate-600 dark:text-slate-400 font-mono">{{ $user['host'] }}</td>
                    <td class="px-5 py-4">
                        <span class="text-xs px-2 py-1 rounded-full {{ $user['privileges'] === 'ALL PRIVILEGES' ? 'bg-violet-50 dark:bg-violet-500/10 text-violet-600 dark:text-violet-400' : 'bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400' }}">{{ $user['privileges'] }}</span>
                    </td>
                    <td class="px-5 py-4 text-right">
                        <button @click="dropUser('{{ $user['username'] }}', '{{ $user['host'] }}')" class="p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-500/10 text-slate-400 hover:text-red-500 transition-colors" title="Drop user">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79"/></svg>
                        </button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-5 py-10 text-center text-sm text-slate-400 dark:text-slate-500">No users.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- hidden action forms --}}
    <form x-ref="dropDbForm" method="POST" action="" class="hidden">@csrf @method('DELETE')</form>
    <form x-ref="dropUserForm" method="POST" action="/databases/users/drop" class="hidden">@csrf<input type="hidden" name="username" x-ref="duUser"><input type="hidden" name="host" x-ref="duHost"></form>

    {{-- Create Modal --}}
    <div x-show="showCreate" x-cloak x-transition.opacity class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showCreate = false">
        <div class="bg-white dark:bg-surface-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-lg">
            <div class="flex items-center justify-between p-6 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white" x-text="tab === 'databases' ? 'Create Database' : 'Add User'"></h3>
                <button @click="showCreate = false" class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-white/10 text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <form method="POST" action="/databases" class="p-6 space-y-4">
                @csrf
                <template x-if="tab === 'databases'">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Database Name</label>
                            <input type="text" name="name" placeholder="my_database" required class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent font-mono">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Character Set</label>
                            <select name="charset" class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent">
                                <option value="utf8mb4" selected>utf8mb4 (Recommended)</option>
                                <option value="utf8">utf8</option>
                                <option value="latin1">latin1</option>
                            </select>
                        </div>
                    </div>
                </template>
                <template x-if="tab === 'users'">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Username</label>
                            <input type="text" name="username" placeholder="db_user" class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent font-mono">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Password</label>
                            <input type="password" name="password" placeholder="Strong password" class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent">
                        </div>
                    </div>
                </template>
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="flex-1 px-4 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-semibold rounded-xl shadow-lg shadow-cyan-500/25 transition-all text-sm" x-text="tab === 'databases' ? 'Create Database' : 'Add User'"></button>
                    <button type="button" @click="showCreate = false" class="px-4 py-2.5 bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-400 font-medium rounded-xl text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function databasePage() {
    return {
        tab: 'databases',
        showCreate: false,
        dropDb(name) {
            if (!confirm('Drop database "' + name + '"? All data will be permanently lost.')) return;
            const f = this.$refs.dropDbForm;
            f.action = '/databases/' + encodeURIComponent(name);
            f.submit();
        },
        dropUser(username, host) {
            if (!confirm('Drop user "' + username + '@' + host + '"?')) return;
            this.$refs.duUser.value = username;
            this.$refs.duHost.value = host;
            this.$refs.dropUserForm.submit();
        },
    }
}
</script>
@endpush
