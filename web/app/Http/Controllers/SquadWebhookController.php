<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\ForensicAnalysisService;
use App\Services\SquadPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SquadWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        SquadPaymentService $squadPaymentService,
        ForensicAnalysisService $forensicAnalysisService
    ): JsonResponse {
        $rawPayload = $request->getContent();
        $signature = $request->header('x-squad-encrypted-body', '');

        if (! $signature || ! $squadPaymentService->validateWebhookSignature($rawPayload, $signature)) {
            Log::warning('Squad webhook: invalid signature');

            return response()->json(['status' => 'invalid_signature'], 400);
        }

        $event = $request->input('Event');
        $transactionRef = $request->input('Body.transaction_ref');
        $transactionStatus = $request->input('Body.transaction_status');

        Log::info('Squad webhook received', [
            'event' => $event,
            'ref' => $transactionRef,
            'status' => $transactionStatus,
        ]);

        if ($event !== 'charge_successful' || strtolower((string) $transactionStatus) !== 'success') {
            return response()->json(['status' => 'ignored']);
        }

        $payment = Payment::where('transaction_ref', $transactionRef)->first();

        if (! $payment) {
            Log::warning('Squad webhook: unknown transaction_ref', ['ref' => $transactionRef]);

            return response()->json(['status' => 'unknown_ref'], 404);
        }

        if ($payment->status === Payment::STATUS_PAID) {
            return response()->json(['status' => 'already_processed']);
        }

        // Mark as paid
        $payment->update([
            'status' => Payment::STATUS_PAID,
            'paid_at' => now(),
        ]);

        // Create royalty ledger entry
        $royalty = \App\Models\RoyaltyLedgerEntry::firstOrCreate(
            ['payment_id' => $payment->id],
            [
                'verification_id' => $payment->verification_id,
                'institution_id' => $payment->verification->institution_id,
                'amount_kobo' => $payment->royalty_amount_kobo,
                'status' => 'recorded',
                'squad_reference' => $transactionRef,
                'metadata' => [
                    'source' => 'squad_webhook',
                    'event' => $event,
                    'gateway_ref' => $request->input('Body.gateway_ref'),
                ],
            ]
        );

        // Trigger the forensic scan
        $verification = $payment->verification;
        if (! $verification->hasCompletedScan()) {
            $forensicAnalysisService->scanAndPersist($verification);
        }

        // Initiate royalty transfer to the university
        $squadPaymentService->initiateTransfer($royalty->refresh());

        return response()->json(['status' => 'processed']);
    }
}
