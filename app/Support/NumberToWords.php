<?php

namespace App\Support;

/**
 * Converts a decimal amount into words for the "Amount Chargeable (in
 * words)" line on Proforma/Tax Invoice PDFs, e.g. 3675.00 -> "Three
 * Thousand Six Hundred Seventy Five".
 */
class NumberToWords
{
    private const ONES = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
        'Seventeen', 'Eighteen', 'Nineteen',
    ];

    private const TENS = [
        '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety',
    ];

    public static function convert(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        $words = [];

        foreach ([
            1_000_000_000 => 'Billion',
            1_000_000 => 'Million',
            1_000 => 'Thousand',
            100 => 'Hundred',
        ] as $value => $label) {
            if ($number >= $value) {
                $words[] = self::convert(intdiv($number, $value)).' '.$label;
                $number %= $value;
            }
        }

        if ($number > 0) {
            if ($number < 20) {
                $words[] = self::ONES[$number];
            } else {
                $tens = self::TENS[intdiv($number, 10)];
                $ones = self::ONES[$number % 10];
                $words[] = trim($tens.' '.$ones);
            }
        }

        return trim(implode(' ', $words));
    }

    /**
     * Full "Amount Chargeable (in words)" line, e.g.
     * amountInWords(3675.00, 'Omani Rial', 'Baisa') ->
     * "Omani Rial Three Thousand Six Hundred Seventy Five Only".
     */
    public static function amountInWords(float $amount, string $currencyName, ?string $subunitName = null): string
    {
        $whole = (int) floor($amount);
        $fraction = (int) round(($amount - $whole) * 100);

        $words = trim($currencyName.' '.self::convert($whole));

        if ($fraction > 0 && $subunitName) {
            $words .= ' and '.self::convert($fraction).' '.$subunitName;
        }

        return $words.' Only';
    }
}
