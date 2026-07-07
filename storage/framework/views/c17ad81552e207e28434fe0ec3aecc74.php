<!DOCTYPE html>
<html lang="en" class="h-full" x-data="{ dark: localStorage.getItem('theme') !== 'light' }" x-init="$watch('dark', v => { localStorage.setItem('theme', v ? 'dark' : 'light') })" :class="{ 'dark': dark }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexPanel — Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        surface: { 800: '#0f172a', 900: '#020617', 950: '#010410' }
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .glass { backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); }
        .glow-dark { box-shadow: 0 0 80px -10px rgba(6, 182, 212, 0.12); }
        .glow-light { box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body class="h-full bg-slate-50 dark:bg-surface-950 text-slate-800 dark:text-white antialiased transition-colors duration-300">

    
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-40 -right-40 w-[500px] h-[500px] bg-cyan-500/5 dark:bg-cyan-500/5 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-40 -left-40 w-[500px] h-[500px] bg-blue-500/5 dark:bg-blue-500/5 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-cyan-400/3 dark:bg-cyan-500/3 rounded-full blur-3xl"></div>
    </div>

    
    <div class="fixed top-6 right-6 z-50">
        <button @click="dark = !dark"
                class="p-2.5 rounded-xl bg-white/80 dark:bg-surface-800/80 glass border border-slate-200 dark:border-slate-700/50 shadow-sm hover:shadow-md transition-all duration-200"
                :title="dark ? 'Switch to Light' : 'Switch to Dark'">
            <svg x-show="dark" class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/></svg>
            <svg x-show="!dark" class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/></svg>
        </button>
    </div>

    <div class="relative min-h-screen flex flex-col justify-center items-center px-4">

        
        <div class="mb-8 text-center">
            <img src="/img/logo.png" alt="NexPanel" class="w-20 h-20 mx-auto mb-4 drop-shadow-lg" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
            <div class="w-20 h-20 mx-auto mb-4 rounded-2xl bg-gradient-to-br from-cyan-500 to-blue-600 items-center justify-center shadow-lg shadow-cyan-500/25 hidden">
                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2" />
                </svg>
            </div>
            <h1 class="text-3xl font-extrabold bg-gradient-to-r from-cyan-400 to-blue-500 bg-clip-text text-transparent">NexPanel</h1>
            <p class="text-sm text-slate-400 dark:text-slate-500 mt-1">Intelligent Server Management</p>
        </div>

        
        <div class="w-full max-w-sm">
            <div class="bg-white/80 dark:bg-surface-800/80 glass border border-slate-200 dark:border-slate-700/50 rounded-2xl shadow-xl glow-light dark:glow-dark p-8">
                <?php echo e($slot); ?>

            </div>
        </div>

        
        <p class="mt-8 text-xs text-slate-400 dark:text-slate-600">&copy; 2026 NexPanel — AI-Powered Server Management</p>
    </div>
</body>
</html>
<?php /**PATH /mnt/c/Users/User/Desktop/Project Server Base/NexPanel_First/resources/views/layouts/guest.blade.php ENDPATH**/ ?>