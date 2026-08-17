<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Registration Verification Code</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f6f8; font-family: Helvetica, Arial, sans-serif; color: #334155;">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f6f8; padding: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0;">

                    {{-- Header: company logo + title --}}
                    <tr>
                        <td style="background-color: #0f172a; padding: 24px 32px; text-align: center;">
                            @if (!empty($company?->image))
                                <img src="{{ $company->image }}" alt="{{ $company->title ?? config('app.name') }}" style="max-height: 56px; max-width: 200px; border-radius: 8px;" />
                            @else
                                <span style="font-size: 22px; font-weight: 700; color: #ffffff; letter-spacing: 0.5px;">
                                    {{ $company->title ?? config('app.name', 'Like Online') }}
                                </span>
                            @endif
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding: 32px;">
                            <p style="font-size: 15px; margin: 0 0 16px;">Dear Customer,</p>
                            <p style="font-size: 15px; line-height: 1.7; margin: 0 0 24px;">
                                Thank you for choosing
                                <strong>{{ $company->title ?? config('app.name', 'Like Online') }}</strong>.
                                Use the verification code below to complete your customer portal registration.
                            </p>

                            {{-- OTP card --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; padding: 24px; margin-bottom: 24px;">
                                <tr>
                                    <td align="center">
                                        <p style="font-size: 12px; text-transform: uppercase; letter-spacing: 1.5px; color: #64748b; margin: 0 0 10px;">Your Verification Code</p>
                                        <span style="display: inline-block; font-size: 34px; font-weight: 800; letter-spacing: 10px; color: #0f172a; background: #e2e8f0; padding: 10px 20px; border-radius: 8px;">
                                            {{ $otp }}
                                        </span>
                                    </td>
                                </tr>
                            </table>

                            <p style="font-size: 14px; line-height: 1.6; margin: 0 0 8px; color: #475569;">
                                ⏱ This code is valid for <strong>10 minutes</strong> only. After that, you will need to request a new code.
                            </p>
                            <p style="font-size: 14px; line-height: 1.6; margin: 0 0 24px; color: #475569;">
                                If you did not attempt to register for a customer portal account, you can safely ignore this email.
                            </p>

                            <p style="font-size: 14px; margin: 0;">Regards,<br /><strong>{{ $company->title ?? config('app.name', 'Like Online') }}</strong></p>
                        </td>
                    </tr>

                    {{-- Footer: company contact details --}}
                    <tr>
                        <td style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 20px 32px; text-align: center;">
                            <p style="font-size: 13px; font-weight: 600; color: #334155; margin: 0 0 6px;">
                                {{ $company->title ?? config('app.name', 'Like Online') }}
                            </p>
                            @if (!empty($company?->address1) || !empty($company?->address2))
                                <p style="font-size: 13px; line-height: 1.6; color: #64748b; margin: 0 0 4px;">
                                    {{ trim(($company->address1 ?? '') . ' ' . ($company->address2 ?? '')) }}
                                </p>
                            @endif
                            <p style="font-size: 13px; line-height: 1.6; color: #64748b; margin: 0;">
                                @if (!empty($company?->mobile1) || !empty($company?->mobile2))
                                    📞 {{ trim(($company->mobile1 ?? '') . ($company->mobile2 ? ' / ' . $company->mobile2 : '')) }}&nbsp;&nbsp;•&nbsp;&nbsp;
                                @endif
                                @if (!empty($company?->email))
                                    ✉️ {{ $company->email }}
                                @endif
                            </p>
                            @if (!empty($company?->website))
                                <p style="font-size: 13px; color: #64748b; margin: 4px 0 0;">🌐 {{ $company->website }}</p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

</body>
</html>
