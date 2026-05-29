<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificate Ready</title>
</head>
<body style="margin:0;padding:24px;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e2e8f0;">

        {{-- Header --}}
        <tr>
            <td style="padding:28px 32px 0;text-align:center;">
                <img src="{{ asset('images/dosttt.png') }}" alt="DOST Logo" style="width:48px;height:48px;object-fit:contain;display:block;margin:0 auto 10px;">
                <div style="font-size:20px;font-weight:800;letter-spacing:0.03em;color:#0f172a;">DOST CARAGA</div>
                <div style="margin-top:14px;font-size:24px;font-weight:800;line-height:1.25;color:#0f172a;">
                    Your Certificate is Ready
                </div>
            </td>
        </tr>

        {{-- Greeting --}}
        <tr>
            <td style="padding:20px 32px 0;">
                <p style="margin:0;font-size:16px;line-height:1.6;color:#334155;">
                    Hello <strong>{{ $certificate->participant_name }}</strong>,
                </p>
                <p style="margin:10px 0 0;font-size:15px;line-height:1.65;color:#475569;">
                    Your <strong>{{ $certificate->certificate_type ?? 'Certificate' }}</strong>
                    for <strong>{{ $certificate->training_title }}</strong>
                    @if ($certificate->training_date)
                        held on {{ $certificate->training_date->format('F j, Y') }}
                    @endif
                    @if ($certificate->venue)
                        at {{ $certificate->venue }}
                    @endif
                    is now available. A PDF copy is attached to this email.
                </p>
            </td>
        </tr>

        {{-- Buttons row — all three on one line --}}
        <tr>
            <td style="padding:24px 32px 0;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding:0 8px 0 0;width:33%;">
                            <a href="{{ $downloadUrl }}" style="display:block;padding:12px 8px;border-radius:10px;background:#0f172a;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;text-align:center;white-space:nowrap;">
                                Download PDF
                            </a>
                        </td>
                        <td style="padding:0 4px;width:33%;">
                            <a href="{{ $verifyUrl }}" style="display:block;padding:12px 8px;border-radius:10px;background:#0891b2;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;text-align:center;white-space:nowrap;">
                                Verify Online
                            </a>
                        </td>
                        <td style="padding:0 0 0 8px;width:34%;">
                            @if ($isDormant && $claimUrl)
                                <a href="{{ $claimUrl }}" style="display:block;padding:12px 8px;border-radius:10px;background:#059669;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;text-align:center;white-space:nowrap;">
                                    Claim Account
                                </a>
                            @elseif (! $isDormant && $certificate->recipient)
                                <a href="{{ route('recipient.certificates') }}" style="display:block;padding:12px 8px;border-radius:10px;background:#059669;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;text-align:center;white-space:nowrap;">
                                    View Dashboard
                                </a>
                            @else
                                &nbsp;
                            @endif
                        </td>
                    </tr>
                </table>

                {{-- Claim explanation text below buttons --}}
                @if ($isDormant && $claimUrl)
                    <p style="margin:12px 0 0;font-size:13px;line-height:1.5;color:#64748b;text-align:center;">
                        New to CERTiFY? <strong>Claim your account</strong> to keep all your certificates in one place.
                        This link does not expire.
                    </p>
                @elseif (! $isDormant && $certificate->recipient)
                    <p style="margin:12px 0 0;font-size:13px;line-height:1.5;color:#64748b;text-align:center;">
                        This certificate has been added to your CERTiFY account.
                    </p>
                @endif
            </td>
        </tr>

        {{-- Divider --}}
        <tr>
            <td style="padding:20px 32px;">
                <div style="height:1px;background:#e2e8f0;"></div>
            </td>
        </tr>

        {{-- Certificate details --}}
        <tr>
            <td style="padding:0 32px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;">
                    <tr>
                        <td style="padding:16px 20px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding:4px 0;font-size:14px;color:#475569;width:50%;"><strong>Certificate No.</strong></td>
                                    <td style="padding:4px 0;font-size:14px;color:#0f172a;">{{ $certificate->certificate_code }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px 0;font-size:14px;color:#475569;"><strong>Type</strong></td>
                                    <td style="padding:4px 0;font-size:14px;color:#0f172a;">{{ $certificate->certificate_type ?: 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px 0;font-size:14px;color:#475569;"><strong>Recipient</strong></td>
                                    <td style="padding:4px 0;font-size:14px;color:#0f172a;">{{ $certificate->recipient_type ?: 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px 0;font-size:14px;color:#475569;"><strong>Activity</strong></td>
                                    <td style="padding:4px 0;font-size:14px;color:#0f172a;">{{ $certificate->activity_type ?: 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px 0;font-size:14px;color:#475569;"><strong>Schedule</strong></td>
                                    <td style="padding:4px 0;font-size:14px;color:#0f172a;">
                                        @if ($certificate->training_date && $certificate->training_date_to && $certificate->training_date->format('F j, Y') !== $certificate->training_date_to->format('F j, Y'))
                                            {{ $certificate->training_date->format('F j, Y') }} &ndash; {{ $certificate->training_date_to->format('F j, Y') }}
                                        @elseif ($certificate->training_date)
                                            {{ $certificate->training_date->format('F j, Y') }}
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:4px 0;font-size:14px;color:#475569;"><strong>Venue</strong></td>
                                    <td style="padding:4px 0;font-size:14px;color:#0f172a;">{{ $certificate->venue ?: 'N/A' }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- Links as plain text --}}
        <tr>
            <td style="padding:20px 32px 0;">
                <p style="margin:0;font-size:12px;color:#94a3b8;">
                    If the buttons above don't work, copy and paste these links:
                </p>
                <p style="margin:6px 0 0;font-size:12px;line-height:1.7;word-break:break-all;color:#64748b;">
                    Download: <a href="{{ $downloadUrl }}" style="color:#0891b2;">{{ $downloadUrl }}</a><br>
                    Verify: <a href="{{ $verifyUrl }}" style="color:#0891b2;">{{ $verifyUrl }}</a>
                    @if ($isDormant && $claimUrl)
                        <br>Claim: <a href="{{ $claimUrl }}" style="color:#0891b2;">{{ $claimUrl }}</a>
                    @endif
                </p>
            </td>
        </tr>

        {{-- Footer --}}
        <tr>
            <td style="padding:24px 32px 28px;">
                <p style="margin:0;font-size:13px;line-height:1.65;color:#64748b;">
                    Please keep this email for your records. You may use the verification link anytime to confirm the authenticity of your certificate.
                </p>
                <p style="margin:8px 0 0;font-size:12px;line-height:1.65;color:#94a3b8;">
                    This is an automated message from DOST Caraga CERTiFY. Please do not reply to this email.
                    If you have questions, contact DOST Caraga at ord@caraga.dost.gov.ph.
                </p>
            </td>
        </tr>

    </table>
</body>
</html>
