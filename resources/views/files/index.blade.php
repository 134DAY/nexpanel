@extends('layouts.app')

@section('title', 'File Manager')
@section('subheader', 'Browse and manage server files')

@section('content')
<div x-data="fileManager(@js($path), @js($root), @js($trashCount))" x-init="init()" class="space-y-4">

    {{-- Tab bar --}}
    <div class="flex items-end gap-1 overflow-x-auto pb-px">
        <template x-for="t in tabs" :key="t.id">
            <div @click="activate(t.id)"
                 :class="t.id === activeId
                    ? 'bg-white dark:bg-white/5 border-slate-200 dark:border-slate-800/60 border-b-transparent text-slate-800 dark:text-white'
                    : 'bg-slate-100/70 dark:bg-white/[0.02] border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'"
                 class="group flex items-center gap-2 pl-3 pr-2 py-2 rounded-t-xl border border-b-0 cursor-pointer text-sm font-medium whitespace-nowrap transition-colors">
                <svg class="w-4 h-4 text-amber-400 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M2 6a2 2 0 012-2h5l2 2h9a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
                <span x-text="tabLabel(t.path)"></span>
                <button x-show="tabs.length > 1" @click.stop="closeTab(t.id)"
                        class="p-0.5 rounded hover:bg-slate-200 dark:hover:bg-white/10 text-slate-400 opacity-0 group-hover:opacity-100 transition-opacity">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </template>
        <button @click="newTab()" title="New tab" class="mb-px ml-1 p-2 rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-white/5 hover:text-cyan-500 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        </button>
    </div>

    <template x-if="error">
        <div class="flex items-center gap-2 px-4 py-3 rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 text-sm text-red-600 dark:text-red-400">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
            <span x-text="error"></span>
        </div>
    </template>

    {{-- Toolbar --}}
    <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-4 space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <input type="file" x-ref="upload" class="hidden" @change="upload($event)">
            <button @click="$refs.upload.click()" class="tb">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                Upload
            </button>
            <button @click="create('file')" class="tb">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                New File
            </button>
            <button @click="create('folder')" class="tb">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m3-3H9m4.06-7.19l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/></svg>
                New Folder
            </button>
            <button @click="searchOpen()" class="tb">
                <svg class="w-4 h-4 text-cyan-500" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                Search File Content
            </button>
            <button @click="go(root)" class="tb">
                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/></svg>
                Root dir
            </button>
            <a href="/terminal" class="tb">
                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z"/></svg>
                Terminal
            </a>
            <button @click="trashOpen()" class="tb">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79"/></svg>
                Recycle Bin
                <span x-show="tr.count > 0" x-cloak class="ml-0.5 min-w-[1.25rem] px-1 py-0.5 rounded-full bg-slate-200 dark:bg-white/10 text-[11px] font-bold leading-none text-slate-600 dark:text-slate-300" x-text="tr.count"></span>
            </button>

            <button x-show="clip.path" x-cloak @click="paste()" class="tb !bg-cyan-500/10 !text-cyan-600 dark:!text-cyan-400" :title="'Paste ' + clip.name">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184"/></svg>
                <span x-text="(clip.mode === 'cut' ? 'Move' : 'Paste') + ' here'"></span>
            </button>

            {{-- view switcher --}}
            <div class="ml-auto flex bg-slate-100 dark:bg-white/5 rounded-xl p-1">
                <button @click="view='list'" :class="view==='list' ? 'bg-white dark:bg-surface-800 shadow text-slate-800 dark:text-white' : 'text-slate-400'" class="p-1.5 rounded-lg transition-all" title="List view">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                </button>
                <button @click="view='grid'" :class="view==='grid' ? 'bg-white dark:bg-surface-800 shadow text-slate-800 dark:text-white' : 'text-slate-400'" class="p-1.5 rounded-lg transition-all" title="Grid view">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>
                </button>
            </div>
        </div>

        {{-- Breadcrumbs --}}
        <div class="flex items-center gap-1 text-sm overflow-x-auto">
            <template x-for="(crumb, i) in breadcrumbs" :key="crumb.path">
                <div class="flex items-center gap-1 shrink-0">
                    <svg x-show="i > 0" class="w-4 h-4 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                    <button @click="go(crumb.path)" :class="i === breadcrumbs.length - 1 ? 'text-cyan-600 dark:text-cyan-400' : 'text-slate-500 dark:text-slate-400'"
                            class="px-2 py-1 rounded-lg hover:bg-slate-100 dark:hover:bg-white/5 font-medium whitespace-nowrap transition-colors" x-text="crumb.name"></button>
                </div>
            </template>
            <svg x-show="loading" x-cloak class="w-4 h-4 ml-2 animate-spin text-cyan-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
        </div>
    </div>

    {{-- List view --}}
    <div x-show="view==='list'" class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 overflow-hidden">
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
                <tr x-show="parent" class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                    <td class="px-3 py-3" colspan="6">
                        <button @click="go(parent)" class="flex items-center gap-2 text-sm text-cyan-600 dark:text-cyan-400 hover:underline">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
                            ..
                        </button>
                    </td>
                </tr>

                <template x-for="file in files" :key="file.path">
                    <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors group" @contextmenu.prevent="openMenu($event, file)">
                        <td class="px-3 py-2.5">
                            <button @click="open(file)" class="flex items-center gap-2.5 text-sm text-left text-slate-700 dark:text-slate-300 hover:text-cyan-600 dark:hover:text-cyan-400 transition-colors">
                                <span x-html="iconHtml(file)"></span>
                                <span :class="file.type === 'directory' && 'font-medium text-slate-800 dark:text-white'" x-text="file.name"></span>
                            </button>
                        </td>
                        <td class="px-3 py-2.5 text-sm text-slate-500 dark:text-slate-400" x-text="file.size"></td>
                        <td class="px-3 py-2.5 text-xs text-slate-500 dark:text-slate-400 font-mono" x-text="file.permissions"></td>
                        <td class="px-3 py-2.5 text-sm text-slate-500 dark:text-slate-400" x-text="file.owner"></td>
                        <td class="px-3 py-2.5 text-sm text-slate-500 dark:text-slate-400" x-text="file.modified"></td>
                        <td class="px-4 py-2.5 text-right">
                            <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <template x-if="file.type === 'file'">
                                    <div class="flex items-center gap-1">
                                        <button @click="edit(file.path)" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-white/5 text-slate-400 hover:text-blue-500 transition-colors" title="Edit">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                                        </button>
                                        <a :href="'/files/download?path=' + encodeURIComponent(file.path)" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-white/5 text-slate-400 hover:text-emerald-500 transition-colors inline-block" title="Download">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                                        </a>
                                    </div>
                                </template>
                                <button @click="rename(file.path, file.name)" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-white/5 text-slate-400 hover:text-amber-500 transition-colors" title="Rename">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487z"/></svg>
                                </button>
                                <button @click="remove(file.path, file.name)" class="p-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-500/10 text-slate-400 hover:text-red-500 transition-colors" title="Move to recycle bin">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>

                <tr x-show="!files.length && !loading"><td colspan="6" class="px-4 py-10 text-center text-sm text-slate-400 dark:text-slate-500">Empty directory</td></tr>
            </tbody>
        </table>
    </div>

    {{-- Grid view --}}
    <div x-show="view==='grid'" x-cloak class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-4">
        <div class="grid grid-cols-[repeat(auto-fill,minmax(9rem,1fr))] gap-2">
            <button x-show="parent" @click="go(parent)" class="flex flex-col items-center gap-2 p-4 rounded-xl hover:bg-slate-50 dark:hover:bg-white/5 transition-colors">
                <svg class="w-10 h-10 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
                <span class="text-xs text-slate-500">..</span>
            </button>
            <template x-for="file in files" :key="file.path">
                <button @click="open(file)" @contextmenu.prevent="openMenu($event, file)"
                        class="flex flex-col items-center gap-2 p-4 rounded-xl hover:bg-slate-50 dark:hover:bg-white/5 transition-colors">
                    <span x-html="iconHtml(file, true)"></span>
                    <span class="text-xs text-center text-slate-600 dark:text-slate-300 break-all line-clamp-2" x-text="file.name"></span>
                </button>
            </template>
        </div>
        <p x-show="!files.length && !loading" class="py-10 text-center text-sm text-slate-400">Empty directory</p>
    </div>

    {{-- Storage Info --}}
    <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-5">
        <div class="flex items-center justify-between mb-3">
            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Disk Usage</span>
            <span class="text-sm text-slate-500 dark:text-slate-400"><span x-text="disk.used_h"></span> / <span x-text="disk.total_h"></span></span>
        </div>
        <div class="w-full h-2 rounded-full bg-slate-100 dark:bg-white/10">
            <div class="h-2 rounded-full bg-gradient-to-r from-cyan-500 to-blue-500" :style="`width: ${disk.percent}%`"></div>
        </div>
        <p class="mt-2 text-xs text-slate-400 dark:text-slate-500"><span x-text="disk.percent"></span>% used — <span x-text="disk.free_h"></span> free</p>
    </div>

    {{-- Search modal --}}
    <div x-show="sr.open" x-cloak x-transition.opacity class="fixed inset-0 bg-black/50 z-50 flex items-start justify-center p-4 pt-16" @click.self="sr.open=false">
        <div class="bg-white dark:bg-surface-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-3xl flex flex-col" style="max-height:80vh">
            <div class="flex items-center justify-between p-5 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-base font-bold text-slate-800 dark:text-white">Search file content in <span class="font-mono text-cyan-500 text-sm" x-text="cwd"></span></h3>
                <button @click="sr.open=false" class="text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <div class="p-5 space-y-3 border-b border-slate-200 dark:border-slate-700">
                <div class="flex gap-2">
                    <input x-model="sr.query" @keydown.enter="runSearch()" placeholder="Text to find (min 2 chars)" class="flex-1 px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500">
                    <input x-model="sr.include" @keydown.enter="runSearch()" placeholder="*.php" class="w-32 px-3 py-2.5 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white font-mono text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500">
                    <button @click="runSearch()" :disabled="sr.busy || sr.query.length < 2" class="px-5 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-semibold rounded-xl text-sm disabled:opacity-40 disabled:cursor-not-allowed" x-text="sr.busy ? 'Searching…' : 'Search'"></button>
                </div>
                <label class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 select-none">
                    <input type="checkbox" x-model="sr.skip" class="rounded border-slate-300 dark:border-slate-600 text-cyan-500 focus:ring-cyan-500">
                    Skip <code class="font-mono">node_modules</code>, <code class="font-mono">vendor</code>, <code class="font-mono">.git</code>
                </label>
            </div>
            <div class="overflow-auto flex-1">
                <template x-for="r in sr.results" :key="r.path + ':' + r.line">
                    <button @click="edit(r.path); sr.open=false" class="w-full text-left px-5 py-2.5 hover:bg-slate-50 dark:hover:bg-white/[0.03] border-b border-slate-100 dark:border-slate-800/40">
                        <div class="flex items-baseline gap-2">
                            <span class="font-mono text-xs font-semibold text-cyan-600 dark:text-cyan-400" x-text="r.name"></span>
                            <span class="font-mono text-[11px] text-slate-400">line <span x-text="r.line"></span></span>
                            <span class="font-mono text-[11px] text-slate-400 truncate" x-text="r.path"></span>
                        </div>
                        <code class="block mt-1 font-mono text-xs text-slate-600 dark:text-slate-300 truncate" x-text="r.text"></code>
                    </button>
                </template>
                <p x-show="sr.done && !sr.results.length" class="py-10 text-center text-sm text-slate-400">No matches.</p>
                <p x-show="!sr.done" class="py-10 text-center text-sm text-slate-400">Enter some text and hit Search.</p>
            </div>
            <p x-show="sr.done" class="px-5 py-3 border-t border-slate-200 dark:border-slate-700 text-xs text-slate-400">
                <span x-text="sr.results.length"></span> hit(s) in <span x-text="sr.scanned"></span> file(s) scanned.
                <span x-show="sr.truncated" class="text-amber-500">Results truncated — search hit its limit, narrow it down.</span>
            </p>
        </div>
    </div>

    {{-- Recycle Bin modal --}}
    <div x-show="tr.open" x-cloak x-transition.opacity class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="tr.open=false">
        <div class="bg-white dark:bg-surface-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-3xl flex flex-col" style="max-height:85vh">
            <div class="flex items-center justify-between p-5 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-base font-bold text-slate-800 dark:text-white">Recycle Bin</h3>
                <button @click="tr.open=false" class="text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <div class="overflow-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0"><tr class="bg-slate-50 dark:bg-white/5">
                        <th class="text-left px-5 py-2.5 text-xs font-semibold text-slate-500 dark:text-slate-400">Name</th>
                        <th class="text-left px-3 py-2.5 text-xs font-semibold text-slate-500 dark:text-slate-400">Original location</th>
                        <th class="text-left px-3 py-2.5 text-xs font-semibold text-slate-500 dark:text-slate-400">Size</th>
                        <th class="text-left px-3 py-2.5 text-xs font-semibold text-slate-500 dark:text-slate-400">Deleted</th>
                        <th class="text-right px-5 py-2.5 text-xs font-semibold text-slate-500 dark:text-slate-400">Operate</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/40">
                        <template x-for="i in tr.items" :key="i.id">
                            <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02]">
                                <td class="px-5 py-2.5"><div class="flex items-center gap-2"><span x-html="iconHtml(i)"></span><span class="font-mono text-xs text-slate-700 dark:text-slate-300" x-text="i.name"></span></div></td>
                                <td class="px-3 py-2.5 font-mono text-[11px] text-slate-400 max-w-xs truncate" x-text="i.original_path"></td>
                                <td class="px-3 py-2.5 text-xs text-slate-500" x-text="i.size"></td>
                                <td class="px-3 py-2.5 text-xs text-slate-500" x-text="i.deleted_at"></td>
                                <td class="px-5 py-2.5 text-right whitespace-nowrap">
                                    <button @click="trRestore(i)" class="text-xs font-medium text-cyan-600 dark:text-cyan-400 hover:underline">Restore</button>
                                    <button @click="trPurge(i)" class="text-xs font-medium text-red-500 hover:underline ml-3">Delete forever</button>
                                </td>
                            </tr>
                        </template>
                        <template x-if="!tr.items.length"><tr><td colspan="5" class="px-5 py-10 text-center text-sm text-slate-400">The recycle bin is empty.</td></tr></template>
                    </tbody>
                </table>
            </div>
            <p class="px-5 py-3 border-t border-slate-200 dark:border-slate-700 text-xs text-slate-400">Deleted files are kept here until you delete them forever. Restore puts them back where they came from.</p>
        </div>
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

    {{-- Right-click context menu --}}
    <div x-show="menu.open" x-cloak @click.outside="menu.open=false" @keydown.escape.window="menu.open=false"
         class="fixed z-50 w-56 py-1.5 rounded-xl bg-white dark:bg-surface-800 border border-slate-200 dark:border-slate-700 shadow-2xl text-sm"
         :style="`left:${menu.x}px; top:${menu.y}px`">
        <template x-if="menu.item.type === 'directory'">
            <button @click="openInNewTab(menu.item.path); menu.open=false" class="ctx"><span>Open in new tab</span></button>
        </template>
        <template x-if="menu.item.type === 'file'">
            <div>
                <button @click="edit(menu.item.path); menu.open=false" class="ctx"><span>Edit</span></button>
                <a :href="'/files/download?path='+encodeURIComponent(menu.item.path)" @click="menu.open=false" class="ctx"><span>Download</span></a>
                <template x-if="/\.(zip|tar\.gz|tgz|tar)$/i.test(menu.item.name)"><button @click="extract(menu.item.path); menu.open=false" class="ctx"><span>Extract</span></button></template>
            </div>
        </template>
        <button @click="chmodOpen(menu.item); menu.open=false" class="ctx"><span>Permission (chmod)</span></button>
        <div class="my-1 border-t border-slate-100 dark:border-slate-700/60"></div>
        <button @click="clipSet(menu.item,'copy'); menu.open=false" class="ctx"><span>Copy</span></button>
        <button @click="clipSet(menu.item,'cut'); menu.open=false" class="ctx"><span>Cut</span></button>
        <button @click="copyPath(menu.item.path); menu.open=false" class="ctx"><span>Copy Path</span></button>
        <button @click="compressOpen(menu.item); menu.open=false" class="ctx"><span>Compress (zip)</span></button>
        <div class="my-1 border-t border-slate-100 dark:border-slate-700/60"></div>
        <button @click="rename(menu.item.path, menu.item.name); menu.open=false" class="ctx"><span>Rename</span></button>
        <button @click="remove(menu.item.path, menu.item.name); menu.open=false" class="ctx"><span>Move to recycle bin</span></button>
        <button @click="remove(menu.item.path, menu.item.name, true); menu.open=false" class="ctx text-red-500"><span>Delete permanently</span></button>
        <div class="my-1 border-t border-slate-100 dark:border-slate-700/60"></div>
        <button @click="propsOpen(menu.item); menu.open=false" class="ctx"><span>Properties</span></button>
    </div>

    {{-- chmod Modal --}}
    <div x-show="cm.open" x-cloak x-transition.opacity class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="cm.open=false">
        <div class="bg-white dark:bg-surface-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-sm">
            <div class="flex items-center justify-between p-5 border-b border-slate-200 dark:border-slate-700"><h3 class="text-base font-bold text-slate-800 dark:text-white">Permissions — <span class="font-mono text-cyan-500 text-sm" x-text="cm.name"></span></h3><button @click="cm.open=false" class="text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button></div>
            <div class="p-5 space-y-4">
                <div class="grid grid-cols-3 gap-3">
                    <template x-for="(g,gi) in ['Owner','Group','Public']" :key="gi">
                        <div><p class="text-xs font-semibold text-slate-500 mb-1.5" x-text="g"></p>
                            <template x-for="(perm,pi) in ['r','w','x']" :key="pi">
                                <label class="flex items-center gap-1.5 text-sm text-slate-600 dark:text-slate-300"><input type="checkbox" x-model="cm.bits[gi][pi]" class="rounded text-cyan-500"><span x-text="{r:'Read',w:'Write',x:'Exec'}[perm]"></span></label>
                            </template>
                        </div>
                    </template>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-slate-500">Mode:</span>
                    <input x-model="cm.mode" @input="cm.syncFromMode()" class="w-20 px-3 py-1.5 rounded-lg bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-slate-700 font-mono text-sm text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-cyan-500" maxlength="4">
                </div>
                <div class="flex justify-end gap-2"><button @click="cm.open=false" class="px-4 py-2 bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-400 rounded-xl text-sm">Cancel</button><button @click="chmodApply()" class="px-4 py-2 bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-semibold rounded-xl text-sm">Apply</button></div>
            </div>
        </div>
    </div>

    {{-- Properties Modal --}}
    <div x-show="props.open" x-cloak x-transition.opacity class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="props.open=false">
        <div class="bg-white dark:bg-surface-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-md">
            <div class="flex items-center justify-between p-5 border-b border-slate-200 dark:border-slate-700"><h3 class="text-base font-bold text-slate-800 dark:text-white">Properties</h3><button @click="props.open=false" class="text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button></div>
            <div class="p-5 space-y-2 text-sm">
                <template x-for="(v,k) in props.data" :key="k">
                    <div class="flex gap-3"><span class="w-28 text-slate-400 capitalize" x-text="k"></span><span class="flex-1 text-slate-700 dark:text-slate-200 font-mono break-all" x-text="v"></span></div>
                </template>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.ctx { display:flex; align-items:center; gap:.5rem; width:100%; text-align:left; padding:.45rem 1rem; color:inherit; }
