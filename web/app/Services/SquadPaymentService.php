<?php

namespace App\Services;

use App\Models\Institution;
use App\Models\Payment;
use App\Models\RoyaltyLedgerEntry;
use App\Models\Verification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SquadPaymentService
{
    private function baseUrl(): string
    {
        return rtrim(config('services.squad.base_url'), '/');
    }

    private function secretKey(): string
    {
        return config('services.squad.secret_key') ?? '';
    }

    private function merchantId(): string
    {
        return config('services.squad.merchant_id') ?? '';
    }

    public function hasCredentials(): bool
    {
        return filled($this->secretKey());
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($this->secretKey())
            ->acceptJson()
            ->asJson()
            ->timeout(15);
    }

    // ─── PAYMENT INITIATION ─────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function buildInitiatePayload(Verification $verification, Payment $payment): array
    {
        return [
            'amount' => $payment->amount_kobo,
            'email' => $verification->candidate_email,
            'currency' => $payment->currency ?: 'NGN',
            'initiate_type' => 'inline',
            'transaction_ref' => $payment->transaction_ref,
            'customer_name' => $verification->candidate_name,
            'callback_url' => route('payments.callback', ['transaction_ref' => $payment->transaction_ref]),
            'metadata' => [
                'verification_id' => $verification->id,
                'institution_id' => $verification->institution_id,
                'institution_name' => $verification->institution->name,
                'platform_amount_kobo' => $payment->platform_amount_kobo,
                'royalty_amount_kobo' => $payment->royalty_amount_kobo,
                'royalty_destination' => $verification->institution->account_number,
            ],
        ];
    }

    /**
     * @return array{checkout_url:string, raw_response:array<string, mixed>}|null
     */
    public function initiate(Verification $verification, Payment $payment): ?array
    {
        if (! $this->hasCredentials()) {
            return null;
        }

        $response = $this->client()
            ->post($this->baseUrl().'/transaction/initiate', $this->buildInitiatePayload($verification, $payment));

        if (! $response->successful()) {
            Log::warning('Squad initiate failed', ['status' => $response->status(), 'body' => $response->body()]);

            return null;
        }

        $payload = $response->json();
        $checkoutUrl = data_get($payload, 'data.checkout_url');

        if (! is_string($checkoutUrl) || $checkoutUrl === '') {
            return null;
        }

        return [
            'checkout_url' => $checkoutUrl,
            'raw_response' => $payload,
        ];
    }

    // ─── TRANSACTION VERIFICATION ───────────────────────────────────────

    /**
     * @return array<string, mixed>|null
     */
    public function verifyTransaction(string $transactionRef): ?array
    {
        if (! $this->hasCredentials()) {
            return null;
        }

        $response = $this->client()
            ->get($this->baseUrl().'/transaction/verify/'.$transactionRef);

        if (! $response->successful()) {
            Log::warning('Squad verify failed', ['ref' => $transactionRef, 'status' => $response->status()]);

            return null;
        }

        return $response->json('data');
    }

    // ─── WEBHOOK SIGNATURE VALIDATION ───────────────────────────────────

    public function validateWebhookSignature(string $payload, string $signature): bool
    {
        $computed = strtoupper(hash_hmac('sha512', $payload, $this->secretKey()));

        return hash_equals($computed, strtoupper($signature));
    }

    // ─── ACCOUNT LOOKUP ─────────────────────────────────────────────────

    /**
     * @return array{account_name:string, account_number:string}|null
     */
    public function accountLookup(string $bankCode, string $accountNumber): ?array
    {
        if (! $this->hasCredentials()) {
            return null;
        }

        $response = $this->client()
            ->post($this->baseUrl().'/payout/account_lookup', [
                'bank_code' => $bankCode,
                'account_number' => $accountNumber,
            ]);

        if (! $response->successful()) {
            Log::warning('Squad account lookup failed', ['bank_code' => $bankCode, 'account' => $accountNumber]);

            return null;
        }

        return $response->json('data');
    }

    // ─── FUND TRANSFER (ROYALTY ROUTING) ────────────────────────────────

    /**
     * Initiate a royalty transfer to the issuing university's bank account.
     *
     * @return array<string, mixed>|null
     */
    public function initiateTransfer(RoyaltyLedgerEntry $entry): ?array
    {
        if (! $this->hasCredentials()) {
            $entry->update([
                'transfer_reference' => $this->merchantId().'_ROYALTY_'.$entry->id.'_'.Str::upper(Str::random(6)),
                'transfer_status' => 'queued',
                'transfer_response' => ['note' => 'Squad credentials unavailable — transfer queued for processing.'],
            ]);

            return null;
        }

        $institution = $entry->institution;

        if (! $institution->bank_code || ! $institution->account_number || ! $institution->account_name) {
            Log::warning('Institution missing bank details for transfer', ['institution_id' => $institution->id]);

            $entry->update([
                'transfer_status' => 'queued',
                'transfer_response' => ['note' => 'Institution bank details incomplete — transfer queued.'],
            ]);

            return null;
        }

        // Transaction reference must be prefixed with merchant ID per Squad docs
        $transferRef = $this->merchantId().'_ROYALTY_'.$entry->id.'_'.Str::upper(Str::random(6));

        $response = $this->client()
            ->post($this->baseUrl().'/payout/transfer', [
                'transaction_reference' => $transferRef,
                'amount' => (string) $entry->amount_kobo,
                'bank_code' => $institution->bank_code,
                'account_number' => $institution->account_number,
                'account_name' => $institution->account_name,
                'currency_id' => 'NGN',
                'remark' => 'TrueSeal royalty: verification #'.$entry->verification_id.' to '.$institution->name,
            ]);

        $result = $response->json();
        $message = data_get($result, 'message', '');

        // Handle "not profiled" as a soft failure — transfer is valid but
        // the sandbox/production merchant needs Transfer API activation.
        $isProfilingIssue = str_contains(strtolower($message), 'not profiled');

        $entry->update([
            'transfer_reference' => $transferRef,
            'transfer_status' => $response->successful() ? 'initiated' : ($isProfilingIssue ? 'queued' : 'failed'),
            'transfer_response' => $result,
        ]);

        if (! $response->successful()) {
            Log::warning('Squad transfer failed', [
                'ref' => $transferRef,
                'body' => $result,
                'is_profiling_issue' => $isProfilingIssue,
            ]);

            return null;
        }

        $entry->update(['transfer_status' => 'success', 'status' => 'transferred']);

        return $result;
    }

    // ─── SUB-MERCHANT REGISTRATION ──────────────────────────────────────

    /**
     * Register an institution as a sub-merchant (aggregator model).
     *
     * @return string|null The account_id returned by Squad
     */
    public function createSubMerchant(Institution $institution): ?string
    {
        if (! $this->hasCredentials()) {
            return null;
        }

        if (! $institution->bank_code || ! $institution->account_number || ! $institution->account_name) {
            Log::warning('Institution missing bank details for sub-merchant', ['institution_id' => $institution->id]);

            return null;
        }

        $response = $this->client()
            ->post($this->baseUrl().'/merchant/create-sub-users', [
                'display_name' => $institution->name,
                'account_name' => $institution->account_name,
                'account_number' => $institution->account_number,
                'bank_code' => $institution->bank_code,
                'bank' => $institution->bank_name,
            ]);

        if (! $response->successful()) {
            Log::warning('Squad sub-merchant creation failed', [
                'institution' => $institution->code,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $accountId = $response->json('data.account_id');

        if ($accountId) {
            $institution->update(['squad_subaccount_id' => $accountId]);
        }

        return $accountId;
    }
}
