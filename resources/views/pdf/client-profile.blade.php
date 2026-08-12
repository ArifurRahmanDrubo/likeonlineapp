<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Client Profile - {{ $customer->name }}</title>
    <style>
        /* ===== Strict PDF color/text rules ===== */
        .bg-primary {
            background-color: #0d6efd !important;
            color: #ffffff !important;
        }

        .btn-primary {
            background-color: #0d6efd !important;
            color: #ffffff !important;
        }

        th {
            background-color: #212529 !important;
            color: #ffffff !important;
            font-weight: bold;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9.5pt;
            color: #212529;
            line-height: 1.45;
            margin: 0;
            padding: 0;
        }

        .section {
            margin-bottom: 18px;
            page-break-inside: avoid;
        }

        .section-title {
            background-color: #0d6efd !important;
            color: #ffffff !important;
            padding: 6px 10px;
            font-size: 11pt;
            font-weight: bold;
            border-radius: 3px;
            margin: 0 0 8px 0;
        }

        table.info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        table.info-table td {
            border: 0.5px solid #dee2e6;
            padding: 4px 8px;
            vertical-align: top;
        }

        .info-label {
            width: 25%;
            background-color: #f1f3f5;
            font-weight: bold;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        table.data-table th,
        table.data-table td {
            border: 0.5px solid #dee2e6;
            padding: 4px 6px;
            font-size: 8.5pt;
        }

        table.data-table td {
            background-color: #ffffff !important;
            color: #212529 !important;
        }

        .empty-note {
            border: 0.5px solid #dee2e6;
            padding: 10px;
            text-align: center;
            color: #6c757d;
            background: #f8f9fa;
        }

        .text-muted {
            color: #6c757d;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .mb-1 {
            margin-bottom: 4px;
        }

        .mb-2 {
            margin-bottom: 8px;
        }

        .mt-2 {
            margin-top: 8px;
        }

        /* Company header */
        .company-header {
            border-bottom: 3px solid #0d6efd;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }

        .company-header h1 {
            margin: 0;
            font-size: 16pt;
            color: #0d6efd;
        }

        .company-header .contacts {
            font-size: 8pt;
            color: #495057;
            margin-top: 3px;
        }

        /* Profile hero */
        .profile-hero {
            background-color: #0d6efd !important;
            color: #ffffff !important;
            padding: 22px 12px 12px 12px;
            text-align: center;
            border-radius: 4px;
        }

        /* Fixed PDF Circle Images using exact pixel radius */
        .img-circle-sm {
            width: 52px;
            height: 52px;
            border-radius: 26px !important;
            border: 3px solid #ffffff;
            vertical-align: bottom;
        }

        .img-circle-lg {
            width: 104px;
            height: 104px;
            border-radius: 52px !important;
            border: 4px solid #ffffff;
            vertical-align: middle;
        }

        .avatar-fallback {
            display: inline-block;
            width: 52px;
            height: 52px;
            border-radius: 26px !important;
            background-color: #6c757d !important;
            color: #ffffff !important;
            font-weight: bold;
            font-size: 20px;
            line-height: 52px;
            text-align: center;
            border: 3px solid #ffffff;
        }

        .avatar-fallback.lg {
            width: 104px;
            height: 104px;
            border-radius: 52px !important;
            font-size: 38px;
            line-height: 104px;
            border-width: 4px;
        }

        .profile-hero .name {
            font-size: 15pt;
            font-weight: bold;
            margin: 10px 0 2px 0;
        }

        .profile-hero .email {
            font-size: 9pt;
            opacity: 0.9;
            margin: 0 0 8px 0;
        }
    </style>
</head>

<body>

    {{-- Company header --}}
    <div class="company-header">
        <table style="width:100%; border-collapse:collapse;">
            <tr>
                <td style="width:70%; vertical-align:middle;">
                    <h1>{{ $company->title ?? 'Client Profile' }}</h1>
                    <div class="contacts">
                        @if (!empty($company->address1))
                            {{ $company->address1 }}
                        @endif
                        @if (!empty($company->address2))
                            , {{ $company->address2 }}
                        @endif
                        @if (!empty($company->mobile1) || !empty($company->mobile2))
                            &nbsp;|&nbsp; {{ $company->mobile1 }} {{ $company->mobile2 }}
                        @endif
                        @if (!empty($company->email))
                            &nbsp;|&nbsp; {{ $company->email }}
                        @endif
                        @if (!empty($company->website))
                            &nbsp;|&nbsp; {{ $company->website }}
                        @endif
                    </div>
                </td>
                <td style="width:30%; text-align:right; vertical-align:middle;">
                    @if (!empty($images['company']))
                        <img src="{{ $images['company'] }}" style="max-width:110px; max-height:50px;" alt="">
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- ============ PROFILE INFORMATION ============ --}}
    @if (in_array('Profile', $sections, true))
        <div class="section">
            <div class="profile-hero">
                @php($initial = strtoupper(mb_substr(trim($customer->name ?? ''), 0, 1) ?: 'C'))
                <table style="width:100%; border-collapse:collapse;">
                    <tr>
                        <td style="width:28%; text-align:right; vertical-align:bottom; padding:0 6px 4px 0;">
                            @if (!empty($images['nid']))
                                <img src="{{ $images['nid'] }}" width="52" height="52" class="img-circle-sm" alt="NID">
                            @else
                                <div class="avatar-fallback">{{ $initial }}</div>
                            @endif
                        </td>

                        <td style="width:44%; text-align:center; vertical-align:middle; padding-bottom:6px;">
                            @if (!empty($images['profile']))
                                <img src="{{ $images['profile'] }}" width="104" height="104" class="img-circle-lg" alt="Profile">
                            @else
                                <div class="avatar-fallback lg">{{ $initial }}</div>
                            @endif
                        </td>

                        <td style="width:28%; text-align:left; vertical-align:bottom; padding:0 0 4px 6px;">
                            @if (!empty($images['registration']))
                                <img src="{{ $images['registration'] }}" width="52" height="52" class="img-circle-sm" alt="Registration">
                            @else
                                <div class="avatar-fallback">{{ $initial }}</div>
                            @endif
                        </td>
                    </tr>
                </table>
                <div class="name">{{ $customer->name }}</div>
                <div class="email">{{ $customer->email }}</div>
            </div>

            <table class="info-table mt-2">
                <tr>
                    <td class="info-label">Client Code</td>
                    <td>{{ $customer->formatted_id }}</td>
                    <td class="info-label">Client ID / IP</td>
                    <td>{{ $customer->username }}</td>
                </tr>
                <tr>
                    <td class="info-label">Billing Status</td>
                    <td>{{ $customer->billingstatus ?? 'N/A' }}</td>
                    <td class="info-label">MikroTik Status</td>
                    <td>{{ $customer->mikrotikStatus ? 'Enabled' : 'Disabled' }}</td>
                </tr>
                <tr>
                    <td class="info-label">Creation Date</td>
                    <td colspan="3">{{ \Carbon\Carbon::parse($customer->created_at)->format('F d, Y') }}</td>
                </tr>
            </table>
        </div>
    @endif

    {{-- ============ SERVICE INFORMATION ============ --}}
    @if (in_array('Service', $sections, true))
        <div class="section">
            <div class="section-title">Internet Service Information</div>
            <table class="info-table">
                <tr>
                    <td class="info-label">Package</td>
                    <td>{{ $customer->package ?? 'N/A' }}</td>
                    <td class="info-label">Joining Date</td>
                    <td>{{ $customer->joiningdate ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="info-label">Profile (Speed)</td>
                    <td>{{ $customer->profile ?? 'N/A' }}</td>
                    <td class="info-label">Client Type</td>
                    <td>{{ $customer->clienttype ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="info-label">Billing Start Month</td>
                    <td>
                        @if (!empty($billingStartMonth))
                            {{ \Carbon\Carbon::parse('01 ' . preg_replace('/\s*\([^)]*\)/', '', $billingStartMonth))->format('F Y') }}
                        @else
                            N/A
                        @endif
                    </td>
                    <td class="info-label">Expired Date</td>
                    <td>{{ $customer->expireddate ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="info-label">Monthly Bill</td>
                    <td>{{ number_format((float) $customer->monthlybill, 2) }}</td>
                    <td class="info-label">Connection Type</td>
                    <td>{{ $customer->connectiontype ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="info-label">Reference By</td>
                    <td>{{ $customer->referenceby ?? 'N/A' }}</td>
                    <td class="info-label">Connected By</td>
                    <td>{{ $customer->connectedby ?? 'N/A' }}</td>
                </tr>
            </table>
        </div>
    @endif

    {{-- ============ PERSONAL INFORMATION ============ --}}
    @if (in_array('Personal', $sections, true))
        <div class="section">
            <div class="section-title">Customer Personal Information</div>
            <table class="info-table">
                <tr>
                    <td class="info-label">Father Name</td>
                    <td>{{ $customer->fathername ?? 'N/A' }}</td>
                    <td class="info-label">Mother Name</td>
                    <td>{{ $customer->mothername ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="info-label">Date Of Birth</td>
                    <td>{{ $customer->dateofbirth ?? 'N/A' }}</td>
                    <td class="info-label">NID</td>
                    <td>{{ $customer->nid ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="info-label">Registration No</td>
                    <td>{{ $customer->registrationno ?? 'N/A' }}</td>
                    <td class="info-label">Gender</td>
                    <td>{{ ucfirst($customer->gender ?? 'N/A') }}</td>
                </tr>
                <tr>
                    <td class="info-label">Occupation</td>
                    <td>{{ $customer->occupation ?? 'N/A' }}</td>
                    <td class="info-label">Mobile</td>
                    <td>{{ $customer->mobile ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="info-label">Phone</td>
                    <td>{{ $customer->phone ?? 'N/A' }}</td>
                    <td class="info-label">Email</td>
                    <td>{{ $customer->email ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="info-label">Facebook</td>
                    <td>{{ $customer->facebook ?? 'N/A' }}</td>
                    <td class="info-label">LinkedIn</td>
                    <td>{{ $customer->linkedin ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="info-label">District</td>
                    <td>{{ $customer->district ?? 'N/A' }}</td>
                    <td class="info-label">Upzila</td>
                    <td>{{ $customer->upzila ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="info-label">Road No</td>
                    <td>{{ $customer->roadnumber ?? 'N/A' }}</td>
                    <td class="info-label">House No</td>
                    <td>{{ $customer->housenumber ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="info-label">Present Address</td>
                    <td colspan="3">{{ $customer->praddress ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="info-label">Permanent Address</td>
                    <td colspan="3">{{ $customer->paraddress ?? 'N/A' }}</td>
                </tr>
            </table>
        </div>
    @endif

    {{-- ============ NETWORK & PRODUCT INFORMATION ============ --}}
    @if (in_array('Network', $sections, true))
        <div class="section">
            <div class="section-title">Networks &amp; Products Information</div>
            <table class="info-table">
                <tr>
                    <td class="info-label">Server</td>
                    <td>{{ $customer->server ?? 'N/A' }}</td>
                    <td class="info-label">Protocol</td>
                    <td>{{ $customer->protocoltype ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="info-label">Zone</td>
                    <td>{{ $customer->zone ?? 'N/A' }}</td>
                    <td class="info-label">Sub Zone</td>
                    <td>{{ $customer->subzone ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="info-label">Box</td>
                    <td>{{ $customer->box ?? 'N/A' }}</td>
                    <td class="info-label">Connection Type</td>
                    <td>{{ $customer->connectiontype ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="info-label">Latitude</td>
                    <td>{{ $customer->latitude ?? 'N/A' }}</td>
                    <td class="info-label">Longitude</td>
                    <td>{{ $customer->longitude ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="info-label">Cable Required (m)</td>
                    <td>{{ $customer->cable ?? 'N/A' }}</td>
                    <td class="info-label">Fiber Core</td>
                    <td>{{ $customer->fiber ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="info-label">Number Of Core</td>
                    <td>{{ $customer->coreno ?? 'N/A' }}</td>
                    <td class="info-label">Core Color</td>
                    <td>{{ $customer->corecolor ?? 'N/A' }}</td>
                </tr>
            </table>
        </div>
    @endif

    {{-- ============ RECEIVED BILL HISTORY ============ --}}
    @if (in_array('ReceivedBill', $sections, true))
        <div class="section">
            <div class="section-title">All Received Bill History</div>
            @if ($customer->payment->count())
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Receive Date</th>
                            <th>Received By</th>
                            <th>Payment Info</th>
                            <th>Discount</th>
                            <th>Received Bill</th>
                            <th>Total Bill</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($customer->payment as $payment)
                            <tr>
                                <td>{{ $payment->recieved_date }}</td>
                                <td>{{ $payment->recieved_by ?? 'N/A' }}</td>
                                <td>{{ $payment->payment_info ?? 'N/A' }}</td>
                                <td class="text-right">{{ number_format((float) $payment->discount, 2) }}</td>
                                <td class="text-right">{{ number_format((float) $payment->received_amount, 2) }}</td>
                                <td class="text-right">{{ number_format((float) $payment->total_amount, 2) }}</td>
                                <td>{{ ucfirst($payment->approval_status ?? 'pending') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="empty-note">No received bill history available for this client.</div>
            @endif
        </div>
    @endif

    {{-- ============ GENERATED & UPDATED BILL/INVOICES ============ --}}
    @if (in_array('GenerateBill', $sections, true))
        <div class="section">
            <div class="section-title">Generated &amp; Updated Bill/Invoice</div>
            @if ($customer->generatedBill->count())
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Billing Month</th>
                            <th>Package</th>
                            <th>Speed</th>
                            <th>Bill Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($customer->generatedBill as $bill)
                            <tr>
                                <td>{{ $bill->generated_at }}</td>
                                <td>{{ $bill->billing_month }}</td>
                                <td>{{ $bill->package }}</td>
                                <td>{{ $bill->speed }}</td>
                                <td class="text-right">{{ number_format((float) $bill->amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="empty-note">No generated bills available for this client.</div>
            @endif
        </div>
    @endif

    {{-- ============ CUSTOMER ENABLE & DISABLE LOG ============ --}}
    @if (in_array('CustomerStatus', $sections, true))
        <div class="section">
            <div class="section-title">Customer Enable &amp; Disable Log (Status Change History)</div>
            @if ($customer->statusChanged->count())
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Billing Status</th>
                            <th>Remarks / Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($customer->statusChanged as $change)
                            <tr>
                                <td>{{ $change->executiondate ?? $change->created_at?->format('Y-m-d') }}</td>
                                <td>{{ $change->billingstatus }}</td>
                                <td>{{ $change->notes ?? 'N/A' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="empty-note">No status change history available for this client.</div>
            @endif
        </div>
    @endif

    {{-- ============ MESSAGE HISTORY ============ --}}
    @if (in_array('Message', $sections, true))
        <div class="section">
            <div class="section-title">Message History</div>
            <div class="empty-note">No message history is stored for this client.</div>
        </div>
    @endif

    {{-- ============ PRODUCT SALES & SERVICE SALES ============ --}}
    @if (in_array('ProductSell', $sections, true))
        <div class="section">
            <div class="section-title">Product Sales &amp; Service Sales</div>
            <div class="empty-note">No product / service sales are linked to this client.</div>
        </div>
    @endif

    <div class="text-muted text-center mb-2" style="margin-top:24px; font-size:7.5pt;">
        Generated on {{ now()->format('F d, Y h:i A') }}
    </div>
</body>

</html>