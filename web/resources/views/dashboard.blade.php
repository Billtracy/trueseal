<x-layouts.app title="TrueSeal Dashboard">
    <div class="mb-8 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
        <div class="animate-fade-in-up">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-squad-crimson">ForgeSight dashboard</p>
            <h1 class="mt-2 text-3xl font-bold text-white">Verification command center</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-400">Track paid scans, forensic verdicts, and the royalty ledger for issuing institutions.</p>
        </div>
        <a href="{{ route('verifications.create') }}" class="animate-fade-in-up delay-2 inline-flex items-center justify-center gap-2 rounded-lg bg-squad-crimson px-5 py-3 text-sm font-bold text-white transition hover:bg-squad-crimson/85 hover:shadow-lg hover:shadow-squad-crimson/20 active:scale-[0.98]">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Initiate new scan
        </a>
    </div>

    {{-- Stats Grid --}}
    <section class="mb-8 grid gap-4 md:grid-cols-4">
        <div class="glass hover-lift animate-fade-in-up delay-1 rounded-xl p-5">
            <div class="flex items-center justify-between">
                <div class="text-sm text-slate-400">Verifications</div>
                <div class="grid h-8 w-8 place-items-center rounded-lg bg-slate-400/10">
                    <svg class="h-4 w-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
            </div>
            <div class="mt-3 text-3xl font-bold text-white">{{ $totalVerifications }}</div>
        </div>
        <div class="glass hover-lift animate-fade-in-up delay-2 rounded-xl p-5">
            <div class="flex items-center justify-between">
                <div class="text-sm text-slate-400">Paid payments</div>
                <div class="grid h-8 w-8 place-items-center rounded-lg bg-squad-teal/15">
                    <svg class="h-4 w-4 text-squad-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <div class="mt-3 text-3xl font-bold text-squad-teal">{{ $paidPayments }}</div>
        </div>
        <div class="glass hover-lift animate-fade-in-up delay-3 rounded-xl p-5">
            <div class="flex items-center justify-between">
                <div class="text-sm text-slate-400">Manipulations</div>
                <div class="grid h-8 w-8 place-items-center rounded-lg bg-red-400/10">
                    <svg class="h-4 w-4 text-red-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
            </div>
            <div class="mt-3 text-3xl font-bold text-red-300">{{ $failedVerifications }}</div>
        </div>
        <div class="glass hover-lift animate-fade-in-up delay-4 rounded-xl p-5">
            <div class="flex items-center justify-between">
                <div class="text-sm text-slate-400">Royalties recorded</div>
                <div class="grid h-8 w-8 place-items-center rounded-lg bg-cyan-400/10">
                    <svg class="h-4 w-4 text-cyan-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
            </div>
            <div class="mt-3 text-3xl font-bold text-cyan-200">{{ $feeSplit->formatNaira($royaltyTotal) }}</div>
        </div>
    </section>

    {{-- Verifications Table --}}
    <section class="animate-fade-in-up delay-5 overflow-hidden rounded-xl border border-white/10 bg-white/[0.03]">
        <div class="border-b border-white/10 px-5 py-4">
            <h2 class="text-base font-bold text-white">Recent verifications</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-white/10 text-left text-sm">
                <thead class="bg-white/[0.03] text-xs uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-5 py-3">Candidate</th>
                        <th class="px-5 py-3">Institution</th>
                        <th class="px-5 py-3">Payment</th>
                        <th class="px-5 py-3">Royalty</th>
                        <th class="px-5 py-3">Verdict</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/[0.06]">
                    @forelse ($verifications as $verification)
                        @php
                            $statusClass = match ($verification->status) {
                                'fail' => 'bg-red-400/10 text-red-200 border-red-400/30',
                                'pass' => 'bg-emerald-400/10 text-emerald-200 border-emerald-400/30',
                                'error' => 'bg-amber-400/10 text-amber-200 border-amber-400/30',
                                default => 'bg-slate-400/10 text-slate-200 border-white/10',
                            };
                        @endphp
                        <tr class="transition hover:bg-white/[0.03]">
                            <td class="px-5 py-4">
                                <div class="font-medium text-white">{{ $verification->candidate_name }}</div>
                                <div class="text-xs text-slate-500">{{ $verification->candidate_email }}</div>
                            </td>
                            <td class="px-5 py-4 text-slate-300">{{ $verification->institution->name }}</td>
                            <td class="px-5 py-4">
                                <span class="rounded-md border px-2 py-1 text-xs {{ $verification->payment?->status === 'paid' ? 'border-squad-teal/30 bg-squad-teal/10 text-emerald-200' : 'border-white/10 bg-white/[0.03] text-slate-300' }}">
                                    {{ strtoupper($verification->payment?->status ?? 'unpaid') }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-cyan-100">{{ $verification->payment ? $feeSplit->formatNaira($verification->payment->royalty_amount_kobo) : 'NGN 0' }}</td>
                            <td class="px-5 py-4">
                                <span class="rounded-md border px-2 py-1 text-xs font-semibold {{ $statusClass }}">{{ strtoupper($verification->verdict ?? $verification->status) }}</span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <a href="{{ route('verifications.show', $verification) }}" class="inline-flex items-center gap-1 text-sm font-medium text-squad-crimson transition hover:text-squad-crimson/80">
                                    Open
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-slate-400">No verifications yet. Start with a new certificate scan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-white/10 px-5 py-4">
            {{ $verifications->links() }}
        </div>
    </section>
</x-layouts.app>
