<!DOCTYPE html>
<html lang="en" class="h-full" x-data="{ dark: localStorage.getItem('theme') !== 'light' }" x-init="$watch('dark', v => { localStorage.setItem('theme', v ? 'dark' : 'light') })" :class="{ 'dark': dark }">
<head>
    {{-- Apply the saved theme BEFORE first paint to avoid a white flash on navigation. --}}
    <script>
        (function () {
            try {
                if (localStorage.getItem('theme') !== 'light') {
                    document.documentElement.classList.add('dark');
                    document.documentElement.style.backgroundColor = '#010410';
                }
            } catch (e) {}
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>NexPanel — @yield('title', 'Dashboard')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: { 50: '#eefbf4', 100: '#d6f5e4', 400: '#34d399', 500: '#10b981', 600: '#059669' },
                        surface: {
                            50: '#f8fafc', 100: '#f1f5f9', 200: '#e2e8f0', 300: '#cbd5e1',
                            700: '#1e293b', 800: '#0f172a', 900: '#020617', 950: '#010410'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Inter', system-ui, sans-serif; }
        .glass { backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); }
        .gradient-border { background: linear-gradient(135deg, #06b6d4, #10b981, #3b82f6); }
        .stat-glow-blue { box-shadow: 0 0 30px -5px rgba(59, 130, 246, 0.15); }
        .stat-glow-purple { box-shadow: 0 0 30px -5px rgba(168, 85, 247, 0.15); }
        .stat-glow-amber { box-shadow: 0 0 30px -5px rgba(245, 158, 11, 0.15); }
        .stat-glow-emerald { box-shadow: 0 0 30px -5px rgba(16, 185, 129, 0.15); }
        .sidebar-item { transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
        .sidebar-item:hover { transform: translateX(4px); }
        .sidebar-item.active { background: linear-gradient(135deg, rgba(6,182,212,0.15), rgba(59,130,246,0.15)); border-left: 3px solid #06b6d4; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(148,163,184,0.2); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(148,163,184,0.4); }
        [x-cloak] { display: none !important; }
        /* Theme-toggle knob position is driven by the .dark class (set pre-paint),
           not Alpine — so it never slides on page load, only when toggled. */
        .theme-knob { transform: translateX(0); }
        html.dark .theme-knob { transform: translateX(1.75rem); }
    </style>
    @stack('styles')
</head>
<body class="h-full bg-slate-50 dark:bg-surface-950 text-slate-800 dark:text-slate-200 antialiased transition-colors duration-300">

<div class="flex h-full">

    {{-- ===== SIDEBAR ===== --}}
    <aside class="w-[260px] bg-white/80 dark:bg-surface-900/80 glass border-r border-slate-200 dark:border-slate-800/60 flex flex-col shrink-0">

        {{-- Logo --}}
        <div class="h-[72px] flex items-center px-5 border-b border-slate-200 dark:border-slate-800/60 gap-3">
            <img src="/img/logo.png" alt="NexPanel" class="w-9 h-9 rounded-lg object-contain" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
            <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-cyan-500 to-blue-600 items-center justify-center shrink-0 hidden">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2" />
                </svg>
            </div>
            <div>
                <span class="text-lg font-bold bg-gradient-to-r from-cyan-500 to-blue-600 bg-clip-text text-transparent">NexPanel</span>
                <p class="text-[10px] text-slate-400 dark:text-slate-500 -mt-0.5 font-medium">Server Management</p>
            </div>
        </div>

        {{-- Nav --}}
        <nav class="flex-1 px-3 py-5 space-y-1 overflow-y-auto">

            <p class="px-3 mb-3 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-[0.15em]">Main</p>

            <a href="/dashboard" class="sidebar-item {{ request()->is('dashboard') ? 'active' : '' }} flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium {{ request()->is('dashboard') ? 'text-cyan-600 dark:text-cyan-400' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white' }}">
                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>
                <span>Dashboard</span>
            </a>

            <a href="/websites" class="sidebar-item {{ request()->is('websites*') ? 'active' : '' }} flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium {{ request()->is('websites*') ? 'text-cyan-600 dark:text-cyan-400' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white' }}">
                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/></svg>
                <span>Websites</span>
            </a>

            <a href="/databases" class="sidebar-item {{ request()->is('databases*') ? 'active' : '' }} flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium {{ request()->is('databases*') ? 'text-cyan-600 dark:text-cyan-400' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white' }}">
                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/></svg>
                <span>Databases</span>
            </a>

            <a href="/ssl" class="sidebar-item {{ request()->is('ssl*') ? 'active' : '' }} flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium {{ request()->is('ssl*') ? 'text-cyan-600 dark:text-cyan-400' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white' }}">
                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                <span>SSL Certificates</span>
            </a>

            <a href="/files" class="sidebar-item {{ request()->is('files*') ? 'active' : '' }} flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium {{ request()->is('files*') ? 'text-cyan-600 dark:text-cyan-400' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white' }}">
                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/></svg>
                <span>File Manager</span>
            </a>

            <p class="px-3 mt-6 mb-3 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-[0.15em]">Tools</p>

            <a href="/cron" class="sidebar-item {{ request()->is('cron*') ? 'active' : '' }} flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium {{ request()->is('cron*') ? 'text-cyan-600 dark:text-cyan-400' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white' }}">
                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Cron Jobs</span>
            </a>

            <a href="/terminal" class="sidebar-item {{ request()->is('terminal*') ? 'active' : '' }} flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium {{ request()->is('terminal*') ? 'text-cyan-600 dark:text-cyan-400' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white' }}">
                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z"/></svg>
                <span>Web Terminal</span>
            </a>

            <a href="/ai" class="sidebar-item {{ request()->is('ai*') ? 'active' : '' }} flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium {{ request()->is('ai*') ? 'text-cyan-600 dark:text-cyan-400' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white' }}">
                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z"/></svg>
                <span>AI Assistant</span>
                <span class="ml-auto px-2 py-0.5 text-[10px] font-bold rounded-full bg-gradient-to-r from-cyan-500 to-blue-500 text-white">AI</span>
            </a>

            <p class="px-3 mt-6 mb-3 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-[0.15em]">System</p>

            <a href="/services" class="sidebar-item {{ request()->is('services*') ? 'active' : '' }} flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium {{ request()->is('services*') ? 'text-cyan-600 dark:text-cyan-400' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white' }}">
                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9"/></svg>
                <span>Service Control</span>
            </a>

            <a href="/security" class="sidebar-item {{ request()->is('security*') ? 'active' : '' }} flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium {{ request()->is('security*') ? 'text-cyan-600 dark:text-cyan-400' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white' }}">
                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                <span>Security</span>
            </a>

            <a href="/logs" class="sidebar-item {{ request()->is('logs*') ? 'active' : '' }} flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium {{ request()->is('logs*') ? 'text-cyan-600 dark:text-cyan-400' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white' }}">
                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                <span>Logs</span>
            </a>

            <a href="/notifications" class="sidebar-item {{ request()->is('notifications*') ? 'active' : '' }} flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium {{ request()->is('notifications*') ? 'text-cyan-600 dark:text-cyan-400' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white' }}">
                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
                <span>Notifications</span>
            </a>

            <a href="/settings" class="sidebar-item {{ request()->is('settings*') ? 'active' : '' }} flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium {{ request()->is('settings*') ? 'text-cyan-600 dark:text-cyan-400' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white' }}">
                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span>Settings</span>
            </a>
        </nav>

        {{-- User Footer with Logout --}}
        <div class="border-t border-slate-200 dark:border-slate-800/60 p-4" x-data="{ showMenu: false }">
            <div class="flex items-center gap-3 relative">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center text-sm font-bold text-white shrink-0">
                    {{ substr(Auth::user()->name ?? 'A', 0, 1) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-slate-800 dark:text-white truncate">{{ Auth::user()->name ?? 'Admin' }}</p>
                    <p class="text-xs text-slate-400 dark:text-slate-500 truncate">{{ Auth::user()->email ?? 'admin' }}</p>
                </div>
                <button @click="showMenu = !showMenu" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-white/10 text-slate-400 dark:text-slate-500 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z"/></svg>
                </button>

                {{-- Dropdown Menu --}}
                <div x-show="showMenu" x-cloak @click.away="showMenu = false" x-transition
                     class="absolute bottom-full right-0 mb-2 w-48 bg-white dark:bg-surface-800 rounded-xl shadow-lg border border-slate-200 dark:border-slate-700 py-1 z-50">
                    <a href="/profile" class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                        Profile
                    </a>
                    <a href="/settings" class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Settings
                    </a>
                    <div class="border-t border-slate-200 dark:border-slate-700 my-1"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full flex items-center gap-2 px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </aside>

    {{-- ===== MAIN CONTENT ===== --}}
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

        {{-- Topbar --}}
        <header class="h-[72px] bg-white/60 dark:bg-surface-900/60 glass border-b border-slate-200 dark:border-slate-800/60 flex items-center justify-between px-6 shrink-0">
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-bold text-slate-800 dark:text-white">@yield('title', 'Dashboard')</h1>
                @hasSection('subheader')
                    <span class="text-sm text-slate-400 dark:text-slate-500">— @yield('subheader')</span>
                @endif
            </div>

            <div class="flex items-center gap-4">
                {{-- Update available --}}
                <div x-data="updateWidget()" x-init="check(); setInterval(() => { if (!open) check() }, 900000)" x-cloak>
                    <button x-show="available" @click="open = true"
                            class="relative flex items-center gap-2 px-3 py-1.5 rounded-full bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 text-amber-600 dark:text-amber-400 text-xs font-semibold hover:bg-amber-100 dark:hover:bg-amber-500/20 transition-colors">
                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12a7.5 7.5 0 0013.5 4.5m1.5-4.5A7.5 7.5 0 006 7.5m0 0V3m0 4.5H1.5m18 4.5v4.5m0-4.5h4.5"/></svg>
                        อัปเดต <span x-text="behind"></span>
                    </button>

                    {{-- Update modal (teleported to body so .glass ancestors can't clip the fixed overlay) --}}
                    <template x-teleport="body">
                    <div x-show="open" x-cloak @keydown.escape.window="!running && (open = false)"
                         class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                        <div class="absolute inset-0 bg-black/50" @click="!running && (open = false)"></div>
                        <div class="relative w-full max-w-lg bg-white dark:bg-surface-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                            <div class="flex items-center justify-between p-4 border-b border-slate-200 dark:border-slate-700">
                                <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                                    <span class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center">
                                        <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                                    </span>
                                    มีอัปเดตใหม่ของ NexPanel
                                </h3>
                                <button @click="!running && (open = false)" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-white/10 text-slate-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>

                            <div class="p-5">
                                {{-- Before starting: friendly list of what's new --}}
                                <template x-if="!started">
                                    <div>
                                        <p class="text-sm text-slate-500 dark:text-slate-400 mb-3">
                                            มีการอัปเดต <span class="font-semibold text-slate-700 dark:text-slate-200" x-text="behind"></span> รายการ
                                        </p>
                                        <div class="space-y-2">
                                            <template x-for="c in changes" :key="c.label">
                                                <div class="flex items-center gap-3 p-2.5 rounded-xl bg-slate-50 dark:bg-white/5 text-sm">
                                                    <span class="text-lg leading-none" x-text="c.icon"></span>
                                                    <span class="text-slate-700 dark:text-slate-200" x-text="c.label"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                {{-- While updating: spinner + progress ring, no technical log --}}
                                <template x-if="started">
                                    <div class="py-6 flex flex-col items-center text-center">
                                        <div class="relative w-24 h-24 mb-4">
                                            <svg class="w-24 h-24 -rotate-90" viewBox="0 0 100 100">
                                                <circle cx="50" cy="50" r="44" fill="none" stroke-width="8" class="stroke-slate-100 dark:stroke-white/10"/>
                                                <circle cx="50" cy="50" r="44" fill="none" stroke-width="8" stroke-linecap="round"
                                                        class="stroke-cyan-500 transition-all duration-700"
                                                        stroke-dasharray="276" :stroke-dashoffset="276 - (276 * percent / 100)"/>
                                            </svg>
                                            <div class="absolute inset-0 flex items-center justify-center">
                                                <svg x-show="running" class="w-6 h-6 text-cyan-500 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                                <span x-show="done && success" class="text-3xl">✅</span>
                                                <span x-show="done && !success" class="text-3xl">❌</span>
                                            </div>
                                        </div>
                                        <div class="text-2xl font-extrabold text-slate-800 dark:text-white mb-1" x-text="percent + '%'"></div>
                                        <p class="text-sm font-medium text-slate-600 dark:text-slate-300" x-text="stageLabel"></p>
                                        <p x-show="running" class="text-xs text-slate-400 mt-2">อย่าปิดหน้านี้จนกว่าจะเสร็จ</p>
                                        <button x-show="done && !success" @click="showLog = !showLog" class="mt-3 text-xs text-cyan-500 hover:underline">ดูรายละเอียด</button>
                                        <pre x-show="done && !success && showLog" x-cloak class="mt-2 max-h-40 w-full overflow-auto rounded-xl bg-slate-900 text-slate-100 text-[11px] leading-relaxed p-3 whitespace-pre-wrap text-left" x-text="log"></pre>
                                    </div>
                                </template>
                            </div>

                            <div class="flex items-center justify-end gap-3 p-4 border-t border-slate-200 dark:border-slate-700">
                                <button x-show="!running" @click="open = false" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-300 text-sm font-medium hover:bg-slate-200 dark:hover:bg-white/10">ปิด</button>
                                <button x-show="!started" @click="start()" class="px-5 py-2 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-600 hover:to-blue-700 text-white text-sm font-semibold shadow-lg shadow-cyan-500/25">อัปเดตเลย</button>
                            </div>
                        </div>
                    </div>
                    </template>
                </div>

                {{-- Theme Toggle --}}
                <button @click="dark = !dark" class="relative w-14 h-7 rounded-full bg-slate-200 dark:bg-slate-700 transition-colors duration-300 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 dark:focus:ring-offset-surface-900">
                    <div class="theme-knob absolute top-0.5 left-0.5 w-6 h-6 rounded-full bg-white shadow-md transition-transform duration-300 flex items-center justify-center">
                        <svg x-show="!dark" x-cloak class="w-3.5 h-3.5 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"/></svg>
                        <svg x-show="dark" x-cloak class="w-3.5 h-3.5 text-slate-600" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/></svg>
                    </div>
                </button>

                {{-- Server Status --}}
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="text-xs font-medium text-emerald-700 dark:text-emerald-400">Online</span>
                </div>
            </div>
        </header>

        {{-- Page Content --}}
        <main class="flex-1 overflow-y-auto p-6">
            @yield('content')
        </main>

    </div>
</div>

<script>
    function updateWidget() {
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        // Map update.sh stage markers → friendly Thai label + progress %.
        const STAGES = [
            { m: 'Pulling latest code',          label: 'กำลังดึงโค้ดใหม่…',   pct: 20 },
            { m: 'Updating PHP dependencies',    label: 'อัปเดตไลบรารี…',      pct: 45 },
            { m: 'Running database migrations',  label: 'อัปเดตฐานข้อมูล…',    pct: 65 },
            { m: 'Rebuilding caches',            label: 'สร้างแคชใหม่…',       pct: 82 },
            { m: 'Reloading services',           label: 'รีโหลดบริการ…',       pct: 95 },
        ];
        return {
            available: false, current: '', latest: '', behind: 0, changes: [],
            open: false, started: false, running: false, done: false, success: false,
            log: '', showLog: false, percent: 0, stageLabel: '',
            async check() {
                try {
                    const r = await fetch('/api/update/check');
                    const d = await r.json();
                    this.available = d.updateAvailable;
                    this.current = d.current; this.latest = d.latest;
                    this.behind = d.behind; this.changes = d.changes || [];
                } catch (e) { /* offline / no git — hide the widget */ }
            },
            async start() {
                this.started = true; this.running = true;
                this.percent = 8; this.stageLabel = 'กำลังเริ่มอัปเดต…';
                try {
                    const r = await fetch('/api/update/run', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    });
                    const d = await r.json();
                    if (!d.ok) { this.running = false; this.done = true; this.success = false; this.stageLabel = 'เริ่มไม่สำเร็จ'; this.log = d.message || ''; return; }
                    this.poll();
                } catch (e) { this.running = false; this.done = true; this.success = false; this.stageLabel = 'เริ่มไม่สำเร็จ'; this.log = e.message; }
            },
            async poll() {
                try {
                    const r = await fetch('/api/update/status');
                    const d = await r.json();
                    if (d.log) { this.log = d.log; this.applyStage(d.log); }
                    if (d.done) {
                        this.running = false; this.done = true; this.success = d.success;
                        this.percent = d.success ? 100 : this.percent;
                        this.stageLabel = d.success ? 'อัปเดตเสร็จสมบูรณ์ 🎉' : 'อัปเดตล้มเหลว';
                        if (d.success) setTimeout(() => location.reload(), 2500);
                        return;
                    }
                } catch (e) { /* php-fpm reload mid-update drops a poll — keep trying */ }
                setTimeout(() => this.poll(), 2000);
            },
            applyStage(log) {
                // Pick the furthest stage whose marker has appeared in the log.
                for (let i = STAGES.length - 1; i >= 0; i--) {
                    if (log.includes(STAGES[i].m)) {
                        if (STAGES[i].pct > this.percent) this.percent = STAGES[i].pct;
                        this.stageLabel = STAGES[i].label;
                        return;
                    }
                }
            },
        };
    }
</script>
@stack('scripts')
</body>
</html>
