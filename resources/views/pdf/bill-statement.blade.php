<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bill Statement - {{ $customer->name }}</title>
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
        .status-paid { color: #15803d; font-weight: bold; }
        .status-unpaid { color: #b91c1c; font-weight: bold; }
        .status-partial { color: #b45309; font-weight: bold; }
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
            <div class="doc-title">BILL STATEMENT</div>
            <div class="doc-meta">
                Customer: <strong>{{ $customer->name ?? $customer->username }}</strong><br>
                PPPoE Username: {{ $customer->username }}<br>
                Generated: {{ now()->format('d F Y') }}
            </div>
        </div>
    </div>

    <div class="section-title">All Bills</div>
    <table class="bill-table">
        <thead>
            <tr>
                <th>Billing Month</th>
                <th style="text-align: right;">Amount (BDT)</th>
                <th style="text-align: right;">Paid (BDT)</th>
                <th style="text-align: right;">Due (BDT)</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['billing_month'] }}</td>
                    <td style="text-align: right;">{{ number_format((float) $row['amount'], 2) }}</td>
                    <td style="text-align: right;">{{ number_format((float) $row['paid_amount'], 2) }}</td>
                    <td style="text-align: right;">{{ number_format((float) $row['due_amount'], 2) }}</td>
                    <td class="{{ $row['status'] === 'paid' ? 'status-paid' : ($row['status'] === 'partially_paid' || $row['status'] === 'partial' ? 'status-partial' : 'status-unpaid') }}">
                        {{ $row['type'] === 'previous_due' ? 'Previous Due' : ucfirst(str_replace('_', ' ', $row['status'])) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; color: #64748b;">No bills have been generated for this account.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="bill-table totals">
        <tr class="grand">
            <td>Total Due</td>
            <td style="text-align: right;">{{ number_format((float) $due_total, 2) }} BDT</td>
        </tr>
    </table>

    @if (!empty(optional($setup)->invoice_note ?? optional($company)->note ?? null))
    <div class="footer-note">{{ optional($setup)->invoice_note ?? optional($company)->note }}</div>
    @endif

    <div class="footer-note">Thank you for being our valued customer.</div>
</body>
</html>
