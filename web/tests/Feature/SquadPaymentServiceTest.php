<?php

use App\Models\Institution;
use App\Models\Payment;
use App\Models\User;
use App\Models\Verification;
use App\Services\FeeSplit;
use App\Services\SquadPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds a Squad initiate payload with local royalty metadata', function () {
    $user = User::factory()->create();
    $institution = Institution::create([
        'name' => 'University of Ibadan',
        'code' => 'UI',
        'squad_subaccount_id' => 'SQD_SUB_UI_DEMO',
    ]);

    $verification = Verification::create([
        'user_id' => $user->id,
        'institution_id' => $institution->id,
        'candidate_name' => 'Tunde Adeyemi',
        'candidate_email' => 'tunde@example.com',
        'document_path' => 'verifications/originals/tunde.jpg',
        'original_filename' => 'tunde-degree.jpg',
    ]);

    $payment = Payment::create([
        'verification_id' => $verification->id,
        'transaction_ref' => 'TS-TEST-123',
        'amount_kobo' => FeeSplit::VERIFICATION_FEE_KOBO,
        'platform_amount_kobo' => FeeSplit::PLATFORM_AMOUNT_KOBO,
        'royalty_amount_kobo' => FeeSplit::ROYALTY_AMOUNT_KOBO,
    ]);

    $payload = app(SquadPaymentService::class)->buildInitiatePayload($verification->load('institution'), $payment);

    expect($payload['amount'])->toBe(500000)
        ->and($payload['currency'])->toBe('NGN')
        ->and($payload['transaction_ref'])->toBe('TS-TEST-123')
        ->and($payload['metadata']['verification_id'])->toBe($verification->id)
        ->and($payload['metadata']['platform_amount_kobo'])->toBe(400000)
        ->and($payload['metadata']['royalty_amount_kobo'])->toBe(100000)
        ->and($payload['metadata']['split_strategy'])->toBe('local_royalty_ledger');
});
