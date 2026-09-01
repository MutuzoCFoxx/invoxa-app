<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Helvetica', sans-serif; color: #0b0d10; padding: 36px; font-size: 11px; line-height: 1.5; }
    .header { display: table; width: 100%; margin-bottom: 28px; padding-bottom: 20px; border-bottom: 3px solid {{ $invoice->workspace->brand_color ?? '#0b0d10' }}; }
    .header-left, .header-right { display: table-cell; vertical-align: top; }
    .header-right { text-align: right; }
    .logo { max-height: 56px; max-width: 180px; margin-bottom: 8px; }
    .company-name { font-size: 20px; font-weight: bold; margin-bottom: 4px; }
    .info { color: #6b7280; font-size: 10px; line-height: 1.6; }
    .doc-label { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 4px; }
    .doc-number { font-size: 24px; font-weight: bold; font-family: monospace; color: {{ $invoice->workspace->brand_color ?? '#0b0d10' }}; }
    .status { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 9px; text-transform: uppercase; background: #e5e7eb; color: #374151; margin-top: 6px; }
    .status-paid { background: #d1fae5; color: #065f46; }
    .status-sent { background: #dbeafe; color: #1e40af; }
    .status-overdue { background: #fee2e2; color: #991b1b; }
    .status-partially_paid { background: #fef3c7; color: #92400e; }
    .billing { display: table; width: 100%; margin-bottom: 28px; }
    .billing-left, .billing-right { display: table-cell; vertical-align: top; width: 50%; }
    .billing-right { text-align: right; }
    .section-label { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; }
    .customer-name { font-size: 14px; font-weight: bold; margin-bottom: 4px; }
    .date-row { margin-bottom: 10px; }
    .date-label { font-size: 9px; color: #6b7280; text-transform: uppercase; }
    .date-value { font-weight: 600; font-size: 12px; }
    table.items { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    table.items thead tr { border-bottom: 2px solid {{ $invoice->workspace->brand_color ?? '#0b0d10' }}; }
    table.items th { text-align: left; padding: 8px 6px; font-size: 9px; text-transform: uppercase; color: #6b7280; letter-spacing: 1px; }
    table.items td { padding: 9px 6px; border-bottom: 1px solid #f0f0f0; font-size: 11px; }
    table.items .right { text-align: right; }
    .totals-wrap { text-align: right; margin-bottom: 20px; }
    .totals { display: inline-block; width: 260px; }
    .totals-row { display: table; width: 100%; padding: 4px 0; }
    .totals-label { display: table-cell; color: #6b7280; text-align: left; }
    .totals-value { display: table-cell; text-align: right; font-weight: 500; }
    .totals-discount { color: #059669; }
    .totals-total { padding-top: 10px; margin-top: 8px; border-top: 2px solid {{ $invoice->workspace->brand_color ?? '#0b0d10' }}; font-size: 17px; font-weight: bold; }
    .totals-balance { padding-top: 6px; margin-top: 6px; border-top: 1px solid #e5e7eb; color: #dc2626; font-size: 13px; font-weight: bold; }
    .clearfix { clear: both; }
    .bank-box { margin-top: 20px; padding: 14px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb; }
    .notes { padding-top: 18px; border-top: 1px solid #e5e7eb; margin-top: 20px; }
    .footer { margin-top: 36px; padding-top: 16px; border-top: 1px solid #e5e7eb; text-align: center; font-size: 9px; color: #9ca3af; }
</style>
</head>
<body>

    {{-- HEADER --}}
    <div class="header">
        <div class="header-left">
            @if($invoice->workspace->logo_url)
                @php
                    $logoSrc = $invoice->workspace->logo_url;
                    // Logo is stored as a base64 data URI — use directly
                    // Fall back to HTTP fetch for legacy URL values
                    if (!str_starts_with($logoSrc, 'data:')) {
                        try {
                            $ctx = stream_context_create(['http' => ['timeout' => 3]]);
                            $raw = @file_get_contents($logoSrc, false, $ctx);
                            $logoSrc = $raw ? 'data:image/png;base64,' . base64_encode($raw) : null;
                        } catch(\Exception $e) { $logoSrc = null; }
                    }
                @endphp
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" class="logo" alt="logo">
                @endif
            @endif
            <div class="company-name">{{ $invoice->workspace->name ?? 'Company' }}</div>
            @if($invoice->workspace->company_address)
                <div class="info">{{ $invoice->workspace->company_address }}</div>
            @endif
            @if($invoice->workspace->company_email)
                <div class="info">{{ $invoice->workspace->company_email }}</div>
            @endif
            @if($invoice->workspace->company_phone)
                <div class="info">{{ $invoice->workspace->company_phone }}</div>
            @endif
            @if($invoice->workspace->tax_id)
                <div class="info">TIN: {{ $invoice->workspace->tax_id }}</div>
            @endif
        </div>
        <div class="header-right">
            <div class="doc-label">Invoice</div>
            <div class="doc-number">{{ $invoice->invoice_number }}</div>
            <span class="status status-{{ $invoice->status }}">{{ str_replace('_', ' ', $invoice->status) }}</span>
        </div>
    </div>

    {{-- BILL TO / DATES --}}
    <div class="billing">
        <div class="billing-left">
            <div class="section-label">Bill To</div>
            <div class="customer-name">{{ $invoice->customer->name }}</div>
            @if($invoice->customer->company_name)
                <div class="info">{{ $invoice->customer->company_name }}</div>
            @endif
            <div class="info">{{ $invoice->customer->email }}</div>
            @if($invoice->customer->phone)
                <div class="info">{{ $invoice->customer->phone }}</div>
            @endif
            @if($invoice->customer->billing_address)
                <div class="info">{{ $invoice->customer->billing_address }}</div>
            @endif
            @if($invoice->customer->tin)
                <div class="info">TIN: {{ $invoice->customer->tin }}</div>
            @endif
        </div>
        <div class="billing-right">
            <div class="date-row">
                <div class="date-label">Issue Date</div>
                <div class="date-value">{{ \Carbon\Carbon::parse($invoice->issue_date)->format('M d, Y') }}</div>
            </div>
            <div class="date-row">
                <div class="date-label">Due Date</div>
                <div class="date-value">{{ \Carbon\Carbon::parse($invoice->due_date)->format('M d, Y') }}</div>
            </div>
            <div class="date-row">
                <div class="date-label">Currency</div>
                <div class="date-value">{{ $invoice->currency }}</div>
            </div>
        </div>
    </div>

    {{-- LINE ITEMS --}}
    @php $hasTax = $invoice->items->contains(fn($i) => floatval($i->tax_rate) > 0); @endphp
    <table class="items">
        <thead>
            <tr>
                <th>Description</th>
                <th class="right" style="width:50px">Qty</th>
                <th class="right" style="width:90px">Unit Price</th>
                @if($hasTax)
                    <th class="right" style="width:60px">{{ $invoice->workspace->tax_label ?? 'Tax' }}</th>
                @endif
                <th class="right" style="width:90px">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td class="right">{{ number_format(floatval($item->quantity), 2) }}</td>
                <td class="right">{{ $invoice->currency }} {{ number_format(floatval($item->unit_price), 2) }}</td>
                @if($hasTax)
                    <td class="right">{{ floatval($item->tax_rate) > 0 ? number_format(floatval($item->tax_rate), 1).'%' : '—' }}</td>
                @endif
                <td class="right">{{ $invoice->currency }} {{ number_format(floatval($item->line_total), 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- TOTALS --}}
    @php
        $taxLabel    = $invoice->workspace->tax_label ?? 'Tax';
        $taxRate     = floatval($invoice->workspace->tax_rate ?? 0);
        $taxInclusive = $invoice->workspace->tax_inclusive;
        $hasDiscount  = $invoice->discount_type && $invoice->discount_type !== 'none' && floatval($invoice->discount_value) > 0;
        $discountLabel = $invoice->discount_type === 'percentage'
            ? 'Discount (' . number_format(floatval($invoice->discount_amount), 0) . '%)'
            : 'Discount';
        $amountPaid  = floatval($invoice->amount_paid ?? 0);
        $balanceDue  = max(0, floatval($invoice->total_amount) - $amountPaid);
    @endphp

    <div class="totals-wrap">
        <div class="totals">
            <div class="totals-row">
                <span class="totals-label">Subtotal</span>
                <span class="totals-value">{{ $invoice->currency }} {{ number_format(floatval($invoice->subtotal), 2) }}</span>
            </div>
            @if($hasDiscount)
            <div class="totals-row totals-discount">
                <span class="totals-label">{{ $discountLabel }}</span>
                <span class="totals-value">− {{ $invoice->currency }} {{ number_format(floatval($invoice->discount_value), 2) }}</span>
            </div>
            @endif
            @if(floatval($invoice->tax_amount) > 0)
            <div class="totals-row">
                <span class="totals-label">{{ $taxLabel }}{{ $taxRate > 0 ? ' ('.$taxRate.'%)' : '' }}{{ $taxInclusive ? ' (incl.)' : '' }}</span>
                <span class="totals-value">{{ $taxInclusive ? 'included' : $invoice->currency.' '.number_format(floatval($invoice->tax_amount), 2) }}</span>
            </div>
            @endif
            <div class="totals-row totals-total">
                <span class="totals-label">Total</span>
                <span class="totals-value">{{ $invoice->currency }} {{ number_format(floatval($invoice->total_amount), 2) }}</span>
            </div>
            @if($amountPaid > 0)
            <div class="totals-row" style="color:#059669; padding-top:6px;">
                <span class="totals-label">Amount Paid</span>
                <span class="totals-value">− {{ $invoice->currency }} {{ number_format($amountPaid, 2) }}</span>
            </div>
            <div class="totals-row totals-balance">
                <span class="totals-label">Balance Due</span>
                <span class="totals-value">{{ $invoice->currency }} {{ number_format($balanceDue, 2) }}</span>
            </div>
            @endif
        </div>
    </div>
    <div class="clearfix"></div>

    {{-- BANK DETAILS --}}
    @if($invoice->workspace->bank_name || $invoice->workspace->bank_account_number)
    <div class="bank-box">
        <div class="section-label">Payment Details</div>
        <table style="width:100%; margin-top:8px; font-size:11px;">
            @if($invoice->workspace->bank_name)
            <tr>
                <td style="color:#6b7280; width:120px; padding:2px 0;">Bank:</td>
                <td style="font-weight:600;">{{ $invoice->workspace->bank_name }}</td>
            </tr>
            @endif
            @if($invoice->workspace->bank_account_number)
            <tr>
                <td style="color:#6b7280; padding:2px 0;">Account:</td>
                <td style="font-weight:600; font-family:monospace;">{{ $invoice->workspace->bank_account_number }}</td>
            </tr>
            @endif
            @if($invoice->workspace->bank_account_name)
            <tr>
                <td style="color:#6b7280; padding:2px 0;">Account Name:</td>
                <td style="font-weight:600;">{{ $invoice->workspace->bank_account_name }}</td>
            </tr>
            @endif
        </table>
    </div>
    @endif

    {{-- NOTES --}}
    @if($invoice->notes)
    <div class="notes">
        <div class="section-label">Notes</div>
        <div class="info" style="margin-top:4px;">{{ $invoice->notes }}</div>
    </div>
    @endif

    {{-- FOOTER --}}
    <div class="footer">
        {{ $invoice->workspace->invoice_footer ?: 'Thank you for your business!' }}<br>
        Generated by Invoxa &middot; Powered by iremeCloud
    </div>

</body>
</html>
