@extends('layouts.app')

@section('title', 'Profile')
@section('subheader', 'Manage your account information')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    {{-- Success Message --}}
    @if(session('status') === 'profile-updated')
    <div class="flex items-center gap-3 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-sm">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Profile updated successfully!
    </div>
    @endif

    @if($errors->any())
    <div class="flex items-center gap-3 p-4 rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 text-red-700 dark:text-red-400 text-sm">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
        {{ $errors->first() }}
    </div>
    @endif

    {{-- Profile Information --}}
    <div class="bg-white dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-slate-800/60 p-6">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-800 dark:text-white">Profile Information</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Update your account name and email address</p>
            </div>
        </div>

        <form method="POST" action="{{ route('profile.update') }}" class="space-y-5">
            @csrf
            @method('PATCH')

            {{-- Avatar Preview --}}
            <div class="flex items-center gap-4 p-4 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-800/60">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center text-2xl font-bold text-white shrink-0">
                    {{ substr(Auth::user()->name ?? 'A', 0, 1) }}
                </div>
                <div>
                    <p class="text-base font-semibold text-slate-800 dark:text-white">{{ Auth::user()->name }}</p>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ Auth::user()->email }}</p>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Member since {{ Auth::user()->created_at->format('M d, Y') }}</p>
                </div>
            </div>

            {{-- Name --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Name</label>
                <input type="text" name="name" value="{{ old('name', Auth::user()->name) }}" required
                       class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all">
            </div>

            {{-- Email --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Email</label>
                <input type="email" name="email" value="{{ old('email', Auth::user()->email) }}" required
                       class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all">
            </div>

            {{-- Save --}}
            <div>
                <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-600 hover:to-blue-700 text-white font-semibold rounded-xl shadow-lg shadow-cyan-500/25 hover:shadow-cyan-500/40 transition-all text-sm">
                    Save Profile
                </button>
            </div>
        </form>
    </div>

    {{-- Delete Account --}}
    <div class="bg-white dark:bg-white/5 rounded-2xl border border-red-200 dark:border-red-500/20 p-6" x-data="{ confirmDelete: false }">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-rose-500 to-red-600 flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
            </div>
            <div>
                <h2 class="text-lg font-bold text-red-600 dark:text-red-400">Danger Zone</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Permanently delete your account</p>
            </div>
        </div>

        <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">Once your account is deleted, all of its resources and data will be permanently deleted. This action cannot be undone.</p>

        <button @click="confirmDelete = true" x-show="!confirmDelete"
                class="px-5 py-2 bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 font-medium rounded-xl border border-red-200 dark:border-red-500/20 hover:bg-red-100 dark:hover:bg-red-500/20 transition-colors text-sm">
            Delete Account
        </button>

        <form method="POST" action="{{ route('profile.destroy') }}" x-show="confirmDelete" x-transition class="space-y-4 p-4 rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20">
            @csrf
            @method('DELETE')
            <p class="text-sm font-medium text-red-700 dark:text-red-400">Are you sure? Enter your password to confirm:</p>
            <input type="password" name="password" required placeholder="Your password"
                   class="w-full px-4 py-3 rounded-xl bg-white dark:bg-white/5 border border-red-300 dark:border-red-500/30 text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all">
            <div class="flex gap-3">
                <button type="submit" class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-xl transition-colors text-sm">
                    Yes, Delete My Account
                </button>
                <button type="button" @click="confirmDelete = false" class="px-5 py-2 bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-400 font-medium rounded-xl hover:bg-slate-200 dark:hover:bg-white/20 transition-colors text-sm">
                    Cancel
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
