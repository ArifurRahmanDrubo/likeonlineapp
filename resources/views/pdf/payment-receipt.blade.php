<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Receipt</title>
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
        .totals { width: 45%; margin-left: auto; }
        .totals td { padding: 6px 10px; font-size: 12px; }
        .totals .grand { font-weight: bold; font-size: 14px; color: #0f766e; border-top: 2px solid #0f766e; }
        .status-approved { color: #15803d; font-weight: bold; }
        .status-rejected { color: #b91c1c; font-weight: bold; }
        .status-pending { color: #b45309; font-weight: bold; }
        .footer-note { margin-top: 24px; padding-top: 12px; border-top: 1px solid #e2e8f0; font-size: 11px; color: #64748b; }
    </style>
</head>
<body>
    <div class="invoice-header">
        {{-- Company details render only when company_name_invoice is enabled --}}
        @if ($show_company ?? true)
        <div>
            {{-- Direct Cloudinary URL — rendered as-is, no base64 --}}
            @if (!empty(optional($setup)->image))
                <img src="{{ $setup->image }}" alt="{{ optional($setup)->company_name ?? 'Company Logo' }}" style="max-width: 150px; max-height: 60px; margin-bottom: 6px;" />
            @endif
            <div class="company-title">{{ optional($setup)->company_name ?? optional($company)->title ?? 'Internet Service Provider' }}</div>
            <div class="company-info">
                @if (!empty(optional($company)->address)){{ $company->address }}<br>@endif
                @if (!empty(optional($setup)->mobile ?? optional($company)->mobile))Mobile: {{ optional($setup)->mobile ?? optional($company)->mobile }}<br>@endif
                @if (!empty(optional($setup)->email ?? optional($company)->email))Email: {{ optional($setup)->email ?? optional($company)->email }}@endif
            </div>
        </div>
        @endif
        <div>
            <div class="doc-title">PAYMENT RECEIPT</div>
            <div class="doc-meta">
                Receipt No: <strong>{{ $payment->payment_id ?? 'PAY-' . $payment->id }}</strong><br>
                Date: {{ $payment->recieved_date }}
            </div>
        </div>
    </div>

    <div class="section-title">Received From</div>
    <div style="font-size: 12px; line-height: 1.6;">
        <strong>{{ $customer->name ?? $customer->username }}</strong><br>
        @if (!empty($customer->praddress)){{ $customer->praddress }}<br>@endif
        @if (!empty($customer->mobile))Mobile: {{ $customer->mobile }}<br>@endif
        PPPoE Username: {{ $customer->username }}<br>
        Package: {{ $customer->package ?? 'N/A' }} ({{ $customer->profile ?? 'N/A' }})
    </div>

    <div class="section-title">Payment Details</div>
    <table class="bill-table">
        <thead>
            <tr>
                <th>Field</th>
                <th>Detail</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Payment Method</td>
                <td>{{ $payment->payment_method ?? $payment->payment_info ?? '—' }}</td>
            </tr>
            <tr>
                <td>Transaction ID (TrxID)</td>
                <td>{{ $payment->transaction_no ?? '—' }}</td>
            </tr>
            @if (!empty($payment->billing_month))
            <tr>
                <td>Billing Month</td>
                <td>{{ \Carbon\Carbon::parse($payment->billing_month)->format('Y-m') }}</td>
            </tr>
            @endif
            <tr>
                <td>Received By</td>
                <td>{{ $payment->recieved_by ?? '—' }}</td>
            </tr>
            @if (!empty($payment->payment_info))
            <tr>
                <td>Payment Info</td>
                <td>{{ $payment->payment_info }}</td>
            </tr>
            @endif
            @if (!empty($payment->notes))
            <tr>
                <td>Notes</td>
                <td>{{ $payment->notes }}</td>
            </tr>
            @endif
            <tr>
                <td>Status</td>
                <td class="{{ $payment->approval_status === 'approved' ? 'status-approved' : ($payment->approval_status === 'rejected' ? 'status-rejected' : 'status-pending') }}">
                    {{ ucfirst($payment->approval_status ?? 'pending') }}
                </td>
            </tr>
        </tbody>
    </table>

    <table class="bill-table totals">
        <tr>
            <td>Received Amount</td>
            <td style="text-align: right;">{{ number_format((float) $payment->received_amount, 2) }} BDT</td>
        </tr>
        @if ((float) $payment->discount > 0)
        <tr>
            <td>Discount</td>
            <td style="text-align: right;">-{{ number_format((float) $payment->discount, 2) }} BDT</td>
        </tr>
        @endif
        <tr class="grand">
            <td>Total Amount</td>
            <td style="text-align: right;">{{ number_format((float) $payment->total_amount, 2) }} BDT</td>
        </tr>
    </table>

    @if (!empty(optional($setup)->invoice_note ?? optional($company)->note ?? null))
    <div class="footer-note">{{ optional($setup)->invoice_note ?? optional($company)->note }}</div>
    @endif

    <div class="footer-note">Thank you for being our valued customer.</div>
</body>
</html>
