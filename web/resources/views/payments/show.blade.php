<x-layouts.app title="TrueSeal Payment">
    <div class="mx-auto max-w-3xl">
        <div class="mb-8">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-emerald-300">Squad checkout</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">Forensic verification fee</h1>
            <p class="mt-2 text-sm leading-6 text-slate-400">Payment unlocks the scan and records the university royalty ledger entry.</p>
        </div>

        <section class="rounded-lg border border-white/10 bg-white/[0.04] p-6">
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-lg border border-white/10 bg-slate-950/70 p-4">
                    <div class="text-xs uppercase tracking-wide text-slate-500">Total</div>
                    <div class="mt-2 text-2xl font-semibold text-white">{{ $feeSplit->formatNaira($payment->amount_kobo) }}</div>
                </div>
                <div class="rounded-lg border border-white/10 bg-slate-950/70 p-4">
                    <div class="text-xs uppercase tracking-wide text-slate-500">Platform</div>
                    <div class="mt-2 text-2xl font-semibold text-white">{{ $feeSplit->formatNaira($payment->platform_amount_kobo) }}</div>
                </div>
                <div class="rounded-lg border border-cyan-300/20 bg-cyan-300/10 p-4">
                    <div class="text-xs uppercase tracking-wide text-cyan-100/70">University</div>
                    <div class="mt-2 text-2xl font-semibold text-cyan-100">{{ $feeSplit->formatNaira($payment->royalty_amount_kobo) }}</div>
                </div>
            </div>

            <dl class="mt-6 grid gap-4 border-t border-white/10 pt-6 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Candidate</dt>
                    <dd class="mt-1 text-sm text-white">{{ $verification->candidate_name }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Issuing university</dt>
                    <dd class="mt-1 text-sm text-white">{{ $verification->institution->name }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Reference</dt>
                    <dd class="mt-1 font-mono text-sm text-slate-300">{{ $payment->transaction_ref }}</dd>
                </div>
            </dl>

            {{-- Royalty routing explanation --}}
            <div class="mt-6 rounded-lg border border-cyan-300/20 bg-cyan-300/5 p-4">
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-cyan-400/20 text-xs text-cyan-200">₦</div>
                    <div class="text-sm text-cyan-100/80">
                        <p class="font-semibold text-cyan-100">Automated royalty routing</p>
                        <p class="mt-1">Upon payment, <span class="font-semibold text-white">{{ $feeSplit->formatNaira($payment->royalty_amount_kobo) }}</span> is automatically transferred to <span class="font-semibold text-white">{{ $verification->institution->name }}</span>'s bank account via Squad Transfer API.</p>
                    </div>
                </div>
            </div>

            @if ($payment->provider === 'mock')
                <div class="mt-6 rounded-lg border border-amber-300/30 bg-amber-300/10 p-4 text-sm text-amber-100">
                    Squad sandbox credentials are not configured or checkout initiation failed, so this demo is using the mock payment fallback.
                </div>
            @endif

            <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                @if ($payment->provider === 'squad' && $squadPublicKey)
                    {{-- Inline Squad Payment Modal --}}
                    <button id="squad-pay-btn" class="inline-flex flex-1 items-center justify-center rounded-md bg-emerald-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-emerald-300">
                        Pay with Squad
                    </button>
                    {{-- Fallback redirect --}}
                    <a href="{{ $payment->checkout_url }}" class="inline-flex items-center justify-center rounded-md border border-white/10 px-4 py-3 text-sm text-slate-300 hover:border-white/20">
                        Open checkout page instead
                    </a>
                @else
                    <a href="{{ $payment->checkout_url }}" class="inline-flex flex-1 items-center justify-center rounded-md bg-emerald-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-emerald-300">
                        {{ $payment->provider === 'mock' ? 'Complete mock payment' : 'Open Squad checkout' }}
                    </a>
                @endif
                <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center rounded-md border border-white/10 px-4 py-3 text-sm text-slate-300 hover:border-white/20">Return to dashboard</a>
            </div>
        </section>
    </div>

    @if ($payment->provider === 'squad' && $squadPublicKey)
        <script src="https://checkout.squadco.com/widget/squad.min.js"></script>
        <script>
            document.getElementById('squad-pay-btn')?.addEventListener('click', function () {
                const squadInstance = new window.squad({
                    onClose: () => console.log('Squad modal closed'),
                    onLoad: () => console.log('Squad modal loaded'),
                    onSuccess: () => window.location.href = @json(route('payments.callback', ['transaction_ref' => $payment->transaction_ref])),
                    key: @json($squadPublicKey),
                    email: @json($verification->candidate_email),
                    amount: {{ $payment->amount_kobo }},
                    currency_code: 'NGN',
                    transaction_ref: @json($payment->transaction_ref),
                    customer_name: @json($verification->candidate_name),
                    metadata: {
                        verification_id: {{ $verification->id }},
                        institution: @json($verification->institution->name),
                    },
                });
                squadInstance.setup();
                squadInstance.open();
            });
        </script>
    @endif
</x-layouts.app>
