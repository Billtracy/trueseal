@props(['title' => 'TrueSeal'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="TrueSeal — AI-powered forensic certificate verification with automated university royalty routing via Squad API.">
        <meta name="theme-color" content="#070b12">
        <title>{{ $title }}</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#070b12] text-slate-100 antialiased">
        <div class="flex min-h-screen flex-col">
            <header class="border-b border-white/10 bg-[#090f19]/95 backdrop-blur-md">
                <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                    <a href="{{ route(auth()->check() ? 'dashboard' : 'login') }}" class="flex items-center gap-3 group">
                        <span class="grid h-9 w-9 place-items-center rounded-lg bg-emerald-400 text-sm font-black text-slate-950 transition group-hover:shadow-lg group-hover:shadow-emerald-400/25">TS</span>
                        <span>
                            <span class="block text-sm font-semibold tracking-wide text-white">TrueSeal</span>
                            <span class="block text-xs text-slate-400">Forensic credential verification</span>
                        </span>
                    </a>

                    @auth
                        <nav class="flex items-center gap-3">
                            <a href="{{ route('verifications.create') }}" class="rounded-md bg-emerald-400 px-3 py-2 text-sm font-semibold text-slate-950 transition hover:bg-emerald-300 hover:shadow-lg hover:shadow-emerald-400/20">New scan</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="rounded-md border border-white/10 px-3 py-2 text-sm text-slate-300 transition hover:border-red-400/70 hover:text-red-200">Logout</button>
                            </form>
                        </nav>
                    @endauth
                </div>
            </header>

            <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 lg:px-8">
                @if (session('status'))
                    <div class="mb-6 animate-fade-in-up rounded-lg border border-emerald-400/30 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-100">
                        {{ session('status') }}
                    </div>
                @endif

                {{ $slot }}
            </main>

            <footer class="border-t border-white/[0.06] bg-[#060a10]">
                <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-5 sm:px-6 lg:px-8">
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        <span class="grid h-5 w-5 place-items-center rounded bg-emerald-400/20 text-[10px] font-bold text-emerald-300">TS</span>
                        <span>TrueSeal &middot; Squad Hackathon 3.0</span>
                    </div>
                    <div class="text-xs text-slate-600">Powered by Squad API</div>
                </div>
            </footer>
        </div>
    </body>
</html>
