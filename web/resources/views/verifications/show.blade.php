<x-layouts.app title="TrueSeal Verdict">
    @php
        $isFail = $verification->status === 'fail';
        $isPass = $verification->status === 'pass';
        $verdictPulse = $isFail ? 'animate-pulse-red' : ($isPass ? 'animate-pulse-green' : '');
        $verdictBorder = $isFail ? 'border-red-400/40' : ($isPass ? 'border-emerald-400/40' : 'border-amber-400/40');
        $verdictBg = $isFail ? 'bg-red-500/10' : ($isPass ? 'bg-emerald-400/10' : 'bg-amber-400/10');
        $verdictText = $isFail ? 'text-red-100' : ($isPass ? 'text-emerald-100' : 'text-amber-100');
        $scoreColor = $isFail ? 'text-red-400' : ($isPass ? 'text-emerald-400' : 'text-amber-400');
        $ringColor = $isFail ? 'stroke-red-400' : ($isPass ? 'stroke-emerald-400' : 'stroke-amber-400');
        $score = $verification->score ?? 0;
        // SVG circle: circumference = 2πr = 2π×54 ≈ 339.29
        $circumference = 339.29;
        $dashOffset = $circumference - ($circumference * $score / 100);
    @endphp

    <div class="mb-8 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
        <div class="animate-fade-in-up">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-emerald-300">Verification result</p>
            <h1 class="mt-2 text-3xl font-bold text-white">{{ $verification->candidate_name }}</h1>
            <p class="mt-2 text-sm text-slate-400">{{ $verification->institution->name }} · {{ $verification->original_filename }}</p>
        </div>
        <a href="{{ route('dashboard') }}" class="animate-fade-in-up delay-2 inline-flex items-center justify-center rounded-lg border border-white/10 px-4 py-3 text-sm text-slate-300 transition hover:border-white/20 hover:text-white">Back to dashboard</a>
    </div>

    {{-- Verdict Banner --}}
    <section class="animate-fade-in-up delay-1 mb-6 rounded-xl border p-6 {{ $verdictBorder }} {{ $verdictBg }} {{ $verdictText }} {{ $verdictPulse }}">
        <div class="flex flex-col justify-between gap-6 sm:flex-row sm:items-center">
            <div>
                <div class="text-xs uppercase tracking-[0.2em] opacity-60">Verdict</div>
                <div class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">
                    @if ($verification->verdict === 'FAIL')
                        ⚠ FAIL: MANIPULATION DETECTED
                    @elseif ($verification->verdict === 'PASS')
                        ✓ PASS: NO MANIPULATION DETECTED
                    @else
                        {{ strtoupper($verification->verdict ?? $verification->status) }}
                    @endif
                </div>
            </div>

            {{-- Score Gauge --}}
            <div class="flex items-center gap-4">
                <div class="relative h-28 w-28 shrink-0">
                    <svg class="h-28 w-28 -rotate-90" viewBox="0 0 120 120">
                        <circle cx="60" cy="60" r="54" fill="none" stroke="rgba(255,255,255,0.06)" stroke-width="8"/>
                        <circle cx="60" cy="60" r="54" fill="none" class="gauge-ring {{ $ringColor }}" stroke-width="8" stroke-linecap="round"
                            stroke-dasharray="{{ $circumference }}"
                            stroke-dashoffset="{{ $dashOffset }}"/>
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="animate-count-up text-3xl font-black {{ $scoreColor }}">{{ $score }}</span>
                        <span class="text-[10px] uppercase tracking-wider opacity-50">score</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-[1fr_0.75fr]">
        {{-- Left: Images --}}
        <section class="grid gap-6 md:grid-cols-2">
            <div class="animate-fade-in-up delay-2 glass rounded-xl p-4">
                <h2 class="mb-3 text-sm font-bold text-white">Original certificate</h2>
                <div class="scan-overlay rounded-lg">
                    <img src="{{ route('verifications.asset', [$verification, 'original']) }}" alt="Original certificate" class="aspect-[4/3] w-full rounded-lg border border-white/10 object-contain bg-slate-950">
                </div>
            </div>
            <div class="animate-fade-in-up delay-3 glass rounded-xl p-4">
                <h2 class="mb-3 text-sm font-bold text-white">ELA heatmap</h2>
                @if ($verification->heatmap_path)
                    <img src="{{ route('verifications.asset', [$verification, 'heatmap']) }}" alt="ELA heatmap" class="aspect-[4/3] w-full rounded-lg border border-white/10 object-contain bg-slate-950">
                    <p class="mt-2 text-xs text-slate-500">Red regions indicate compression inconsistencies — evidence of pixel-level manipulation.</p>
                @else
                    <div class="grid aspect-[4/3] place-items-center rounded-lg border border-white/10 bg-slate-950 text-sm text-slate-500">Heatmap unavailable</div>
                @endif
            </div>
        </section>

        {{-- Right: Findings + Payment --}}
        <aside class="space-y-6">
            {{-- Forensic Findings --}}
            <section class="animate-fade-in-up delay-4 glass rounded-xl p-5">
                <h2 class="text-base font-bold text-white">Forensic findings</h2>
                <ul class="mt-4 space-y-3 text-sm text-slate-300">
                    @forelse (($verification->findings ?? []) as $index => $finding)
                        <li class="animate-slide-in rounded-lg border border-white/10 bg-slate-950/70 px-4 py-3 leading-relaxed" style="animation-delay: {{ 0.5 + $index * 0.15 }}s">
                            @if (str_contains($finding, 'Critical') || str_contains($finding, 'high-compression'))
                                <span class="mb-1 inline-block rounded bg-red-400/10 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-red-300">High Risk</span>
                            @elseif (str_contains($finding, 'moderate') || str_contains($finding, 'anomal'))
                                <span class="mb-1 inline-block rounded bg-amber-400/10 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-300">Medium Risk</span>
                            @endif
                            <br>{{ $finding }}
                        </li>
                    @empty
                        <li class="text-slate-500">No findings recorded yet.</li>
                    @endforelse
                </ul>
            </section>

            {{-- Payment & Royalty --}}
            <section class="animate-fade-in-up delay-5 glass rounded-xl p-5">
                <h2 class="text-base font-bold text-white">Payment & royalty routing</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-400">Payment status</dt>
                        <dd class="font-bold {{ $verification->payment?->status === 'paid' ? 'text-emerald-300' : 'text-slate-300' }}">{{ strtoupper($verification->payment?->status ?? 'unpaid') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-400">Provider</dt>
                        <dd class="font-semibold text-slate-200">{{ strtoupper($verification->payment?->provider ?? 'N/A') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-400">Reference</dt>
                        <dd class="font-mono text-xs text-slate-300">{{ $verification->payment?->transaction_ref ?? 'N/A' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-t border-white/[0.06] pt-3">
                        <dt class="text-slate-400">University royalty</dt>
                        <dd class="font-bold text-cyan-200">NGN {{ number_format(($verification->payment?->royalty_amount_kobo ?? 0) / 100) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-400">Transfer status</dt>
                        <dd class="font-bold {{ ($verification->royaltyLedgerEntry?->transfer_status === 'success') ? 'text-emerald-300' : 'text-amber-300' }}">
                            {{ strtoupper($verification->royaltyLedgerEntry?->transfer_status ?? $verification->royaltyLedgerEntry?->status ?? 'pending') }}
                        </dd>
                    </div>
                    @if ($verification->royaltyLedgerEntry?->transfer_reference)
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-400">Transfer ref</dt>
                            <dd class="font-mono text-xs text-slate-300">{{ $verification->royaltyLedgerEntry->transfer_reference }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            {{-- Engine Error --}}
            @if ($verification->engine_error)
                <section class="animate-fade-in-up delay-6 rounded-xl border border-red-400/30 bg-red-400/10 p-5 text-sm text-red-100">
                    <span class="mb-1 inline-block text-xs font-bold uppercase tracking-wider text-red-300">Engine Error</span>
                    <br>{{ $verification->engine_error }}
                </section>
            @endif
        </aside>
    </div>
</x-layouts.app>
