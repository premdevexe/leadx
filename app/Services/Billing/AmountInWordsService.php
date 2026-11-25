<?php

namespace App\Services\Billing;

class AmountInWordsService
{
    private const ONES = [
        0 => 'Zero', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four',
        5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
        10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen',
        15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen',
    ];

    private const TENS = [
        2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
        6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety',
    ];

    private const CURRENCY_UNITS = [
        'INR' => ['Rupees', 'Paise'],
        'USD' => ['Dollars', 'Cents'],
        'EUR' => ['Euros', 'Cents'],
        'GBP' => ['Pounds', 'Pence'],
        'AED' => ['Dirhams', 'Fils'],
    ];

    public static function convert(float $amount, string $currencyCode = 'USD'): string
    {
        $currencyCode = strtoupper($currencyCode ?: 'USD');
        [$majorUnit, $minorUnit] = self::CURRENCY_UNITS[$currencyCode] ?? [$currencyCode, 'Cents'];

        $amount = round(abs($amount), 2);
        $whole = (int) floor($amount);
        $fraction = (int) round(($amount - $whole) * 100);

        $words = self::numberToWords($whole).' '.$majorUnit;
        if ($fraction > 0) {
            $words .= ' and '.self::numberToWords($fraction).' '.$minorUnit;
        }

        return $words.' Only';
    }

    private static function numberToWords(int $number): string
    {
        if ($number < 20) {
            return self::ONES[$number];
        }

        if ($number < 100) {
            $ten = intdiv($number, 10);
            $rem = $number % 10;

            return self::TENS[$ten].($rem > 0 ? ' '.self::ONES[$rem] : '');
        }

        if ($number < 1000) {
            $hundred = intdiv($number, 100);
            $rem = $number % 100;

            return self::ONES[$hundred].' Hundred'.($rem > 0 ? ' '.self::numberToWords($rem) : '');
        }

        $scales = [
            1000000000 => 'Billion',
            1000000 => 'Million',
            1000 => 'Thousand',
        ];

        foreach ($scales as $divisor => $label) {
            if ($number >= $divisor) {
                $chunk = intdiv($number, $divisor);
                $rem = $number % $divisor;

                return self::numberToWords($chunk).' '.$label.($rem > 0 ? ' '.self::numberToWords($rem) : '');
            }
        }

        return (string) $number;
    }
}
