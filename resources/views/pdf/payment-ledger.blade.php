<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Ledger - {{ $customer->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1e293b; margin: 0; }
        .invoice-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #0f766e; padding-bottom: 14px; margin-bottom: 18px; }
        .company-title { font-size: 20px; font-weight: bold; color: #0f766e; margin-bottom: 4px; }
        .company-info { font-size: 11px; color: #475569; line-height: 1.5; }
        .doc-title { font-size: 26px; font-weight: bold; color: #0f766e; text-align: right; }
        .doc-meta { text-align: right; font-size: 11px; color: #475569; margin-top: 6px; }
        .section-title { font-size: 13px; font-weight: bold; color: #0f766e; margin: 16px 0 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        .bill-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .bill-table th { background: #0f766e; color: #ffffff; text-align: left; padding: 8px 10px; font-size: 11px; }
        .bill-table td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; font-size: 12px; }
        .bill-table tr:nth-child(even) td { background: #f8fafc; }
        .totals { width: 40%; margin-left: auto; }
        .totals td { padding: 6px 10px; font-size: 12px; }
        .totals .grand { font-weight: bold; font-size: 14px; color: #0f766e; border-top: 2px solid #0f766e; }
        .footer-note { margin-top: 24px; padding-top: 12px; border-top: 1px solid #e2e8f0; font-size: 11px; color: #64748b; }
    </style>
</head>
<body>
    <div class="invoice-header">
        {{-- Company details render only when company_name_invoice is enabled --}}
        @if ($show_company ?? true)
        <div>
            <div class="company-title">{{ optional($setup)->company_name ?? optional($company)->title ?? 'Internet Service Provider' }}</div>
            <div class="company-info">
                @if (!empty(optional($company)->address)){{ $company->address }}<br>@endif
                @if (!empty(optional($setup)->mobile ?? optional($company)->mobile))Mobile: {{ optional($setup)->mobile ?? optional($company)->mobile }}<br>@endif
                @if (!empty(optional($setup)->email ?? optional($company)->email))Email: {{ optional($setup)->email ?? optional($company)->email }}@endif
            </div>
        </div>
        @endif
        <div>
            <div class="doc-title">PAYMENT LEDGER</div>
            <div class="doc-meta">
                Customer: <strong>{{ $customer->name ?? $customer->username }}</strong><br>
                PPPoE Username: {{ $customer->username }}<br>
                Generated: {{ now()->format('d F Y') }}
            </div>
        </div>
    </div>

    <div class="section-title">All Approved Payments</div>
    <table class="bill-table">
        <thead>
            <tr>
                <th>Payment ID</th>
                <th>Date</th>
                <th>Method</th>
                <th>Trx No</th>
                <th style="text-align: right;">Received (BDT)</th>
                <th style="text-align: right;">Discount (BDT)</th>
                <th style="text-align: right;">Total (BDT)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payments as $payment)
                <tr>
                    <td>{{ $payment->payment_id ?? 'PAY-' . $payment->id }}</td>
                    <td>{{ $payment->recieved_date }}</td>
                    <td>{{ $payment->payment_method ?? $payment->payment_info ?? '—' }}</td>
                    <td>{{ $payment->transaction_no ?? '—' }}</td>
                    <td style="text-align: right;">{{ number_format((float) $payment->received_amount, 2) }}</td>
                    <td style="text-align: right;">{{ number_format((float) $payment->discount, 2) }}</td>
                    <td style="text-align: right;">{{ number_format((float) $payment->total_amount, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; color: #64748b;">No approved payments found for this account.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="bill-table totals">
        <tr class="grand">
            <td>Total Collected</td>
            <td style="text-align: right;">{{ number_format($payments->sum('received_amount'), 2) }} BDT</td>
        </tr>
    </table>

    @if (!empty(optional($setup)->invoice_note ?? optional($company)->note ?? null))
    <div class="footer-note">{{ optional($setup)->invoice_note ?? optional($company)->note }}</div>
    @endif

    <div class="footer-note">Thank you for being our valued customer.</div>
</body>
</html>
