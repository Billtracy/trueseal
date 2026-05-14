<x-layouts.app title="TrueSeal Login">
    <div class="gradient-bg grid-pattern -mx-4 -mt-8 px-4 py-10 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
        <div class="mx-auto grid max-w-5xl items-center gap-10 py-6 lg:grid-cols-[1.1fr_0.9fr]">
            <section class="animate-fade-in-up">
                <p class="mb-3 text-sm font-semibold uppercase tracking-[0.2em] text-squad-crimson">Institutional trust layer</p>
                <h1 class="max-w-2xl text-4xl font-bold leading-tight text-white sm:text-5xl">Forensic certificate verification with <span class="text-squad-crimson">royalty routing.</span></h1>
                <p class="mt-5 max-w-xl text-base leading-7 text-slate-300">
                    TrueSeal gives HR teams a payment-gated verification workflow. Every time a certificate is checked, the issuing university automatically receives a royalty payment via Squad Transfer API.
                </p>
                <div class="mt-8 grid max-w-xl grid-cols-3 gap-3">
                    <div class="glass hover-lift animate-fade-in-up delay-1 rounded-lg p-4">
                        <div class="text-2xl font-bold text-white">₦5,000</div>
                        <div class="mt-1 text-xs text-slate-400">Verification fee</div>
                    </div>
                    <div class="glass hover-lift animate-fade-in-up delay-2 rounded-lg p-4">
                        <div class="text-2xl font-bold text-squad-crimson">20%</div>
                        <div class="mt-1 text-xs text-slate-400">University royalty</div>
                    </div>
                    <div class="glass hover-lift animate-fade-in-up delay-3 rounded-lg p-4">
                        <div class="text-2xl font-bold text-red-300">ELA</div>
                        <div class="mt-1 text-xs text-slate-400">Pixel forensics</div>
                    </div>
                </div>

                <div class="mt-8 flex items-center gap-4">
                    <div class="flex -space-x-2">
                        <div class="h-8 w-8 rounded-full border-2 border-[#070b12] bg-squad-crimson/30 grid place-items-center text-xs font-bold text-squad-crimson">U</div>
                        <div class="h-8 w-8 rounded-full border-2 border-[#070b12] bg-cyan-500/30 grid place-items-center text-xs font-bold text-cyan-300">I</div>
                        <div class="h-8 w-8 rounded-full border-2 border-[#070b12] bg-amber-500/30 grid place-items-center text-xs font-bold text-amber-300">C</div>
                        <div class="h-8 w-8 rounded-full border-2 border-[#070b12] bg-purple-500/30 grid place-items-center text-xs font-bold text-purple-300">A</div>
                    </div>
                    <span class="text-xs text-slate-400">4 Nigerian universities onboarded as Squad sub-merchants</span>
                </div>
            </section>

            <section class="glass-strong animate-fade-in-up delay-3 rounded-xl p-6 shadow-2xl shadow-black/40">
                <h2 class="text-xl font-bold text-white">HR Manager Access</h2>
                <p class="mt-1 text-sm text-slate-400">Demo: <span class="font-mono text-squad-crimson/80">hr@trueseal.test</span> / <span class="font-mono text-squad-crimson/80">password</span></p>

                <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
                    @csrf
                    <label class="block">
                        <span class="text-sm font-medium text-slate-300">Email</span>
                        <input name="email" type="email" value="{{ old('email', 'hr@trueseal.test') }}" required autofocus class="mt-2 w-full rounded-lg border border-white/10 bg-slate-950/80 px-4 py-3 text-sm text-white outline-none transition focus:border-squad-crimson focus:ring-1 focus:ring-squad-crimson/30">
                        @error('email')
                            <span class="mt-2 block text-sm text-red-300">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-300">Password</span>
                        <input name="password" type="password" required class="mt-2 w-full rounded-lg border border-white/10 bg-slate-950/80 px-4 py-3 text-sm text-white outline-none transition focus:border-squad-crimson focus:ring-1 focus:ring-squad-crimson/30">
                        @error('password')
                            <span class="mt-2 block text-sm text-red-300">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="flex items-center gap-2 text-sm text-slate-400">
                        <input name="remember" type="checkbox" class="rounded border-white/20 bg-slate-950 text-squad-crimson focus:ring-squad-crimson/30">
                        Keep this session active
                    </label>

                    <button class="w-full rounded-lg bg-squad-crimson px-4 py-3 text-sm font-bold text-white transition hover:bg-squad-crimson/85 hover:shadow-lg hover:shadow-squad-crimson/20 active:scale-[0.98]">Enter dashboard</button>
                </form>

                <div class="mt-5 flex items-center gap-2 rounded-lg border border-white/[0.06] bg-white/[0.02] px-3 py-2.5 text-xs text-slate-500">
                    <svg class="h-4 w-4 shrink-0 text-squad-crimson/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    <span>Squad Hackathon 3.0 — Challenge 01: Proof of Life</span>
                </div>
            </section>
        </div>
    </div>
</x-layouts.app>
