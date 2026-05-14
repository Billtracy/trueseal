<?php

use App\Models\Institution;
use App\Models\Payment;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

it('protects the dashboard behind demo auth', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('logs in the HR manager', function () {
    User::factory()->create([
        'email' => 'hr@trueseal.test',
        'password' => 'password',
    ]);

    $this->post('/login', [
        'email' => 'hr@trueseal.test',
        'password' => 'password',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticated();
});

it('creates a verification and waits for payment before scanning', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $institution = Institution::create([
        'name' => 'University of Lagos',
        'code' => 'UNILAG',
        'squad_subaccount_id' => 'SQD_SUB_UNILAG_DEMO',
    ]);

    $this->actingAs($user)
        ->post('/verifications', [
            'institution_id' => $institution->id,
            'candidate_name' => 'Ada Lovelace',
            'candidate_email' => 'ada@example.com',
            'document' => UploadedFile::fake()->image('ada-degree-2024.jpg', 900, 640),
        ])
        ->assertRedirect();

    $verification = Verification::firstOrFail();

    expect($verification->status)->toBe(Verification::STATUS_PENDING_PAYMENT)
        ->and($verification->heatmap_path)->toBeNull();
});

it('completes mock payment, records royalty, and runs the scan', function () {
    Storage::fake('local');
    config(['services.squad.secret_key' => null]);

    $user = User::factory()->create();
    $institution = Institution::create([
        'name' => 'University of Lagos',
        'code' => 'UNILAG',
        'squad_subaccount_id' => 'SQD_SUB_UNILAG_DEMO',
    ]);

    $this->actingAs($user)
        ->post('/verifications', [
            'institution_id' => $institution->id,
            'candidate_name' => 'Ada Lovelace',
            'candidate_email' => 'ada@example.com',
            'document' => UploadedFile::fake()->image('edited-degree-2024.jpg', 900, 640),
        ])
        ->assertRedirect();

    $verification = Verification::firstOrFail();

    $this->get(route('payments.initiate', $verification))->assertOk();

    $payment = Payment::firstOrFail();
    expect($payment->status)->toBe(Payment::STATUS_PENDING)
        ->and($payment->provider)->toBe('mock');

    $this->get(route('payments.mock', $payment))->assertRedirect(route('verifications.show', $verification));

    $verification->refresh();
    $payment->refresh();

    expect($payment->status)->toBe(Payment::STATUS_PAID)
        ->and($payment->royalty_amount_kobo)->toBe(100000)
        ->and($verification->status)->toBeIn([Verification::STATUS_PASS, Verification::STATUS_FAIL])
        ->and($verification->heatmap_path)->not->toBeNull();

    $this->assertDatabaseHas('royalty_ledger_entries', [
        'payment_id' => $payment->id,
        'institution_id' => $institution->id,
        'amount_kobo' => 100000,
        'status' => 'recorded',
    ]);

    Storage::disk('local')->assertExists($verification->heatmap_path);
});
