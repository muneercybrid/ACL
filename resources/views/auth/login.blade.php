@extends('layouts.auth')

@section('title', 'Sign in — ACL')

@section('content')
<div class="w-full max-w-md">
    <div class="text-center mb-8">
        <div class="font-mono text-terminal text-sm mb-3">~/anyone-can-learn $ ./login <span class="cursor-blink">▊</span></div>
        <h1 class="text-3xl font-extrabold text-white tracking-tight">ACL <span class="text-brand">//</span> ACCESS</h1>
        <p class="text-slate-400 text-sm mt-2">Learn anything. Anywhere. Anyone.</p>
    </div>

    <form method="POST" action="{{ route('login') }}"
          class="bg-surface/80 border border-edge rounded-xl p-6 backdrop-blur shadow-[0_0_50px_rgba(34,211,238,0.07)]">
        @csrf

        @error('email')
            <p class="mb-4 text-xs font-mono text-brand">[!] {{ $message }}</p>
        @enderror

        <label class="block mb-4">
            <span class="text-[11px] font-mono uppercase tracking-widest text-slate-400">Email</span>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="mt-1 w-full rounded-lg bg-abyss border border-edge px-3 py-2.5 text-sm text-white placeholder-slate-600 transition focus:outline-none focus:border-terminal focus:ring-1 focus:ring-terminal/60">
        </label>

        <label class="block mb-5">
            <span class="text-[11px] font-mono uppercase tracking-widest text-slate-400">Password</span>
            <input type="password" name="password" required
                   class="mt-1 w-full rounded-lg bg-abyss border border-edge px-3 py-2.5 text-sm text-white placeholder-slate-600 transition focus:outline-none focus:border-terminal focus:ring-1 focus:ring-terminal/60">
        </label>

        <label class="flex items-center gap-2 mb-5 text-xs text-slate-400">
            <input type="checkbox" name="remember" value="1" class="rounded border-edge bg-abyss accent-[#9fef00]">
            Keep session alive
        </label>

        <button type="submit"
                class="press w-full rounded-lg bg-brand hover:bg-brand/90 text-white font-bold py-2.5 text-sm tracking-widest transition">
            INITIALISE SESSION
        </button>
    </form>
</div>
@endsection
