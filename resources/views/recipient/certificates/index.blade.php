<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Certificates — DOST Caraga CERTiFY</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root { color-scheme: light; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Figtree", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
            color: #0f172a;
            -webkit-font-smoothing: antialiased;
        }

        .cert-shell {
            width: 100%;
            margin: 32px auto;
            max-width: 1000px;
            padding: 0 16px;
        }

        .cert-hero {
            position: relative;
            padding: 28px 32px;
            border-radius: 20px;
            background:
                radial-gradient(400px 180px at 0% 0%, rgba(255,255,255,0.18), transparent 72%),
                radial-gradient(320px 150px at 100% 14%, rgba(34,211,238,0.18), transparent 80%),
                linear-gradient(120deg, #0e4d7a 0%, #0e74ab 56%, #0891B2 100%);
            color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            box-shadow: 0 18px 48px rgba(8, 145, 178, 0.18);
        }

        .cert-hero-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .cert-hero-left img {
            width: 44px;
            height: 44px;
            object-fit: contain;
        }

        .cert-hero h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.01em;
            line-height: 1.2;
        }

        .cert-hero p {
            margin: 3px 0 0;
            font-size: 13px;
            opacity: 0.85;
            font-weight: 500;
        }

        .cert-stat {
            text-align: center;
            min-width: 72px;
        }

        .cert-stat-number {
            font-size: 28px;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.02em;
        }

        .cert-stat-label {
            font-size: 11px;
            font-weight: 600;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-top: 4px;
        }

        .cert-list {
            margin-top: 24px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .cert-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 22px 26px;
            box-shadow: 0 2px 8px rgba(15,23,42,0.04);
            transition: border-color 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
            cursor: default;
        }

        .cert-card:hover {
            border-color: #0891B2;
            box-shadow: 0 6px 24px rgba(8,145,178,0.10);
        }

        .cert-card-body {
            flex: 1;
            min-width: 200px;
        }

        .cert-card-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #ecfeff;
            color: #0e7490;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 4px 10px;
            border-radius: 6px;
            margin-bottom: 8px;
        }

        .cert-card-badge svg {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
        }

        .cert-card-code {
            font-size: 12px;
            color: #94a3b8;
            font-weight: 500;
            letter-spacing: 0.02em;
        }

        .cert-card-title {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 10px;
            line-height: 1.35;
        }

        .cert-card-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            font-size: 13px;
            color: #475569;
        }

        .cert-card-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .cert-card-meta-item svg {
            width: 15px;
            height: 15px;
            color: #94a3b8;
            flex-shrink: 0;
        }

        .cert-card-actions {
            display: flex;
            gap: 10px;
            flex-shrink: 0;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            background: #0f172a;
            color: #fff;
        }

        .btn-primary:hover {
            background: #1e293b;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(15,23,42,0.2);
        }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            background: #fff;
            color: #475569;
            border: 1px solid #d1d5db;
        }

        .btn-secondary:hover {
            border-color: #0891B2;
            color: #0891B2;
            background: #f0fdff;
        }

        .btn-tertiary {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            background: #f8fafc;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .btn-tertiary:hover {
            border-color: #94a3b8;
            color: #334155;
            background: #f1f5f9;
        }

        .empty-state {
            text-align: center;
            padding: 56px 24px;
            background: #fff;
            border: 2px dashed #d1d5db;
            border-radius: 20px;
            margin-top: 24px;
        }

        .empty-state svg {
            width: 56px;
            height: 56px;
            color: #cbd5e1;
            margin-bottom: 16px;
        }

        .empty-state h3 {
            font-size: 17px;
            font-weight: 700;
            color: #334155;
            margin: 0 0 8px;
        }

        .empty-state p {
            font-size: 14px;
            color: #64748b;
            line-height: 1.6;
            max-width: 400px;
            margin: 0 auto;
        }

        .alert-success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            padding: 12px 18px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pagination-nav {
            margin-top: 24px;
            display: flex;
            justify-content: center;
        }

        @media (max-width: 640px) {
            .cert-hero {
                padding: 20px;
                flex-direction: column;
                text-align: center;
            }

            .cert-hero-left {
                flex-direction: column;
                text-align: center;
            }

            .cert-stat {
                min-width: auto;
            }

            .cert-card {
                padding: 16px 18px;
            }

            .cert-card-actions {
                width: 100%;
            }

            .cert-card-actions a {
                flex: 1;
                justify-content: center;
            }

            .cert-card-meta {
                flex-direction: column;
                gap: 6px;
            }
        }
    </style>
</head>
<body>
    @include('recipient.partials.navigation')

    <div class="cert-shell">
        <div class="cert-hero">
            <div class="cert-hero-left">
                <img src="{{ asset('images/dosttt.png') }}" alt="DOST Logo">
                <div>
                    <h1>My Certificates</h1>
                    <p>Welcome back, {{ $recipient->name }}</p>
                </div>
            </div>
            <div class="cert-stat">
                <div class="cert-stat-number">{{ $certificates->total() }}</div>
                <div class="cert-stat-label">{{ Str::plural('Certificate', $certificates->total()) }}</div>
            </div>
        </div>

        @if (session('success'))
            <div style="margin-top:20px;">
                <div class="alert-success">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" style="width:18px;height:18px;flex-shrink:0;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ session('success') }}
                </div>
            </div>
        @endif

        @if ($certificates->isEmpty())
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="width:56px;height:56px;margin-bottom:16px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <h3>No certificates yet</h3>
                <p>
                    When a certificate is issued in your name, it will appear here.
                    You'll receive an email with a PDF copy and a link to view it online.
                </p>
            </div>
        @else
            <div class="cert-list">
                @foreach ($certificates as $cert)
                    <div class="cert-card">
                        <div class="cert-card-body">
                            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:4px;">
                                <span class="cert-card-badge">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    {{ $cert->certificate_type ?? 'Certificate' }}
                                </span>
                                <span class="cert-card-code">{{ $cert->certificate_code }}</span>
                            </div>

                            <h3 class="cert-card-title">{{ $cert->training_title }}</h3>

                            <div class="cert-card-meta">
                                <span class="cert-card-meta-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    {{ $cert->recipient_type ?? 'N/A' }}
                                </span>
                                <span class="cert-card-meta-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    @if ($cert->training_date)
                                        {{ $cert->training_date->format('M j, Y') }}
                                        @if ($cert->training_date_to && $cert->training_date->format('Y-m-d') !== $cert->training_date_to->format('Y-m-d'))
                                            &ndash; {{ $cert->training_date_to->format('M j, Y') }}
                                        @endif
                                    @else
                                        N/A
                                    @endif
                                </span>
                                <span class="cert-card-meta-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    {{ $cert->venue ?? 'N/A' }}
                                </span>
                            </div>
                        </div>

                        <div class="cert-card-actions">
                            <a href="{{ route('cert.preview', ['t' => $cert->public_token]) }}" target="_blank" class="btn-tertiary">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" style="width:15px;height:15px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                Preview
                            </a>
                            <a href="{{ route('cert.download', ['t' => $cert->public_token]) }}" class="btn-primary">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" style="width:15px;height:15px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                Download
                            </a>
                            <a href="{{ route('cert.verify', ['t' => $cert->public_token]) }}" class="btn-secondary">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" style="width:15px;height:15px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Verify
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($certificates->hasPages())
                <div class="pagination-nav">
                    {{ $certificates->links() }}
                </div>
            @endif
        @endif
    </div>
</body>
</html>
