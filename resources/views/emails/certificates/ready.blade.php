<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Certificate Ready</title>
</head>
<body style="margin:0;padding:24px;background:#f3f6fb;font-family:Arial,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:720px;margin:0 auto;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #dbe2ea;">
        <tr>
            <td style="padding:32px 36px;">
                <div style="text-align:center;margin-bottom:28px;">
                    <img src="{{ asset('images/dosttt.png') }}" alt="DOST Logo" style="width:74px;height:74px;object-fit:contain;display:block;margin:0 auto 12px;">
                    <div style="font-size:22px;font-weight:800;letter-spacing:0.04em;color:#111827;">DOST CARAGA</div>
                    <div style="margin-top:18px;font-size:30px;font-weight:800;line-height:1.2;color:#0f172a;">
                        &#127881; Your Certificate is Ready!
                    </div>
                </div>

                <p style="margin:0 0 18px;font-size:18px;line-height:1.7;">
                    Hello {{ $certificate->participant_name }},
                </p>

                <p style="margin:0 0 18px;font-size:16px;line-height:1.8;color:#334155;">
                    Congratulations! We are pleased to inform you that your
                    <strong>{{ $certificate->certificate_type ?? 'Certificate' }}</strong>
                    for <strong>{{ $certificate->training_title }}</strong> is now available.
                </p>

                <p style="margin:0 0 24px;font-size:16px;line-height:1.8;color:#334155;">
                    Thank you for your participation and contribution. Your involvement plays an important role in supporting the initiatives and programs of DOST Caraga.
                </p>

                <h2 style="margin:0 0 14px;font-size:19px;color:#111827;">Certificate Details</h2>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 28px;background:#f8fafc;border:1px solid #dbe2ea;border-radius:14px;">
                    <tr>
                        <td style="padding:18px 22px;">
                            <p style="margin:0 0 10px;font-size:15px;line-height:1.7;color:#334155;"><strong>Recipient Type:</strong> {{ $certificate->recipient_type ?: 'N/A' }}</p>
                            <p style="margin:0 0 10px;font-size:15px;line-height:1.7;color:#334155;"><strong>Activity:</strong> {{ $certificate->activity_type ?: 'N/A' }}</p>
                            <p style="margin:0 0 10px;font-size:15px;line-height:1.7;color:#334155;">
                                <strong>Schedule:</strong>
                                @if ($certificate->training_date && $certificate->training_date_to && $certificate->training_date->format('F j, Y') !== $certificate->training_date_to->format('F j, Y'))
                                    {{ $certificate->training_date->format('F j, Y') }} &ndash; {{ $certificate->training_date_to->format('F j, Y') }}
                                @elseif ($certificate->training_date)
                                    {{ $certificate->training_date->format('F j, Y') }}
                                @else
                                    N/A
                                @endif
                            </p>
                            <p style="margin:0;font-size:15px;line-height:1.7;color:#334155;"><strong>Venue:</strong> {{ $certificate->venue ?: 'N/A' }}</p>
                        </td>
                    </tr>
                </table>

                <h2 style="margin:0 0 14px;font-size:19px;color:#111827;">Access Your Certificate</h2>

                <p style="margin:0 0 10px;font-size:16px;line-height:1.8;color:#334155;">
                    A PDF copy of your certificate is attached to this email for your records.
                </p>
                <p style="margin:0 0 22px;font-size:16px;line-height:1.8;color:#334155;">
                    You may also download or verify your certificate using the options below.
                </p>

                <div style="margin:0 0 26px;">
                    <a href="{{ $downloadUrl }}" style="display:inline-block;margin:0 12px 12px 0;padding:13px 20px;border-radius:10px;background:#111827;color:#ffffff;text-decoration:none;font-weight:700;">
                        Download Certificate PDF
                    </a>
                    <a href="{{ $verifyUrl }}" style="display:inline-block;margin:0 0 12px;padding:13px 20px;border-radius:10px;background:#0b57d0;color:#ffffff;text-decoration:none;font-weight:700;">
                        Verify Certificate
                    </a>
                </div>

                <h3 style="margin:0 0 8px;font-size:16px;color:#111827;">Download Link</h3>
                <p style="margin:0 0 18px;font-size:14px;line-height:1.8;word-break:break-all;color:#1f2937;">
                    <a href="{{ $downloadUrl }}" style="color:#0b57d0;">{{ $downloadUrl }}</a>
                </p>

                <h3 style="margin:0 0 8px;font-size:16px;color:#111827;">Verification Link</h3>
                <p style="margin:0 0 28px;font-size:14px;line-height:1.8;word-break:break-all;color:#1f2937;">
                    <a href="{{ $verifyUrl }}" style="color:#0b57d0;">{{ $verifyUrl }}</a>
                </p>

                <h2 style="margin:0 0 14px;font-size:19px;color:#111827;">Keep This Email</h2>

                <p style="margin:0 0 16px;font-size:16px;line-height:1.8;color:#334155;">
                    Please keep this email for your reference. You may use the verification link anytime to confirm the authenticity of your certificate.
                </p>

                <p style="margin:0 0 16px;font-size:16px;line-height:1.8;color:#334155;">
                    If you have any questions, feel free to contact DOST Caraga.
                </p>

                <p style="margin:0;font-size:13px;line-height:1.8;color:#64748b;">
                    This is an automated message from DOST CERTIFY.<br>
                    Please do not reply to this email.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
