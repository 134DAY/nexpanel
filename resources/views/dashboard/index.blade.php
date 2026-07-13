@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    {{-- Welcome Banner --}}
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

    {{-- Stats Grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-5 mb-6">

        {{-- CPU --}}
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

        {{-- RAM --}}
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

        {{-- Disk --}}
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

        {{-- Network --}}
        <div class="group bg-white dark:bg-surface-800/50 border border-slate-200 dark:border-slate-800/60 rounded-2xl p-5 hover:border-cyan-300 dark:hover:border-cyan-500/30 transition-all duration-300">
            <div class="flex items-center justify-between mb-4">
                <span class="text-sm text-slate-500 dark:text-slate-400 font-medium">Network</span>
                <div class="w-10 h-10 rounded-xl bg-cyan-50 dark:bg-cyan-500/10 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <svg class="w-5 h-5 text-cyan-500" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z"/></svg>
                </div>
            </div>
            <div class="flex items-baseline gap-2 mb-1">
                <span class="text-cyan-500 text-xs font-bold">↓</span>
                <span class="text-lg font-extrabold text-slate-800 dark:text-white" id="net-rx">--</span>
                <span class="text-[10px] text-slate-400">KB/s</span>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-emerald-500 text-xs font-bold">↑</span>
                <span class="text-lg font-extrabold text-slate-800 dark:text-white" id="net-tx">--</span>
                <span class="text-[10px] text-slate-400">KB/s</span>
            </div>
            <div class="text-[10px] text-slate-400 dark:text-slate-500 mt-1.5" id="net-total">-- / -- MB total</div>
        </div>

        {{-- Uptime --}}
        <div class="group bg-white dark:bg-surface-800/50 border border-slate-200 dark:border-slate-800/60 rounded-2xl p-5 hover:border-emerald-300 dark:hover:border-emerald-500/30 transition-all duration-300 stat-glow-emerald">
            <div class="flex items-center justify-between mb-4">
                <span class="text-sm text-slate-500 dark:text-slate-400 font-medium">Uptime</span>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <div class="text-2xl font-extrabold text-slate-800 dark:text-white mb-1" id="uptime-value">--</div>
            <div class="text-xs text-slate-400 dark:text-slate-500" id="server-info">Loading...</div>
            <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">IP: <span class="font-mono text-slate-500 dark:text-slate-300" id="server-ip">--</span></div>
        </div>
    </div>

    {{-- Chart + Services --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

        {{-- Chart --}}
        <div class="xl:col-span-2 bg-white dark:bg-surface-800/50 border border-slate-200 dark:border-slate-800/60 rounded-2xl p-5">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h3 class="text-sm font-bold text-slate-800 dark:text-white">Resource Monitor</h3>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Real-time CPU, RAM &amp; Network</p>
                </div>
                <span class="px-2.5 py-1 text-[10px] font-medium rounded-full bg-slate-100 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400">Live</span>
            </div>
            <canvas id="resourceChart" height="110"></canvas>
        </div>

        {{-- Services --}}
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
@endsection

@push('scripts')
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
        const netData = Array(maxPoints).fill(null); // combined rx+tx KB/s

        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    { label: 'CPU %', data: cpuData, yAxisID: 'y', borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.1)', borderWidth: 2.5, pointRadius: 0, tension: 0.4, fill: true },
                    { label: 'RAM %', data: ramData, yAxisID: 'y', borderColor: '#a855f7', backgroundColor: 'rgba(168,85,247,0.1)', borderWidth: 2.5, pointRadius: 0, tension: 0.4, fill: true },
                    { label: 'Net KB/s', data: netData, yAxisID: 'yNet', borderColor: '#06b6d4', backgroundColor: 'rgba(6,182,212,0.08)', borderWidth: 2, borderDash: [4, 3], pointRadius: 0, tension: 0.4, fill: false }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: true, animation: { duration: 400, easing: 'easeOutQuart' },
                scales: {
                    x: { display: false },
                    y: { position: 'left', min: 0, max: 100, grid: { color: gridColor() }, ticks: { color: tickColor(), callback: v => v + '%', font: { size: 11 } } },
                    yNet: { position: 'right', min: 0, grid: { drawOnChartArea: false }, ticks: { color: tickColor(), callback: v => v + ' KB/s', font: { size: 10 } } }
                },
                plugins: { legend: { labels: { color: legendColor(), boxWidth: 12, font: { size: 11 }, usePointStyle: true, pointStyle: 'circle' } } }
            }
        });

        // Theme observer
        new MutationObserver(() => {
            chart.options.scales.y.grid.color = gridColor();
            chart.options.scales.y.ticks.color = tickColor();
            chart.options.scales.yNet.ticks.color = tickColor();
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
                document.getElementById('server-ip').textContent = d.ip || 'N/A';

                if (d.network) {
                    document.getElementById('net-rx').textContent = d.network.rx;
                    document.getElementById('net-tx').textContent = d.network.tx;
                    document.getElementById('net-total').textContent = d.network.total_rx_mb + ' / ' + d.network.total_tx_mb + ' MB total';
                    netData.push(parseFloat(d.network.rx) + parseFloat(d.network.tx));
                    netData.shift();
                }

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
    @endpush
