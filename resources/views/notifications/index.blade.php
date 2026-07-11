@extends('layouts.app')

@section('title', 'Notifications')
@section('subheader', 'Alert channels for server events')

@section('content')
<div x-data="notificationsPage()" class="max-w-3xl space-y-6">

    @if(session('success'))
    <div class="flex items-center gap-3 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-sm">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ session('success') }}
    </div>
    @endif

    <form method="POST" action="/notifications" class="space-y-4" x-ref="form">
        @csrf

        {{-- Monitoring & thresholds --}}
        <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-5">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-500/10 flex items-center justify-center">
                        <svg class="w-5 h-5 text-rose-500" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75l6-6 4.5 4.5 4.5-6 4.5 6M3.75 21h16.5"/></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 dark:text-white">Monitoring &amp; Alerts</h3>
                        <p class="text-xs text-slate-400">แจ้งเตือนอัตโนมัติเมื่อทรัพยากรเกินเกณฑ์ / บริการล่ม / SSL ใกล้หมด / cron ล้มเหลว</p>
                    </div>
                </div>
                <label class="inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="monitor_enabled" value="1" @checked(($settings['monitor_enabled'] ?? '0') === '1') class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-200 dark:bg-white/10 peer-checked:bg-emerald-500 rounded-full peer transition-colors relative after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"></div>
                </label>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
                <div>
                    <label class="text-[11px] text-slate-400 font-medium">CPU เกิน (%)</label>
                    <input type="number" min="1" max="100" name="monitor_cpu" value="{{ $settings['monitor_cpu'] ?? '90' }}" class="mt-1 w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500">
                </div>
                <div>
                    <label class="text-[11px] text-slate-400 font-medium">RAM เกิน (%)</label>
                    <input type="number" min="1" max="100" name="monitor_ram" value="{{ $settings['monitor_ram'] ?? '90' }}" class="mt-1 w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500">
                </div>
                <div>
                    <label class="text-[11px] text-slate-400 font-medium">Disk เกิน (%)</label>
                    <input type="number" min="1" max="100" name="monitor_disk" value="{{ $settings['monitor_disk'] ?? '90' }}" class="mt-1 w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500">
                </div>
                <div>
                    <label class="text-[11px] text-slate-400 font-medium">SSL เตือนก่อน (วัน)</label>
                    <input type="number" min="1" max="90" name="monitor_ssl_days" value="{{ $settings['monitor_ssl_days'] ?? '14' }}" class="mt-1 w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500">
                </div>
                <div>
                    <label class="text-[11px] text-slate-400 font-medium">Cooldown (นาที)</label>
                    <input type="number" min="1" max="1440" name="monitor_cooldown" value="{{ $settings['monitor_cooldown'] ?? '30' }}" class="mt-1 w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500">
                </div>
            </div>

            <div class="flex flex-wrap gap-4 text-sm text-slate-600 dark:text-slate-300">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="monitor_services_enabled" value="1" @checked(($settings['monitor_services_enabled'] ?? '1') === '1') class="rounded border-slate-300 dark:border-slate-600 text-cyan-500 focus:ring-cyan-500">
                    บริการล่ม (nginx/mysql/php-fpm)
                </label>
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="monitor_ssl_enabled" value="1" @checked(($settings['monitor_ssl_enabled'] ?? '1') === '1') class="rounded border-slate-300 dark:border-slate-600 text-cyan-500 focus:ring-cyan-500">
                    SSL ใกล้หมดอายุ
                </label>
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="monitor_cron_enabled" value="1" @checked(($settings['monitor_cron_enabled'] ?? '1') === '1') class="rounded border-slate-300 dark:border-slate-600 text-cyan-500 focus:ring-cyan-500">
                    Cron ล้มเหลว
                </label>
            </div>
            <p class="text-[11px] text-slate-400 mt-3">แจ้งเตือนจะส่งผ่าน LINE เมื่อเปิดใช้งานด้านล่าง · ตรวจทุก 1 นาทีผ่าน scheduler</p>
        </div>

        {{-- LINE --}}
        <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-5">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-green-50 dark:bg-green-500/10 flex items-center justify-center">
                        <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 24 24"><path d="M24 10.304c0-5.369-5.383-9.738-12-9.738S0 4.935 0 10.304c0 4.813 4.269 8.843 10.036 9.608.391.084.922.258 1.057.592.121.303.079.778.039 1.085l-.171 1.027c-.053.303-.242 1.186 1.039.647 1.281-.54 6.911-4.069 9.428-6.967C23.176 14.393 24 12.458 24 10.304zM7.71 13.464H5.334a.63.63 0 01-.63-.629V8.108a.63.63 0 011.26 0v4.098H7.71a.63.63 0 010 1.258zm2.466-.629a.631.631 0 01-1.26 0V8.108a.63.63 0 011.26 0v4.727zm5.741 0a.63.63 0 01-.631.629.628.628 0 01-.51-.261l-2.443-3.324v2.956a.63.63 0 01-1.26 0V8.108a.629.629 0 01.63-.629c.203 0 .381.094.5.257l2.454 3.327V8.108a.63.63 0 011.26 0v4.727zm3.855-2.993a.63.63 0 010 1.258h-1.745v1.107h1.745a.63.63 0 010 1.257h-2.376a.63.63 0 01-.629-.629V8.108a.63.63 0 01.629-.63h2.376a.63.63 0 010 1.259h-1.745v1.105h1.745z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 dark:text-white">LINE</h3>
                        <p class="text-xs text-slate-400">Push via LINE Messaging API (แทน LINE Notify ที่ปิดตัว)</p>
                    </div>
                </div>
                <label class="inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="line_enabled" value="1" @checked(($settings['line_enabled'] ?? '0') === '1') class="sr-only peer" x-model="channels.line">
                    <div class="w-11 h-6 bg-slate-200 dark:bg-white/10 peer-checked:bg-emerald-500 rounded-full peer transition-colors relative after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"></div>
                </label>
            </div>
            <div class="space-y-3">
                <input type="text" name="line_token" value="{{ $settings['line_token'] ?? '' }}" placeholder="Channel access token (long-lived)" class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-500 font-mono text-sm">
                <input type="text" name="line_to" value="{{ $settings['line_to'] ?? '' }}" placeholder="Recipient id (userId / groupId — ต้องเพิ่มบอทเป็นเพื่อนก่อน)" class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-500 font-mono text-sm">
            </div>
            <div class="flex justify-end mt-3">
                <button type="button" @click="test('line')" class="px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-300 text-xs font-medium hover:bg-slate-200 dark:hover:bg-white/10">Send test</button>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-600 hover:to-blue-700 text-white font-semibold rounded-xl shadow-lg shadow-cyan-500/25 transition-all text-sm">Save Settings</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function notificationsPage() {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    return {
        channels: {
            line: {{ ($settings['line_enabled'] ?? '0') === '1' ? 'true' : 'false' }},
        },
        async test(channel) {
            const fd = new FormData(this.$refs.form);
            fd.set('channel', channel);
            try {
                const res = await fetch('/notifications/test', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: fd,
                });
                const data = await res.json();
                alert(data.ok ? '✅ ' + data.message : '❌ ' + data.message);
            } catch (e) { alert('Request failed: ' + e.message); }
        },
    }
}
</script>
@endpush
