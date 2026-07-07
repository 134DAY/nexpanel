@extends('layouts.app')

@section('title', $title ?? 'Coming Soon')

@section('content')
<div class="flex flex-col items-center justify-center py-32">
    <div class="w-16 h-16 rounded-2xl bg-slate-100 dark:bg-white/5 flex items-center justify-center mb-4">
        <svg class="w-8 h-8 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
        </svg>
    </div>
    <h2 class="text-xl font-bold text-slate-800 dark:text-white mb-2">{{ $title ?? 'Coming Soon' }}</h2>
    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $description ?? 'This feature is coming in a future update.' }}</p>
</div>
@endsection
