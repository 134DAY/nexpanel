@extends('layouts.app')

@section('title', 'WordPress')
@section('subheader', 'One-click WordPress installer')

@section('content')
<div class="max-w-4xl space-y-6">

    @if(session('success'))
    <div class="flex items-start gap-3 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-sm">
        <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>{{ session('success') }}</span>
    </div>
    @endif
    @if(session('error'))
    <div class="flex items-start gap-3 p-4 rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 text-red-600 dark:text-red-400 text-sm">
        <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
        <span>{{ session('error') }}</span>
    </div>
    @endif

    @if(! $nginxOk || ! $mysqlOk)
    <div class="flex items-center gap-2 px-4 py-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 text-sm text-amber-600 dark:text-amber-400">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
        WordPress needs both {{ $nginxOk ? '' : 'Nginx' }}{{ (!$nginxOk && !$mysqlOk) ? ' and ' : '' }}{{ $mysqlOk ? '' : 'MySQL' }} — install/start them first.
    </div>
    @endif

    {{-- Installed WP sites --}}
    @if(count($sites))
    <div class="bg-white dark:bg-surface-800/50 border border-slate-200 dark:border-slate-800/60 rounded-2xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-800/60"><h3 class="text-sm font-bold text-slate-800 dark:text-white">Installed WordPress sites</h3></div>
        <div class="divide-y divide-slate-100 dark:divide-slate-800/40">
            @foreach($sites as $s)
            <div class="flex items-center gap-3 px-5 py-3">
                <div class="w-9 h-9 rounded-lg bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-blue-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-8.06 10c0-1.17.25-2.28.7-3.28l3.86 10.58A8.006 8.006 0 013.94 12zM12 20c-.79 0-1.55-.12-2.28-.33l2.42-7.04 2.48 6.79c.02.04.03.08.05.11A7.96 7.96 0 0112 20zm1.11-11.75c.49-.03.93-.08.93-.08.43-.05.38-.69-.05-.66 0 0-1.31.1-2.16.1-.79 0-2.13-.11-2.13-.11-.43-.02-.48.64-.05.66 0 0 .41.05.86.08l1.27 3.48-1.78 5.35-2.97-8.83c.49-.02.93-.08.93-.08.43-.05.38-.69-.05-.66 0 0-1.3.1-2.15.1-.15 0-.33 0-.52-.01A7.994 7.994 0 0112 4c2.02 0 3.86.76 5.25 2.02-.03 0-.07-.01-.11-.01-.79 0-1.35.69-1.35 1.43 0 .66.38 1.22.79 1.88.31.53.66 1.22.66 2.21 0 .69-.26 1.48-.61 2.59l-.8 2.66-2.92-8.53zm5.42 9.05l2.44-7.05c.46-1.14.61-2.05.61-2.86 0-.29-.02-.56-.05-.82.62 1.14.98 2.44.98 3.83 0 2.95-1.6 5.52-3.98 6.9z"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <a href="http://{{ $s['domain'] }}" target="_blank" class="text-sm font-semibold text-slate-800 dark:text-white hover:underline">{{ $s['domain'] }}</a>
                    <p class="text-xs text-slate-400 font-mono truncate">{{ $s['root'] }} · PHP {{ $s['php'] }}</p>
                </div>
                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $s['status']==='active' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-slate-100 text-slate-500 dark:bg-white/5' }}">{{ $s['status'] }}</span>
                <a href="http://{{ $s['domain'] }}/wp-admin" target="_blank" class="text-cyan-500 hover:underline text-xs">wp-admin</a>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Install form --}}
    <form method="POST" action="/wordpress/install" class="bg-white dark:bg-surface-800/50 border border-slate-200 dark:border-slate-800/60 rounded-2xl p-6 space-y-5">
        @csrf
        <div>
            <h3 class="text-sm font-bold text-slate-800 dark:text-white">Install a new WordPress site</h3>
            <p class="text-xs text-slate-400 mt-0.5">สร้าง database + vhost + ดาวน์โหลด WordPress core ให้อัตโนมัติในคลิกเดียว</p>
        </div>

        @error('domain') <p class="text-xs text-red-500">{{ $message }}</p> @enderror

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Domain</label>
                <input name="domain" value="{{ old('domain') }}" required placeholder="blog.example.com"
                       class="mt-1 w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500">
            </div>
            <div>
                <label class="text-xs font-medium text-slate-500 dark:text-slate-400">PHP version</label>
                <select name="php_version" class="mt-1 w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500">
                    @foreach($phpVersions as $v)<option value="{{ $v }}">PHP {{ $v }}</option>@endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Site title</label>
                <input name="title" value="{{ old('title') }}" required placeholder="My WordPress Site"
                       class="mt-1 w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500">
            </div>
            <div>
                <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Admin username</label>
                <input name="admin_user" value="{{ old('admin_user') }}" required placeholder="admin"
                       class="mt-1 w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500">
            </div>
            <div>
                <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Admin password</label>
                <input name="admin_pass" type="text" required placeholder="min 6 chars"
                       class="mt-1 w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500">
            </div>
            <div class="sm:col-span-2">
                <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Admin email</label>
                <input name="admin_email" type="email" value="{{ old('admin_email') }}" required placeholder="you@example.com"
                       class="mt-1 w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500">
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
            <input type="checkbox" name="ssl" value="1" class="rounded border-slate-300 dark:border-slate-600 text-cyan-500 focus:ring-cyan-500">
            ออก SSL (Let's Encrypt) หลังติดตั้ง — ต้องชี้โดเมนมาที่เครื่องนี้ก่อน
        </label>

        <div class="flex items-center justify-between pt-1">
            <p class="text-xs text-slate-400">ติดตั้งที่ <code>/var/www/&lt;domain&gt;</code> · DB สร้างอัตโนมัติชื่อ <code>wp_&lt;domain&gt;</code></p>
            <button type="submit" @disabled(! $nginxOk || ! $mysqlOk)
                    class="px-6 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-600 hover:to-blue-700 text-white font-semibold rounded-xl shadow-lg shadow-cyan-500/25 text-sm disabled:opacity-40">
                Install WordPress
            </button>
        </div>
    </form>
</div>
@endsection
