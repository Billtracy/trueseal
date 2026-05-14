<?php

namespace App\Services;

class FeeSplit
{
    public const VERIFICATION_FEE_KOBO = 500000;
    public const PLATFORM_AMOUNT_KOBO = 400000;
    public const ROYALTY_AMOUNT_KOBO = 100000;

    /**
     * @return array{amount_kobo:int, platform_amount_kobo:int, royalty_amount_kobo:int}
     */
    public function amounts(): array
    {
        return [
            'amount_kobo' => self::VERIFICATION_FEE_KOBO,
            'platform_amount_kobo' => self::PLATFORM_AMOUNT_KOBO,
            'royalty_amount_kobo' => self::ROYALTY_AMOUNT_KOBO,
        ];
    }

    public function formatNaira(int $kobo): string
    {
        return 'NGN '.number_format($kobo / 100, 0);
    }
}
