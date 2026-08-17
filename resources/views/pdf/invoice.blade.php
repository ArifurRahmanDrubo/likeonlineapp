{{-- <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1e293b; margin: 0; }
        .invoice-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #0f766e; padding-bottom: 14px; margin-bottom: 18px; }
        .company-title { font-size: 20px; font-weight: bold; color: #0f766e; margin-bottom: 4px; }
        .company-info { font-size: 11px; color: #475569; line-height: 1.5; }
        .invoice-title { font-size: 26px; font-weight: bold; color: #0f766e; text-align: right; }
        .invoice-meta { text-align: right; font-size: 11px; color: #475569; margin-top: 6px; }
        .section-title { font-size: 13px; font-weight: bold; color: #0f766e; margin: 16px 0 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        .bill-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .bill-table th { background: #0f766e; color: #ffffff; text-align: left; padding: 8px 10px; font-size: 11px; }
        .bill-table td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; font-size: 12px; }
        .bill-table tr:nth-child(even) td { background: #f8fafc; }
        .totals { width: 40%; margin-left: auto; }
        .totals td { padding: 6px 10px; font-size: 12px; }
        .totals .grand { font-weight: bold; font-size: 14px; color: #0f766e; border-top: 2px solid #0f766e; }
        .status-paid { color: #15803d; font-weight: bold; }
        .status-unpaid { color: #b91c1c; font-weight: bold; }
        .status-partial { color: #b45309; font-weight: bold; }
        .footer-note { margin-top: 24px; padding-top: 12px; border-top: 1px solid #e2e8f0; font-size: 11px; color: #64748b; }
    </style>
</head>
<body>
    @php
        // Status badge: PAID → green, UNPAID / PARTIAL → red.
        $badgeLabel = $invoice->status === 'paid'
            ? 'PAID'
            : (in_array($invoice->status, ['partially_paid', 'partial']) ? 'PARTIAL' : 'UNPAID');
        $badgeColor = $badgeLabel === 'PAID' ? '#28a745' : '#dc3545';
    @endphp

    <div class="invoice-header">
        <div>
            <div class="company-title">{{ optional($setup)->company_name ?? optional($company)->title ?? 'Internet Service Provider' }}</div>
            <div class="company-info">
                @if (!empty(optional($company)->address)){{ $company->address }}<br>@endif
                @if (!empty(optional($setup)->mobile ?? optional($company)->mobile))Mobile: {{ optional($setup)->mobile ?? optional($company)->mobile }}<br>@endif
                @if (!empty(optional($setup)->email ?? optional($company)->email))Email: {{ optional($setup)->email ?? optional($company)->email }}@endif
            </div>
        </div>
        <div>
            <table style="border-collapse: collapse; margin-left: auto;">
                <tr>
                    <td style="vertical-align: top; text-align: right;">
                        <div class="invoice-title">INVOICE</div>
                        <div class="invoice-meta">
                            Invoice No: <strong>INV-{{ str_pad((string) $invoice->id, 4, '0', STR_PAD_LEFT) }}</strong><br>
                            Billing Month: {{ $invoice->billing_month }}
                        </div>
                    </td>
                    {{-- 45° angled status badge — rendered as an inline SVG (mPDF only
                       
                    <td style="vertical-align: top; width: 120px; padding: 0 0 0 10px;">
                        <svg width="120" height="120" xmlns="http://www.w3.org/2000/svg" style="display: block;">
                            <g transform="rotate(-45 60 60)">
                                <rect x="15" y="47" width="90" height="26" rx="4" fill="{{ $badgeColor }}" />
                                <text x="60" y="66" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="14" font-weight="bold" fill="#ffffff">{{ $badgeLabel }}</text>
                            </g>
                        </svg>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div class="section-title">Billed To</div>
    <div style="font-size: 12px; line-height: 1.6;">
        <strong>{{ $customer->name ?? $customer->username }}</strong><br>
        @if (!empty($customer->praddress)){{ $customer->praddress }}<br>@endif
        @if (!empty($customer->mobile))Mobile: {{ $customer->mobile }}<br>@endif
        @if (!empty($customer->email))Email: {{ $customer->email }}<br>@endif
        PPPoE Username: {{ $customer->username }}<br>
        Package: {{ $customer->package ?? 'N/A' }} ({{ $customer->profile ?? 'N/A' }})
    </div>

    <div class="section-title">Bill Details</div>
    <table class="bill-table">
        <thead>
            <tr>
                <th>Description</th>
                <th style="text-align: right;">Amount (BDT)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Monthly bill — {{ $invoice->billing_month }} ({{ $customer->package ?? 'Package' }})</td>
                <td style="text-align: right;">{{ number_format((float) $invoice->amount, 2) }}</td>
            </tr>
            @if ((float) $invoice->discount > 0)
            <tr>
                <td>Discount</td>
                <td style="text-align: right;">-{{ number_format((float) $invoice->discount, 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <table class="bill-table totals">
        <tr>
            <td>Due Amount</td>
            <td style="text-align: right;">{{ number_format((float) $invoice->amount, 2) }}</td>
        </tr>
        <tr>
            <td>Received</td>
            <td style="text-align: right;">{{ number_format((float) $invoice->received_amount, 2) }}</td>
        </tr>
        <tr>
            <td>Advance</td>
            <td style="text-align: right;">{{ number_format((float) $invoice->advance, 2) }}</td>
        </tr>
        <tr class="grand">
            <td>Status</td>
            <td style="text-align: right;" class="{{ $invoice->status === 'paid' ? 'status-paid' : ($invoice->status === 'partial' ? 'status-partial' : 'status-unpaid') }}">
                {{ ucfirst($invoice->status) }}
            </td>
        </tr>
    </table>

    @if (!empty(optional($setup)->invoice_note ?? optional($company)->note ?? null))
    <div class="footer-note">{{ optional($setup)->invoice_note ?? optional($company)->note }}</div>
    @endif

    <div class="footer-note">Thank you for being our valued customer.</div>
</body>
</html> --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice - INV-{{ str_pad((string) $invoice->id, 4, '0', STR_PAD_LEFT) }}</title>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: 'DejaVu Sans', sans-serif; 
            font-size: 11px; 
            color: #334155; 
            margin: 0; 
            padding: 0;
            background: #ffffff;
        }

        /* ── Top-Right Corner 45° Ribbon Badge ── */
        .corner-ribbon-wrapper {
            position: absolute;
            top: 0;
            right: 0;
            width: 140px;
            height: 140px;
            overflow: hidden;
            z-index: 9999;
        }

        /* ── Main Layout Containers ── */
        .invoice-container {
            padding: 10px 5px;
        }

        .invoice-header { 
            width: 100%;
            border-bottom: 2px solid #0f766e; 
            padding-bottom: 16px; 
            margin-bottom: 20px; 
        }

        .company-title { 
            font-size: 22px; 
            font-weight: bold; 
            color: #0f766e; 
            margin-bottom: 4px; 
            letter-spacing: -0.5px;
        }

        .company-info { 
            font-size: 10px; 
            color: #64748b; 
            line-height: 1.5; 
        }

        .invoice-title { 
            font-size: 24px; 
            font-weight: bold; 
            color: #0f766e; 
            text-align: right; 
            letter-spacing: 1px;
        }

        .invoice-meta { 
            text-align: right; 
            font-size: 11px; 
            color: #475569; 
            margin-top: 6px; 
            line-height: 1.5;
        }

        /* ── Customer & Info Cards ── */
        .info-grid {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }

        .info-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px 14px;
            vertical-align: top;
        }

        .section-title { 
            font-size: 11px; 
            font-weight: bold; 
            color: #0f766e; 
            margin-bottom: 6px; 
            text-transform: uppercase; 
            letter-spacing: 0.8px; 
        }

        .customer-details {
            font-size: 11px;
            line-height: 1.6;
            color: #1e293b;
        }

        /* ── Tables ── */
        .bill-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 20px; 
        }

        .bill-table th { 
            background: #0f766e; 
            color: #ffffff; 
            text-align: left; 
            padding: 9px 12px; 
            font-size: 11px; 
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .bill-table td { 
            padding: 10px 12px; 
            border-bottom: 1px solid #e2e8f0; 
            font-size: 11px; 
        }

        .bill-table tr:nth-child(even) td { 
            background: #f8fafc; 
        }

        /* ── Summary & Totals Table ── */
        .summary-wrapper {
            width: 100%;
            margin-bottom: 20px;
        }

        .totals { 
            width: 45%; 
            margin-left: auto; 
            border-collapse: collapse;
        }

        .totals td { 
            padding: 6px 10px; 
            font-size: 11px; 
            color: #475569;
        }

        .totals .grand { 
            font-weight: bold; 
            font-size: 13px; 
            color: #0f766e; 
            border-top: 2px solid #0f766e; 
            border-bottom: 2px solid #0f766e;
            background: #f0fdf4;
        }

        .status-paid { color: #16a34a; font-weight: bold; }
        .status-unpaid { color: #dc2626; font-weight: bold; }
        .status-partial { color: #d97706; font-weight: bold; }

        /* ── Footer ── */
        .footer-container {
            margin-top: 30px;
            padding-top: 14px;
            border-top: 1px solid #e2e8f0;
            font-size: 10px;
            color: #64748b;
            text-align: center;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    @php
        // Dynamic Status Badge setup
        $badgeLabel = $invoice->status === 'paid'
            ? 'PAID'
            : (in_array($invoice->status, ['partially_paid', 'partial']) ? 'PARTIAL' : 'UNPAID');
        
        $badgeColor = $badgeLabel === 'PAID' ? '#16a34a' : ($badgeLabel === 'PARTIAL' ? '#d97706' : '#dc2626');
    @endphp

    {{-- Top-Right Corner 45° Angle Badge Ribbon (WHMCS Style) --}}
    <div class="corner-ribbon-wrapper">
        <svg width="140" height="140" viewBox="0 0 140 140" xmlns="http://www.w3.org/2000/svg">
            <g transform="rotate(45 70 70)">
                <rect x="-20" y="32" width="180" height="30" fill="{{ $badgeColor }}" />
                <text x="70" y="52" text-anchor="middle" font-family="'DejaVu Sans', sans-serif" font-size="13" font-weight="bold" fill="#ffffff" letter-spacing="1.5">{{ $badgeLabel }}</text>
            </g>
        </svg>
    </div>

    <div class="invoice-container">
        {{-- Header Section --}}
        <table class="invoice-header">
            <tr>
                {{-- Company details render only when company_name_invoice is enabled --}}
                @if ($show_company ?? true)
                <td style="vertical-align: top; width: 60%;">
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
                </td>
                @endif
                <td style="vertical-align: top; text-align: right; width: 40%; padding-right: 40px;">
                    <div class="invoice-title">INVOICE</div>
                    <div class="invoice-meta">
                        Invoice No: <strong>INV-{{ str_pad((string) $invoice->id, 4, '0', STR_PAD_LEFT) }}</strong><br>
                        Billing Month: <strong>{{ $invoice->billing_month }}</strong>
                    </div>
                </td>
            </tr>
        </table>

        {{-- Billed To Section --}}
        <table class="info-grid">
            <tr>
                <td class="info-card">
                    <div class="section-title">Billed To</div>
                    <div class="customer-details">
                        <strong>{{ $customer->name ?? $customer->username }}</strong><br>
                        @if (!empty($customer->praddress)){{ $customer->praddress }}<br>@endif
                        @if (!empty($customer->mobile))Mobile: {{ $customer->mobile }}<br>@endif
                        @if (!empty($customer->email))Email: {{ $customer->email }}<br>@endif
                        PPPoE Username: <strong>{{ $customer->username }}</strong><br>
                        Package: {{ $customer->package ?? 'N/A' }} @if(!empty($customer->profile))({{ $customer->profile }})@endif
                    </div>
                </td>
            </tr>
        </table>

        {{-- Bill Details Table --}}
        <div class="section-title" style="margin-bottom: 8px;">Bill Details</div>
        <table class="bill-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th style="text-align: right; width: 30%;">Amount (BDT)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Monthly Internet Bill — {{ $invoice->billing_month }} ({{ $customer->package ?? 'Package' }})</td>
                    <td style="text-align: right; font-weight: 500;">{{ number_format((float) $invoice->amount, 2) }}</td>
                </tr>
                @if ((float) $invoice->discount > 0)
                <tr>
                    <td>Discount</td>
                    <td style="text-align: right; color: #dc2626;">-{{ number_format((float) $invoice->discount, 2) }}</td>
                </tr>
                @endif
            </tbody>
        </table>

        {{-- Totals Summary --}}
        <div class="summary-wrapper">
            <table class="totals">
                <tr>
                    <td>Total Due Amount</td>
                    <td style="text-align: right; font-weight: 600;">{{ number_format((float) $invoice->amount, 2) }} BDT</td>
                </tr>
                <tr>
                    <td>Received Amount</td>
                    <td style="text-align: right; font-weight: 600; color: #16a34a;">{{ number_format((float) $invoice->received_amount, 2) }} BDT</td>
                </tr>
                <tr>
                    <td>Advance Balance</td>
                    <td style="text-align: right;">{{ number_format((float) $invoice->advance, 2) }} BDT</td>
                </tr>
                <tr class="grand">
                    <td style="padding: 8px 10px;">Payment Status</td>
                    <td style="text-align: right; padding: 8px 10px;" class="{{ $invoice->status === 'paid' ? 'status-paid' : ($invoice->status === 'partial' ? 'status-partial' : 'status-unpaid') }}">
                        {{ strtoupper($invoice->status) }}
                    </td>
                </tr>
            </table>
        </div>

        {{-- Footer Notes --}}
        <div class="footer-container">
            @if (!empty(optional($setup)->invoice_note ?? optional($company)->note ?? null))
                <div style="margin-bottom: 4px; font-weight: 500;">{{ optional($setup)->invoice_note ?? optional($company)->note }}</div>
            @endif
            <div>Thank you for being our valued customer.</div>
        </div>
    </div>
</body>
</html>