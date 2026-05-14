<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Verification;
use App\Services\FeeSplit;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(FeeSplit $feeSplit): View
    {
        $verifications = Verification::query()
            ->with(['institution', 'payment', 'royaltyLedgerEntry'])
            ->latest()
            ->paginate(10);

        return view('dashboard', [
            'verifications' => $verifications,
            'feeSplit' => $feeSplit,
            'totalVerifications' => Verification::count(),
            'failedVerifications' => Verification::where('status', Verification::STATUS_FAIL)->count(),
            'paidPayments' => Payment::where('status', Payment::STATUS_PAID)->count(),
            'royaltyTotal' => Payment::where('status', Payment::STATUS_PAID)->sum('royalty_amount_kobo'),
        ]);
    }
}