.ctx:hover { background: rgba(6,182,212,.1); }
.tb { display:inline-flex; align-items:center; gap:.375rem; padding:.5rem .75rem; border-radius:.75rem; font-size:.875rem; font-weight:500;
      background:#f1f5f9; color:#475569; transition:background-color .15s; }
.tb:hover { background:#e2e8f0; }
.dark .tb { background:rgba(255,255,255,.05); color:#94a3b8; }
.dark .tb:hover { background:rgba(255,255,255,.1); }
</style>
@endpush

@push('scripts')
<script>
function fileManager(initialPath, root, trashCount) {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    // Extension → label + brand-ish colour, so a .php reads as PHP at a glance.
    const TYPES = {
        php: ['PHP', '#777BB4'], js: ['JS', '#F0B400'], mjs: ['JS', '#F0B400'], cjs: ['JS', '#F0B400'],
        ts: ['TS', '#3178C6'], tsx: ['TSX', '#3178C6'], jsx: ['JSX', '#61DAFB'],
        css: ['CSS', '#2965F1'], scss: ['SASS', '#CC6699'], less: ['LESS', '#1D365D'],
        html: ['HTML', '#E34F26'], htm: ['HTML', '#E34F26'], vue: ['VUE', '#42B883'],
        json: ['JSON', '#6B7280'], xml: ['XML', '#F26522'], yml: ['YML', '#CB171E'], yaml: ['YML', '#CB171E'],
        md: ['MD', '#64748B'], txt: ['TXT', '#94A3B8'], log: ['LOG', '#94A3B8'],
        sql: ['SQL', '#00758F'], db: ['DB', '#00758F'], sqlite: ['DB', '#00758F'],
        sh: ['SH', '#4EAA25'], bash: ['SH', '#4EAA25'], py: ['PY', '#3776AB'], go: ['GO', '#00ADD8'],
        rb: ['RB', '#CC342D'], java: ['JAVA', '#E76F00'], c: ['C', '#00599C'], cpp: ['C++', '#00599C'],
        rs: ['RS', '#DEA584'], lock: ['LOCK', '#94A3B8'], env: ['ENV', '#10B981'],
        ini: ['INI', '#6B7280'], conf: ['CONF', '#6B7280'], htaccess: ['HTA', '#6B7280'],
        zip: ['ZIP', '#F59E0B'], gz: ['GZ', '#F59E0B'], tar: ['TAR', '#F59E0B'], tgz: ['TGZ', '#F59E0B'],
        rar: ['RAR', '#F59E0B'], '7z': ['7Z', '#F59E0B'],
        png: ['PNG', '#EC4899'], jpg: ['JPG', '#EC4899'], jpeg: ['JPG', '#EC4899'], gif: ['GIF', '#EC4899'],
        svg: ['SVG', '#FFB13B'], webp: ['WEBP', '#EC4899'], ico: ['ICO', '#EC4899'],
        pdf: ['PDF', '#DC2626'], mp4: ['MP4', '#8B5CF6'], mkv: ['MKV', '#8B5CF6'], mov: ['MOV', '#8B5CF6'],
        mp3: ['MP3', '#10B981'], wav: ['WAV', '#10B981'],
    };
    const esc = (s) => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    return {
        root, cwd: initialPath,
        tabs: [{ id: 1, path: initialPath }],
        activeId: 1,
        nextId: 2,
        files: [], breadcrumbs: [], parent: null, disk: { used_h: '—', total_h: '—', free_h: '—', percent: 0 },
        error: null, loading: false, view: 'list',

        editor: { open: false, path: '', content: '', status: '' },
        menu: { open: false, x: 0, y: 0, item: {} },
        clip: { path: '', name: '', mode: '' },
        cm: { open: false, path: '', name: '', mode: '755', bits: [[false,false,false],[false,false,false],[false,false,false]],
              syncFromMode() {
                  const m = (this.mode || '').padStart(3,'0').slice(-3);
                  for (let i=0;i<3;i++){ const d=parseInt(m[i]||'0',8); this.bits[i]=[!!(d&4),!!(d&2),!!(d&1)]; }
              } },
        props: { open: false, data: {} },
        sr: { open: false, query: '', include: '', skip: true, busy: false, done: false, results: [], scanned: 0, truncated: false },
        tr: { open: false, items: [], count: trashCount },

        init() { this.load(this.cwd); },

        // ---- icons ----
        extOf(name) {
            const lower = name.toLowerCase();
            if (lower.endsWith('.tar.gz')) return 'tgz';
            if (lower.startsWith('.') && !lower.slice(1).includes('.')) return lower.slice(1);
            const i = lower.lastIndexOf('.');
            return i > 0 ? lower.slice(i + 1) : '';
        },
        iconHtml(file, big = false) {
            const size = big ? 'width:2.5rem;height:2.5rem;font-size:.7rem;border-radius:.6rem'
                             : 'width:1.75rem;height:1.75rem;font-size:.5rem;border-radius:.5rem';
            if (file.type === 'directory') {
                const s = big ? 'w-10 h-10' : 'w-7 h-7';
                return `<svg class="${s} text-amber-400 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M2 6a2 2 0 012-2h5l2 2h9a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>`;
            }
            const [label, color] = TYPES[this.extOf(file.name)] || ['', '#94A3B8'];
            const glyph = label
                ? `<span style="color:${color};font-weight:800;letter-spacing:-.02em">${esc(label)}</span>`
                : `<svg style="width:60%;height:60%;color:${color}" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>`;
            return `<span class="shrink-0 inline-flex items-center justify-center" style="${size};background:${color}22;border:1px solid ${color}33">${glyph}</span>`;
        },

        // ---- tabs ----
        tabLabel(path) { return path === '/' ? '/' : path.split('/').filter(Boolean).pop() || '/'; },
        activate(id) {
            this.activeId = id;
            const t = this.tabs.find(t => t.id === id);
            if (t) this.load(t.path);
        },
        newTab() {
            const id = this.nextId++;
            this.tabs.push({ id, path: this.cwd });
            this.activate(id);
        },
        openInNewTab(path) {
            const id = this.nextId++;
            this.tabs.push({ id, path });
            this.activate(id);
        },
        closeTab(id) {
            if (this.tabs.length < 2) return;
            const i = this.tabs.findIndex(t => t.id === id);
            this.tabs.splice(i, 1);
            if (this.activeId === id) this.activate(this.tabs[Math.max(0, i - 1)].id);
        },

        // ---- navigation ----
        async load(path) {
            this.loading = true;
            this.error = null;
            try {
                const res = await fetch('/files/list?path=' + encodeURIComponent(path), { headers: { 'Accept': 'application/json' } });
                const d = await res.json();
                if (!res.ok) throw new Error(d.error || 'Could not read directory');
                this.cwd = d.path;
                this.files = d.files;
                this.breadcrumbs = d.breadcrumbs;
                this.parent = d.parent;
                this.disk = d.disk;
                const t = this.tabs.find(t => t.id === this.activeId);
                if (t) t.path = d.path;
                history.replaceState(null, '', '/files?path=' + encodeURIComponent(d.path));
            } catch (e) { this.error = e.message; this.files = []; }
            this.loading = false;
        },
        go(path) { this.load(path); },
        refresh() { this.load(this.cwd); },
        open(file) { file.type === 'directory' ? this.go(file.path) : this.edit(file.path); },

        openMenu(e, item) {
            this.menu.item = item;
            this.menu.x = Math.min(e.clientX, window.innerWidth - 240);
            this.menu.y = Math.min(e.clientY, window.innerHeight - 420);
            this.menu.open = true;
        },

        async post(url, body) {
            const res = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.error || ('HTTP ' + res.status));
            return data;
        },
        form(obj) {
            const fd = new FormData();
            Object.entries(obj).forEach(([k, v]) => Array.isArray(v) ? v.forEach(x => fd.append(k + '[]', x)) : fd.append(k, v));
            return fd;
        },

        async create(type) {
            const name = prompt(`New ${type} name:`);
            if (!name) return;
            try { await this.post('/files/create', this.form({ path: this.cwd, name, type })); this.refresh(); }
            catch (e) { alert(e.message); }
        },
        async rename(path, current) {
            const name = prompt('Rename to:', current);
            if (!name || name === current) return;
            try { await this.post('/files/rename', this.form({ path, name })); this.refresh(); }
            catch (e) { alert(e.message); }
        },
        async remove(path, name, permanent = false) {
            const msg = permanent
                ? `Permanently delete "${name}"? This cannot be undone.`
                : `Move "${name}" to the recycle bin?`;
            if (!confirm(msg)) return;
            try {
                await this.post('/files/delete', this.form({ path, permanent: permanent ? 1 : 0 }));
                if (!permanent) this.tr.count++;
                this.refresh();
            } catch (e) { alert(e.message); }
        },
        async upload(event) {
            const file = event.target.files[0];
            if (!file) return;
            try { await this.post('/files/upload', this.form({ path: this.cwd, file })); this.refresh(); }
            catch (e) { alert(e.message); }
            event.target.value = '';
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

        // ---- search ----
        searchOpen() { this.sr.open = true; this.sr.done = false; this.sr.results = []; },
        async runSearch() {
            if (this.sr.query.length < 2) return;
            this.sr.busy = true;
            try {
                const qs = new URLSearchParams({ path: this.cwd, query: this.sr.query, include: this.sr.include, skip: this.sr.skip ? 1 : 0 });
                const res = await fetch('/files/search?' + qs, { headers: { 'Accept': 'application/json' } });
                const d = await res.json();
                if (!res.ok) throw new Error(d.error || 'Search failed');
                this.sr.results = d.results; this.sr.scanned = d.scanned; this.sr.truncated = d.truncated; this.sr.done = true;
            } catch (e) { alert(e.message); }
            this.sr.busy = false;
        },

        // ---- recycle bin ----
        async trashOpen() { this.tr.open = true; await this.loadTrash(); },
        async loadTrash() {
            try {
                const res = await fetch('/files/trash', { headers: { 'Accept': 'application/json' } });
                const d = await res.json();
                this.tr.items = d.items || []; this.tr.count = this.tr.items.length;
            } catch (e) { alert(e.message); }
        },
        async trCall(path, id) {
            const res = await fetch(path, {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ id }),
            });
            const d = await res.json(); if (!res.ok) throw new Error(d.error || 'Error'); return d;
        },
        async trRestore(item) {
            if (!confirm(`Restore "${item.name}" to ${item.original_path}?`)) return;
            try {
                const d = await this.trCall('/files/trash/restore', item.id);
                this.tr.items = d.items || []; this.tr.count = this.tr.items.length;
                this.refresh();
            } catch (e) { alert(e.message); }
        },
        async trPurge(item) {
            if (!confirm(`Permanently delete "${item.name}"? This cannot be undone.`)) return;
            try {
                const d = await this.trCall('/files/trash/purge', item.id);
                this.tr.items = d.items || []; this.tr.count = this.tr.items.length;
            } catch (e) { alert(e.message); }
        },

        // ---- context-menu operations ----
        clipSet(item, mode) { this.clip = { path: item.path, name: item.name, mode }; },
        async paste() {
            if (!this.clip.path) return;
            try {
                await this.post('/files/transfer', this.form({ source: this.clip.path, dest: this.cwd, action: this.clip.mode === 'cut' ? 'move' : 'copy' }));
                this.clip = { path: '', name: '', mode: '' };
                this.refresh();
            } catch (e) { alert(e.message); }
        },
        copyPath(path) { navigator.clipboard.writeText(path).then(() => {}, () => {}); },
        async extract(path) {
            if (!confirm('Extract "' + path.split('/').pop() + '" here?')) return;
            try { await this.post('/files/extract', this.form({ path })); this.refresh(); } catch (e) { alert(e.message); }
        },
        compressOpen(item) {
            const name = prompt('Archive name:', item.name + '.zip');
            if (!name) return;
            this.post('/files/compress', this.form({ path: this.cwd, name, items: [item.name] }))
                .then(() => this.refresh()).catch(e => alert(e.message));
        },
        chmodOpen(item) {
            this.cm.path = item.path; this.cm.name = item.name;
            fetch('/files/info?path=' + encodeURIComponent(item.path), { headers: { 'Accept': 'application/json' } })
                .then(r => r.json()).then(d => { this.cm.mode = d.mode || '644'; this.cm.syncFromMode(); this.cm.open = true; })
                .catch(() => { this.cm.mode = '644'; this.cm.syncFromMode(); this.cm.open = true; });
        },
        async chmodApply() {
            let mode = '';
            for (let i=0;i<3;i++){ let d=0; if(this.cm.bits[i][0])d+=4; if(this.cm.bits[i][1])d+=2; if(this.cm.bits[i][2])d+=1; mode+=d; }
            try { await this.post('/files/chmod', this.form({ path: this.cm.path, mode })); this.cm.open=false; this.refresh(); }
            catch (e) { alert(e.message); }
        },
        async propsOpen(item) {
            this.props = { open: true, data: { name: item.name, loading: '…' } };
            try {
                const res = await fetch('/files/info?path=' + encodeURIComponent(item.path), { headers: { 'Accept': 'application/json' } });
                const d = await res.json();
                this.props.data = { name: d.name, type: d.type, size: d.size, permissions: d.permissions + ' (' + d.mode + ')', owner: d.owner, path: d.path, modified: d.modified, accessed: d.accessed };
            } catch (e) { this.props.data = { error: e.message }; }
        },
    }
}
</script>
@endpush
