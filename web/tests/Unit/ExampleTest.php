<?php

use App\Services\FeeSplit;

it('calculates the TrustStack fee split', function () {
    $split = new FeeSplit();

    expect($split->amounts())->toBe([
        'amount_kobo' => 500000,
        'platform_amount_kobo' => 400000,
        'royalty_amount_kobo' => 100000,
    ])->and($split->formatNaira(100000))->toBe('NGN 1,000');
});
