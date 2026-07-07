<?php $__env->startSection('title', 'Service Control'); ?>

<?php $__env->startSection('content'); ?>
<div x-data="serviceControl()" class="max-w-5xl mx-auto space-y-6">

    
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-800 dark:text-white">Service Control</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Monitor and manage system services</p>
        </div>
        <div class="flex items-center gap-3">
            
            <template x-if="!isReal">
                <span class="px-3 py-1.5 rounded-full bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 text-xs font-medium text-amber-700 dark:text-amber-400">
                    ⚡ Demo Mode — Mock Data
                </span>
            </template>
            <template x-if="isReal">
                <span class="px-3 py-1.5 rounded-full bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-xs font-medium text-emerald-700 dark:text-emerald-400">
                    ● Live Server
                </span>
            </template>
            <button @click="refreshAll()" class="p-2 rounded-xl bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 hover:bg-slate-50 dark:hover:bg-white/10 text-slate-500 dark:text-slate-400 transition-all" title="Refresh">
                <svg class="w-5 h-5" :class="refreshing && 'animate-spin'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182"/></svg>
            </button>
        </div>
    </div>

    
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-2xl p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-500/15 flex items-center justify-center">
                    <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-slate-800 dark:text-white" x-text="runningCount"></p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Running</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-2xl p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-red-100 dark:bg-red-500/15 flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 9.563C9 9.252 9.252 9 9.563 9h4.874c.311 0 .563.252.563.563v4.874c0 .311-.252.563-.563.563H9.564A.562.562 0 019 14.437V9.564z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-slate-800 dark:text-white" x-text="stoppedCount"></p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Stopped</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-2xl p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-500/15 flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7m0 0a3 3 0 01-3 3m0 3h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008zm-3 6h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-slate-800 dark:text-white" x-text="services.length"></p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Total Services</p>
                </div>
            </div>
        </div>
    </div>

    
    <div class="space-y-3">
        <template x-for="svc in services" :key="svc.id">
            <div class="bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-2xl p-5 hover:shadow-md dark:hover:shadow-none transition-all">
                <div class="flex items-center justify-between">
                    
                    <div class="flex items-center gap-4">
                        
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center"
                             :class="svc.status === 'running'
                                ? 'bg-emerald-100 dark:bg-emerald-500/15'
                                : 'bg-slate-100 dark:bg-white/5'">
                            
                            <svg x-show="svc.icon === 'globe'" class="w-6 h-6" :class="svc.status === 'running' ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500'" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/></svg>
                            
                            <svg x-show="svc.icon === 'database'" class="w-6 h-6" :class="svc.status === 'running' ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500'" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/></svg>
                            
                            <svg x-show="svc.icon === 'code'" class="w-6 h-6" :class="svc.status === 'running' ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500'" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5"/></svg>
                            
                            <svg x-show="svc.icon === 'zap'" class="w-6 h-6" :class="svc.status === 'running' ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500'" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
                            
                            <svg x-show="svc.icon === 'activity'" class="w-6 h-6" :class="svc.status === 'running' ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500'" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
                            
                            <svg x-show="svc.icon === 'shield'" class="w-6 h-6" :class="svc.status === 'running' ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500'" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                        </div>
                        
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-bold text-slate-800 dark:text-white" x-text="svc.name"></h3>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide"
                                    :class="svc.status === 'running'
                                        ? 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'
                                        : svc.status === 'stopped'
                                            ? 'bg-red-100 dark:bg-red-500/15 text-red-700 dark:text-red-400'
                                            : 'bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-400'"
                                    x-text="svc.status"></span>
                            </div>
                            <p class="text-sm text-slate-500 dark:text-slate-400" x-text="svc.description"></p>
                        </div>
                    </div>

                    
                    <div class="flex items-center gap-6 text-sm">
                        <div class="text-center">
                            <p class="text-slate-400 dark:text-slate-500 text-xs">Uptime</p>
                            <p class="font-semibold text-slate-700 dark:text-slate-200" x-text="svc.uptime"></p>
                        </div>
                        <div class="text-center">
                            <p class="text-slate-400 dark:text-slate-500 text-xs">Memory</p>
                            <p class="font-semibold text-slate-700 dark:text-slate-200" x-text="svc.memory"></p>
                        </div>
                        <div class="text-center" x-show="svc.port">
                            <p class="text-slate-400 dark:text-slate-500 text-xs">Port</p>
                            <p class="font-semibold text-slate-700 dark:text-slate-200" x-text="':' + svc.port"></p>
                        </div>
                    </div>

                    
                    <div class="flex items-center gap-2">
                        <template x-if="svc.status === 'running'">
                            <div class="flex items-center gap-2">
                                <button @click="doAction(svc.id, 'restart')"
                                    :disabled="svc.loading"
                                    class="px-3 py-2 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 text-amber-700 dark:text-amber-400 text-xs font-semibold hover:bg-amber-100 dark:hover:bg-amber-500/20 transition-all disabled:opacity-50">
                                    <span x-show="!svc.loading">Restart</span>
                                    <span x-show="svc.loading" class="flex items-center gap-1">
                                        <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        ...
                                    </span>
                                </button>
                                <button @click="confirmAction(svc.id, svc.name, 'stop')"
                                    :disabled="svc.loading"
                                    class="px-3 py-2 rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 text-red-700 dark:text-red-400 text-xs font-semibold hover:bg-red-100 dark:hover:bg-red-500/20 transition-all disabled:opacity-50">
                                    Stop
                                </button>
                            </div>
                        </template>
                        <template x-if="svc.status === 'stopped'">
                            <button @click="doAction(svc.id, 'start')"
                                :disabled="svc.loading"
                                class="px-4 py-2 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-xs font-semibold hover:bg-emerald-100 dark:hover:bg-emerald-500/20 transition-all disabled:opacity-50">
                                <span x-show="!svc.loading">Start</span>
                                <span x-show="svc.loading" class="flex items-center gap-1">
                                    <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    ...
                                </span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </template>
    </div>

    
    <div x-show="toast.show" x-transition
         class="fixed bottom-6 right-6 z-50 px-5 py-3 rounded-2xl shadow-lg border flex items-center gap-3"
         :class="toast.type === 'success'
            ? 'bg-emerald-50 dark:bg-emerald-900/90 border-emerald-200 dark:border-emerald-500/30'
            : 'bg-red-50 dark:bg-red-900/90 border-red-200 dark:border-red-500/30'">
        <svg x-show="toast.type === 'success'" class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <svg x-show="toast.type === 'error'" class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
        <span class="text-sm font-medium" :class="toast.type === 'success' ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300'" x-text="toast.message"></span>
    </div>
