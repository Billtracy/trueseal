<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\RoyaltyLedgerEntry;
use App\Models\Verification;
use App\Services\FeeSplit;
use App\Services\ForensicAnalysisService;
use App\Services\SquadPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class PaymentController extends Controller
{
    public function initiate(
        Verification $verification,
        FeeSplit $feeSplit,
        SquadPaymentService $squadPaymentService
    ): View|RedirectResponse {
        abort_unless(auth()->id() === $verification->user_id, 403);

        if ($verification->payment?->status === Payment::STATUS_PAID) {
            return redirect()->route('verifications.show', $verification);
        }

        $payment = $verification->payment ?: $verification->payment()->create([
            ...$feeSplit->amounts(),
            'provider' => 'squad',
            'transaction_ref' => $this->makeTransactionRef(),
            'status' => Payment::STATUS_PENDING,
            'currency' => 'NGN',
        ]);

        $mockReason = null;
        $squadResult = null;

        try {
            $squadResult = $squadPaymentService->initiate($verification->load('institution'), $payment);
        } catch (Throwable $exception) {
            $mockReason = $exception->getMessage();
        }

        if ($squadResult) {
            $payment->update([
                'provider' => 'squad',
                'checkout_url' => $squadResult['checkout_url'],
                'raw_response' => $squadResult['raw_response'],
            ]);
        } else {
            $payment->update([
                'provider' => 'mock',
                'checkout_url' => route('payments.mock', $payment),
                'raw_response' => ['fallback_reason' => $mockReason ?: 'Squad credentials unavailable or checkout initiation failed.'],
            ]);
        }

        return view('payments.show', [
            'verification' => $verification->refresh()->load('institution'),
            'payment' => $payment->refresh(),
            'feeSplit' => $feeSplit,
            'squadPublicKey' => config('services.squad.public_key'),
        ]);
    }

    public function mock(Payment $payment, ForensicAnalysisService $forensicAnalysisService, SquadPaymentService $squadPaymentService): RedirectResponse
    {
        abort_unless(auth()->id() === $payment->verification->user_id, 403);

        $this->completePayment($payment, $forensicAnalysisService, $squadPaymentService, 'mock_checkout_success');

        return redirect()->route('verifications.show', $payment->verification)
            ->with('status', 'Mock payment completed and forensic scan finished.');
    }

    public function callback(Request $request, ForensicAnalysisService $forensicAnalysisService, SquadPaymentService $squadPaymentService): RedirectResponse
    {
        $payment = Payment::where('transaction_ref', $request->query('transaction_ref'))->firstOrFail();

        // Verify the transaction with Squad before trusting the callback
        if ($payment->provider === 'squad') {
            $verification = $squadPaymentService->verifyTransaction($payment->transaction_ref);
            if ($verification && strtolower((string) data_get($verification, 'transaction_status')) !== 'success') {
                return redirect()->route('verifications.show', $payment->verification)
                    ->with('status', 'Payment verification failed. Please try again.');
            }
        }

        $this->completePayment($payment, $forensicAnalysisService, $squadPaymentService, 'squad_callback');

        return redirect()->route('verifications.show', $payment->verification)
            ->with('status', 'Payment completed successfully. Forensic scan finished.');
    }

    private function completePayment(Payment $payment, ForensicAnalysisService $forensicAnalysisService, SquadPaymentService $squadPaymentService, string $source): void
    {
        if ($payment->status !== Payment::STATUS_PAID) {
            $payment->update([
                'status' => Payment::STATUS_PAID,
                'paid_at' => now(),
            ]);

            $royalty = RoyaltyLedgerEntry::firstOrCreate(
                ['payment_id' => $payment->id],
                [
                    'verification_id' => $payment->verification_id,
                    'institution_id' => $payment->verification->institution_id,
                    'amount_kobo' => $payment->royalty_amount_kobo,
                    'status' => 'recorded',
                    'squad_reference' => $payment->transaction_ref,
                    'metadata' => [
                        'source' => $source,
                        'split_strategy' => 'squad_transfer_api',
                    ],
                ]
            );

            // Attempt to initiate the royalty transfer via Squad Transfer API
            $squadPaymentService->initiateTransfer($royalty);
        }

        if (! $payment->verification->fresh()->hasCompletedScan()) {
            $forensicAnalysisService->scanAndPersist($payment->verification);
        }
    }

    private function makeTransactionRef(): string
    {
        do {
            $ref = 'TS-'.now()->format('YmdHis').'-'.Str::upper(Str::random(8));
        } while (Payment::where('transaction_ref', $ref)->exists());

        return $ref;
    }
}
