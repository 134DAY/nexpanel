<?php if (isset($component)) { $__componentOriginal5863877a5171c196453bfa0bd807e410 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5863877a5171c196453bfa0bd807e410 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.layouts.app','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('layouts.app'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
    <?php $__env->startSection('title', 'Dashboard'); ?>

    
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-cyan-600 via-blue-600 to-indigo-600 p-6 mb-6">
        <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiNmZmYiIGZpbGwtb3BhY2l0eT0iMC4wNSI+PHBhdGggZD0iTTM2IDM0djItSDI0di0yaDEyek0zNiAyNHYySDI0di0yaDEyeiIvPjwvZz48L2c+PC9zdmc+')] opacity-30"></div>
        <div class="relative">
            <h2 class="text-2xl font-bold text-white mb-1">Welcome back, Admin</h2>
            <p class="text-blue-100 text-sm">Your server is running smoothly. All systems operational.</p>
        </div>
        <div class="absolute right-6 top-1/2 -translate-y-1/2 opacity-20">
            <svg class="w-24 h-24 text-white" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
        </div>
    </div>

    
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-6">

        
        <div class="group bg-white dark:bg-surface-800/50 border border-slate-200 dark:border-slate-800/60 rounded-2xl p-5 hover:border-blue-300 dark:hover:border-blue-500/30 transition-all duration-300 stat-glow-blue">
            <div class="flex items-center justify-between mb-4">
                <span class="text-sm text-slate-500 dark:text-slate-400 font-medium">CPU Usage</span>
                <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25zm.75-12h9v9h-9v-9z"/></svg>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-slate-800 dark:text-white mb-2" id="cpu-value">--</div>
            <div class="w-full bg-slate-100 dark:bg-slate-700/50 rounded-full h-2">
                <div class="bg-gradient-to-r from-blue-500 to-cyan-400 h-2 rounded-full transition-all duration-700" id="cpu-bar" style="width: 0%"></div>
            </div>
        </div>

        
        <div class="group bg-white dark:bg-surface-800/50 border border-slate-200 dark:border-slate-800/60 rounded-2xl p-5 hover:border-purple-300 dark:hover:border-purple-500/30 transition-all duration-300 stat-glow-purple">
            <div class="flex items-center justify-between mb-4">
                <span class="text-sm text-slate-500 dark:text-slate-400 font-medium">RAM Usage</span>
                <div class="w-10 h-10 rounded-xl bg-purple-50 dark:bg-purple-500/10 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-slate-800 dark:text-white mb-1" id="ram-value">--</div>
            <div class="text-xs text-slate-400 dark:text-slate-500 mb-2" id="ram-detail">-- / -- MB</div>
            <div class="w-full bg-slate-100 dark:bg-slate-700/50 rounded-full h-2">
                <div class="bg-gradient-to-r from-purple-500 to-pink-400 h-2 rounded-full transition-all duration-700" id="ram-bar" style="width: 0%"></div>
            </div>
        </div>

        
        <div class="group bg-white dark:bg-surface-800/50 border border-slate-200 dark:border-slate-800/60 rounded-2xl p-5 hover:border-amber-300 dark:hover:border-amber-500/30 transition-all duration-300 stat-glow-amber">
            <div class="flex items-center justify-between mb-4">
                <span class="text-sm text-slate-500 dark:text-slate-400 font-medium">Disk Usage</span>
                <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-slate-800 dark:text-white mb-1" id="disk-value">--</div>
            <div class="text-xs text-slate-400 dark:text-slate-500 mb-2" id="disk-detail">-- / -- GB</div>
            <div class="w-full bg-slate-100 dark:bg-slate-700/50 rounded-full h-2">
                <div class="bg-gradient-to-r from-amber-500 to-orange-400 h-2 rounded-full transition-all duration-700" id="disk-bar" style="width: 0%"></div>
            </div>
        </div>

        
        <div class="group bg-white dark:bg-surface-800/50 border border-slate-200 dark:border-slate-800/60 rounded-2xl p-5 hover:border-emerald-300 dark:hover:border-emerald-500/30 transition-all duration-300 stat-glow-emerald">
            <div class="flex items-center justify-between mb-4">
                <span class="text-sm text-slate-500 dark:text-slate-400 font-medium">Uptime</span>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <div class="text-2xl font-extrabold text-slate-800 dark:text-white mb-1" id="uptime-value">--</div>
            <div class="text-xs text-slate-400 dark:text-slate-500" id="server-info">Loading...</div>
        </div>
    </div>

    
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

        
        <div class="xl:col-span-2 bg-white dark:bg-surface-800/50 border border-slate-200 dark:border-slate-800/60 rounded-2xl p-5">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h3 class="text-sm font-bold text-slate-800 dark:text-white">Resource Monitor</h3>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Real-time CPU & RAM usage</p>
                </div>
                <span class="px-2.5 py-1 text-[10px] font-medium rounded-full bg-slate-100 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400">Live</span>
            </div>
            <canvas id="resourceChart" height="110"></canvas>
        </div>

        
        <div class="bg-white dark:bg-surface-800/50 border border-slate-200 dark:border-slate-800/60 rounded-2xl p-5">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h3 class="text-sm font-bold text-slate-800 dark:text-white">Services</h3>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">System services status</p>
                </div>
            </div>

            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700/50">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center">
                            <span class="w-2.5 h-2.5 rounded-full bg-slate-300" id="svc-dot-nginx"></span>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Nginx</span>
                            <p class="text-[10px] text-slate-400" id="svc-status-nginx">Checking...</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700/50">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center">
                            <span class="w-2.5 h-2.5 rounded-full bg-slate-300" id="svc-dot-mysql"></span>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">MySQL / MariaDB</span>
                            <p class="text-[10px] text-slate-400" id="svc-status-mysql">Checking...</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700/50">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-purple-50 dark:bg-purple-500/10 flex items-center justify-center">
                            <span class="w-2.5 h-2.5 rounded-full bg-slate-300" id="svc-dot-phpfpm"></span>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">PHP-FPM</span>
                            <p class="text-[10px] text-slate-400" id="svc-status-phpfpm">Checking...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php $__env->startPush('scripts'); ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        const isDark = () => document.documentElement.classList.contains('dark');
        const gridColor = () => isDark() ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
        const tickColor = () => isDark() ? '#64748b' : '#94a3b8';
        const legendColor = () => isDark() ? '#94a3b8' : '#64748b';

        const ctx = document.getElementById('resourceChart').getContext('2d');
        const maxPoints = 20;
        const labels = Array(maxPoints).fill('');
        const cpuData = Array(maxPoints).fill(null);
        const ramData = Array(maxPoints).fill(null);

        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    { label: 'CPU %', data: cpuData, borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.1)', borderWidth: 2.5, pointRadius: 0, tension: 0.4, fill: true },
                    { label: 'RAM %', data: ramData, borderColor: '#a855f7', backgroundColor: 'rgba(168,85,247,0.1)', borderWidth: 2.5, pointRadius: 0, tension: 0.4, fill: true }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: true, animation: { duration: 400, easing: 'easeOutQuart' },
                scales: {
                    x: { display: false },
                    y: { min: 0, max: 100, grid: { color: gridColor() }, ticks: { color: tickColor(), callback: v => v + '%', font: { size: 11 } } }
                },
                plugins: { legend: { labels: { color: legendColor(), boxWidth: 12, font: { size: 11 }, usePointStyle: true, pointStyle: 'circle' } } }
            }
        });

        // Theme observer
        new MutationObserver(() => {
            chart.options.scales.y.grid.color = gridColor();
            chart.options.scales.y.ticks.color = tickColor();
            chart.options.plugins.legend.labels.color = legendColor();
            chart.update('none');
        }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

        async function fetchMetrics() {
            try {
                const res = await fetch('/api/metrics');
                const d = await res.json();
                document.getElementById('cpu-value').textContent = d.cpu + '%';
                document.getElementById('cpu-bar').style.width = d.cpu + '%';
                document.getElementById('ram-value').textContent = d.ram.percent + '%';
                document.getElementById('ram-detail').textContent = d.ram.used + ' / ' + d.ram.total + ' MB';
                document.getElementById('ram-bar').style.width = d.ram.percent + '%';
                document.getElementById('disk-value').textContent = d.disk.percent + '%';
                document.getElementById('disk-detail').textContent = d.disk.used + ' / ' + d.disk.total + ' GB';
                document.getElementById('disk-bar').style.width = d.disk.percent + '%';
                document.getElementById('uptime-value').textContent = d.uptime;
                document.getElementById('server-info').textContent = d.hostname + ' · ' + d.os;
                cpuData.push(parseFloat(d.cpu)); ramData.push(parseFloat(d.ram.percent));
                cpuData.shift(); ramData.shift(); chart.update();

                ['nginx','mysql','phpfpm'].forEach(svc => {
                    const dot = document.getElementById('svc-dot-' + svc);
                    const text = document.getElementById('svc-status-' + svc);
                    if (!dot || !text) return;
                    const isActive = d.services[svc] === 'active';
                    dot.className = 'w-2.5 h-2.5 rounded-full ' + (isActive ? 'bg-emerald-500' : 'bg-red-400');
                    text.textContent = isActive ? 'Running' : 'Stopped';
                    text.className = 'text-[10px] font-medium ' + (isActive ? 'text-emerald-500' : 'text-red-400');
                });
            } catch(e) { console.error('Metrics fetch failed:', e); }
        }
        fetchMetrics();
        setInterval(fetchMetrics, 3000);
    </script>
    <?php $__env->stopPush(); ?>

 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5863877a5171c196453bfa0bd807e410)): ?>
<?php $attributes = $__attributesOriginal5863877a5171c196453bfa0bd807e410; ?>
<?php unset($__attributesOriginal5863877a5171c196453bfa0bd807e410); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5863877a5171c196453bfa0bd807e410)): ?>
<?php $component = $__componentOriginal5863877a5171c196453bfa0bd807e410; ?>
<?php unset($__componentOriginal5863877a5171c196453bfa0bd807e410); ?>
<?php endif; ?>
<?php /**PATH /mnt/c/Users/User/Desktop/Project Server Base/NexPanel_First/resources/views/dashboard/index.blade.php ENDPATH**/ ?>