</div>

<script>
function serviceControl() {
    return {
        services: <?php echo json_encode($services, 15, 512) ?>.map(s => ({...s, loading: false})),
        refreshing: false,
        toast: { show: false, message: '', type: 'success' },

        get isReal() {
            return this.services.length > 0 && this.services[0].is_real === true;
        },
        get runningCount() {
            return this.services.filter(s => s.status === 'running').length;
        },
        get stoppedCount() {
            return this.services.filter(s => s.status !== 'running').length;
        },

        confirmAction(serviceId, serviceName, action) {
            if (confirm(`Are you sure you want to ${action} ${serviceName}?`)) {
                this.doAction(serviceId, action);
            }
        },

        async doAction(serviceId, action) {
            const svc = this.services.find(s => s.id === serviceId);
            if (!svc) return;
            svc.loading = true;

            try {
                const res = await fetch('/api/services/action', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ service: serviceId, action: action })
                });

                const data = await res.json();

                if (data.success) {
                    svc.status = data.new_status;
                    if (data.new_status === 'stopped') {
                        svc.uptime = '-';
                        svc.pid = null;
                    }
                    this.showToast(data.message, 'success');
                } else {
                    this.showToast(data.message || 'Action failed', 'error');
                }
            } catch (e) {
                this.showToast('Connection error. Please try again.', 'error');
            }

            svc.loading = false;
        },

        refreshAll() {
            this.refreshing = true;
            window.location.reload();
        },

        showToast(message, type = 'success') {
            this.toast = { show: true, message, type };
            setTimeout(() => { this.toast.show = false; }, 3000);
        }
    }
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /mnt/c/Users/User/Desktop/Project Server Base/NexPanel_First/resources/views/services/index.blade.php ENDPATH**/ ?>