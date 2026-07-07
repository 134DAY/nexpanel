@extends('layouts.app')

@section('title', 'File Manager')
@section('subheader', 'Browse and manage server files')

@section('content')
<div x-data="fileManager('{{ $path }}')" class="space-y-4">

    @if($error)
    <div class="flex items-center gap-2 px-4 py-3 rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 text-sm text-red-600 dark:text-red-400">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
        {{ $error }}
    </div>
    @endif

    {{-- Toolbar --}}
    <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-1 text-sm overflow-x-auto">
                @foreach($breadcrumbs as $i => $crumb)
                    @if($i > 0)
                    <svg class="w-4 h-4 text-slate-300 dark:text-slate-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                    @endif
                    <a href="/files?path={{ urlencode($crumb['path']) }}" class="px-2 py-1 rounded-lg hover:bg-slate-100 dark:hover:bg-white/5 font-medium {{ $loop->last ? 'text-cyan-600 dark:text-cyan-400' : 'text-slate-500 dark:text-slate-400' }} whitespace-nowrap transition-colors">{{ $crumb['name'] }}</a>
                @endforeach
            </div>

            <div class="flex items-center gap-2 shrink-0 ml-4">
                <input type="file" x-ref="upload" class="hidden" @change="upload($event)">
                <button @click="$refs.upload.click()" class="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-white/10 text-sm font-medium transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                    Upload
                </button>
                <button @click="create('file')" class="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-white/10 text-sm font-medium transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    New File
                </button>
                <button @click="create('folder')" class="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-white/10 text-sm font-medium transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m3-3H9m4.06-7.19l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/></svg>
                    New Folder
                </button>
            </div>
        </div>
    </div>

    {{-- File List --}}
    <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="border-b border-slate-200 dark:border-slate-800/60">
                    <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Name</th>
                    <th class="text-left px-3 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Size</th>
                    <th class="text-left px-3 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Permissions</th>
                    <th class="text-left px-3 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Owner</th>
                    <th class="text-left px-3 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Modified</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/40">
                @if($path !== '/')
                <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                    <td class="px-3 py-3" colspan="6">
                        <a href="/files?path={{ urlencode(dirname($path)) }}" class="flex items-center gap-2 text-sm text-cyan-600 dark:text-cyan-400 hover:underline">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
                            ..
                        </a>
                    </td>
                </tr>
                @endif

                @forelse($files as $file)
                <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors group">
                    <td class="px-3 py-3">
                        @if($file['type'] === 'directory')
                        <a href="/files?path={{ urlencode($file['path']) }}" class="flex items-center gap-2.5 text-sm font-medium text-slate-800 dark:text-white hover:text-cyan-600 dark:hover:text-cyan-400 transition-colors">
                            <svg class="w-5 h-5 text-amber-400" fill="currentColor" viewBox="0 0 24 24"><path d="M2 6a2 2 0 012-2h5l2 2h9a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
                            {{ $file['name'] }}
                        </a>
                        @else
                        <button @click="edit('{{ addslashes($file['path']) }}')" class="flex items-center gap-2.5 text-sm text-slate-700 dark:text-slate-300 hover:text-cyan-600 dark:hover:text-cyan-400 transition-colors text-left">
                            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                            {{ $file['name'] }}
                        </button>
                        @endif
                    </td>
                    <td class="px-3 py-3 text-sm text-slate-500 dark:text-slate-400">{{ $file['size'] }}</td>
                    <td class="px-3 py-3 text-xs text-slate-500 dark:text-slate-400 font-mono">{{ $file['permissions'] }}</td>
                    <td class="px-3 py-3 text-sm text-slate-500 dark:text-slate-400">{{ $file['owner'] }}</td>
                    <td class="px-3 py-3 text-sm text-slate-500 dark:text-slate-400">{{ $file['modified'] }}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            @if($file['type'] === 'file')
                            <button @click="edit('{{ addslashes($file['path']) }}')" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-white/5 text-slate-400 hover:text-blue-500 transition-colors" title="Edit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                            </button>
                            <a href="/files/download?path={{ urlencode($file['path']) }}" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-white/5 text-slate-400 hover:text-emerald-500 transition-colors inline-block" title="Download">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                            </a>
                            @endif
                            <button @click="rename('{{ addslashes($file['path']) }}', '{{ addslashes($file['name']) }}')" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-white/5 text-slate-400 hover:text-amber-500 transition-colors" title="Rename">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487z"/></svg>
                            </button>
                            <button @click="remove('{{ addslashes($file['path']) }}', '{{ addslashes($file['name']) }}')" class="p-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-500/10 text-slate-400 hover:text-red-500 transition-colors" title="Delete">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-slate-400 dark:text-slate-500">Empty directory</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Storage Info --}}
    <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-5">
        <div class="flex items-center justify-between mb-3">
            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Disk Usage</span>
            <span class="text-sm text-slate-500 dark:text-slate-400">{{ $disk['used_h'] }} / {{ $disk['total_h'] }}</span>
        </div>
        <div class="w-full h-2 rounded-full bg-slate-100 dark:bg-white/10">
            <div class="h-2 rounded-full bg-gradient-to-r from-cyan-500 to-blue-500" style="width: {{ $disk['percent'] }}%"></div>
        </div>
        <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">{{ $disk['percent'] }}% used — {{ $disk['free_h'] }} free</p>
    </div>

    {{-- Editor Modal --}}
    <div x-show="editor.open" x-cloak x-transition.opacity class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @keydown.escape.window="editor.open = false">
        <div class="bg-white dark:bg-surface-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-4xl flex flex-col" style="max-height: 85vh">
            <div class="flex items-center justify-between p-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-sm font-bold text-slate-800 dark:text-white font-mono truncate" x-text="editor.path"></h3>
                <button @click="editor.open = false" class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-white/10 text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <textarea x-model="editor.content" spellcheck="false" class="flex-1 w-full p-4 font-mono text-sm bg-slate-50 dark:bg-surface-900 text-slate-800 dark:text-slate-200 focus:outline-none resize-none" style="min-height: 50vh"></textarea>
            <div class="flex items-center justify-end gap-3 p-4 border-t border-slate-200 dark:border-slate-700">
                <span class="text-xs text-slate-400 mr-auto" x-text="editor.status"></span>
                <button @click="editor.open = false" class="px-4 py-2 bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-400 font-medium rounded-xl text-sm">Cancel</button>
                <button @click="saveFile()" class="px-4 py-2 bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-semibold rounded-xl text-sm">Save</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function fileManager(cwd) {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    return {
        cwd,
        editor: { open: false, path: '', content: '', status: '' },

        async post(url, body) {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.error || ('HTTP ' + res.status));
            return data;
        },
        form(obj) {
            const fd = new FormData();
            Object.entries(obj).forEach(([k, v]) => fd.append(k, v));
            return fd;
        },

        async create(type) {
            const name = prompt(`New ${type} name:`);
            if (!name) return;
            try {
                await this.post('/files/create', this.form({ path: this.cwd, name, type }));
                location.reload();
            } catch (e) { alert(e.message); }
        },
        async rename(path, current) {
            const name = prompt('Rename to:', current);
            if (!name || name === current) return;
            try {
                await this.post('/files/rename', this.form({ path, name }));
                location.reload();
            } catch (e) { alert(e.message); }
        },
        async remove(path, name) {
            if (!confirm(`Delete "${name}"? This cannot be undone.`)) return;
            try {
                await this.post('/files/delete', this.form({ path }));
                location.reload();
            } catch (e) { alert(e.message); }
        },
        async upload(event) {
            const file = event.target.files[0];
            if (!file) return;
            try {
                await this.post('/files/upload', this.form({ path: this.cwd, file }));
                location.reload();
            } catch (e) { alert(e.message); }
        },
        async edit(path) {
            try {
                const res = await fetch('/files/read?path=' + encodeURIComponent(path), { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (!res.ok) throw new Error(data.error);
                this.editor = { open: true, path, content: data.content, status: '' };
            } catch (e) { alert(e.message); }
        },
        async saveFile() {
            this.editor.status = 'Saving…';
            try {
                await this.post('/files/save', this.form({ path: this.editor.path, content: this.editor.content }));
                this.editor.status = 'Saved ✓';
                setTimeout(() => this.editor.open = false, 600);
            } catch (e) { this.editor.status = ''; alert(e.message); }
        },
    }
}
</script>
@endpush
