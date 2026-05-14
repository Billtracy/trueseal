<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Models\Verification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VerificationController extends Controller
{
    public function create(): View
    {
        return view('verifications.create', [
            'institutions' => Institution::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'institution_id' => ['required', 'exists:institutions,id'],
            'candidate_name' => ['required', 'string', 'max:120'],
            'candidate_email' => ['required', 'email', 'max:160'],
            'document' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $file = $request->file('document');
        $path = $file->store('verifications/originals');

        $verification = Verification::create([
            'user_id' => $request->user()->id,
            'institution_id' => $validated['institution_id'],
            'candidate_name' => $validated['candidate_name'],
            'candidate_email' => $validated['candidate_email'],
            'document_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'status' => Verification::STATUS_PENDING_PAYMENT,
        ]);

        return redirect()->route('payments.initiate', $verification);
    }

    public function show(Verification $verification): View
    {
        $this->authorizeOwner($verification);

        $verification->load(['institution', 'payment', 'royaltyLedgerEntry']);

        return view('verifications.show', [
            'verification' => $verification,
        ]);
    }

    public function asset(Verification $verification, string $type): BinaryFileResponse
    {
        $this->authorizeOwner($verification);

        $path = match ($type) {
            'original' => $verification->document_path,
            'heatmap' => $verification->heatmap_path,
            default => abort(404),
        };

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path));
    }

    private function authorizeOwner(Verification $verification): void
    {
        abort_unless(auth()->id() === $verification->user_id, 403);
    }
}
