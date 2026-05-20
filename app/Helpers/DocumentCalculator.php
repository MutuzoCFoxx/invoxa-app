<?php

namespace App\Helpers;

class DocumentCalculator
{
    /**
     * Compute subtotal, discount_value, tax_amount, and total_amount
     * from a list of items and document-level discount/tax settings.
     *
     * @param  array  $items          [['quantity', 'unit_price', 'tax_rate'], ...]
     * @param  string $discountType   'none' | 'percentage' | 'fixed'
     * @param  float  $discountAmount percentage value or fixed amount
     * @param  string $taxType        'on_total' | 'per_item'
     * @param  float  $taxRate        document-level rate (used when taxType='on_total')
     * @param  bool   $taxInclusive
     * @return array  ['subtotal', 'discount_value', 'tax_amount', 'total_amount']
     */
    public static function compute(
        array  $items,
        string $discountType   = 'none',
        float  $discountAmount = 0,
        string $taxType        = 'per_item',
        float  $taxRate        = 0,
        bool   $taxInclusive   = false
    ): array {
        $subtotal = 0;
        $perItemTax = 0;

        foreach ($items as $item) {
            $lineSubtotal = (float)$item['quantity'] * (float)$item['unit_price'];
            $subtotal += $lineSubtotal;
            if ($taxType === 'per_item') {
                $perItemTax += $lineSubtotal * ((float)($item['tax_rate'] ?? 0) / 100);
            }
        }

        // Discount
        $discountValue = match ($discountType) {
            'percentage' => $subtotal * ($discountAmount / 100),
            'fixed'      => min($discountAmount, $subtotal),
            default      => 0.0,
        };

        $afterDiscount = $subtotal - $discountValue;

        // Tax
        if ($taxInclusive) {
            $taxAmount = 0; // tax already included in prices
        } elseif ($taxType === 'on_total') {
            $taxAmount = $afterDiscount * ($taxRate / 100);
        } else {
            // per_item: scale proportionally to the discount
            $scale = $subtotal > 0 ? $afterDiscount / $subtotal : 1;
            $taxAmount = $perItemTax * $scale;
        }

        $totalAmount = $afterDiscount + $taxAmount;

        return [
            'subtotal'       => round($subtotal, 2),
            'discount_value' => round($discountValue, 2),
            'tax_amount'     => round($taxAmount, 2),
            'total_amount'   => round($totalAmount, 2),
        ];
    }
}
