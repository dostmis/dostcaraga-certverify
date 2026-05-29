<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'DOST Verifier') }}</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}?v=3">
    <link href="https://fonts.bunny.net/css?family=lexend:300,400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            color-scheme: light;
        }

        body.auth-guest {
            margin: 0;
            min-height: 100vh;
            font-family: "Lexend", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #0F172A;
            background-color: #F8FAFC;
            overflow-x: hidden;
        }

        /* ── Aurora mesh gradient orbs ── */
        .aurora-bg {
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
        }

        .aurora-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(120px);
            opacity: 0.50;
            animation: auroraFloat 22s ease-in-out infinite;
        }

        .aurora-orb--1 {
            width: 640px; height: 640px;
            background: radial-gradient(circle, rgba(14, 116, 144, 0.40), transparent 70%);
            top: -18%; left: -12%;
            animation-delay: 0s;
        }

        .aurora-orb--2 {
            width: 520px; height: 520px;
            background: radial-gradient(circle, rgba(37, 99, 235, 0.30), transparent 70%);
            top: 38%; right: -14%;
            animation-delay: -7s;
        }

        .aurora-orb--3 {
            width: 460px; height: 460px;
            background: radial-gradient(circle, rgba(202, 138, 4, 0.14), transparent 70%);
            bottom: -12%; left: 22%;
            animation-delay: -14s;
        }

        .aurora-orb--4 {
            width: 380px; height: 380px;
            background: radial-gradient(circle, rgba(6, 182, 212, 0.24), transparent 70%);
            top: 58%; left: 52%;
            animation-delay: -3s;
        }

        .aurora-orb--5 {
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.18), transparent 70%);
            bottom: 25%; right: 30%;
            animation-delay: -10s;
        }

        @keyframes auroraFloat {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25%  { transform: translate(50px, -35px) scale(1.10); }
            50%  { transform: translate(-25px, 25px) scale(0.94); }
            75%  { transform: translate(35px, 15px) scale(1.06); }
        }

        /* ── Layout ── */
        .auth-shell {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem 1rem;
        }

        /* ── Glassmorphic card ── */
        .auth-card-half {
            position: relative;
            width: min(100%, 28rem);
            margin-top: 1.5rem;
            padding: 1.75rem 1.5rem;
            background: rgba(255, 255, 255, 0.86);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.55);
            border-radius: 1.25rem;
            box-shadow:
                0 4px 24px rgba(15, 23, 42, 0.07),
                0 16px 48px rgba(15, 23, 42, 0.10);
            overflow: hidden;
        }

        /* Gold-teal shimmer line at top of card */
        .auth-card-half::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg,
                transparent 0%,
                rgba(202, 138, 4, 0.45) 20%,
                rgba(14, 116, 144, 0.45) 50%,
                rgba(202, 138, 4, 0.45) 80%,
                transparent 100%);
        }

        /* ── Shared input styles ── */
        .auth-guest input[type="text"],
        .auth-guest input[type="email"],
        .auth-guest input[type="password"],
        .auth-guest select,
        .auth-guest textarea {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            padding: 0.70rem 0.875rem;
            border: 1.5px solid #E2E8F0;
            border-radius: 0.75rem;
            background: rgba(255, 255, 255, 0.92);
            color: #0F172A;
            font-family: inherit;
            font-size: 0.9375rem;
            line-height: 1.5;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }

        .auth-guest input[type="text"]:hover,
        .auth-guest input[type="email"]:hover,
        .auth-guest input[type="password"]:hover,
        .auth-guest select:hover,
        .auth-guest textarea:hover {
            border-color: #CBD5E1;
        }

        .auth-guest input[type="text"]:focus,
        .auth-guest input[type="email"]:focus,
        .auth-guest input[type="password"]:focus,
        .auth-guest select:focus,
        .auth-guest textarea:focus {
            outline: none;
            border-color: #0369A1;
            box-shadow: 0 0 0 4px rgba(3, 105, 161, 0.12);
            background: #fff;
        }

        .auth-guest input[type="checkbox"] {
            width: 1.125rem;
            height: 1.125rem;
            accent-color: #0369A1;
            border-radius: 0.25rem;
            cursor: pointer;
        }

        /* ── Error text ── */
        .auth-guest .input-error {
            font-size: 0.8125rem;
            color: #DC2626;
            margin-top: 0.375rem;
        }

        /* ── Password toggle spacing ── */
        .auth-guest input.pr-10 {
            padding-right: 2.75rem;
        }

        /* ── Utility classes ── */
        .auth-guest ul { margin: 0; padding: 0; list-style: none; }
        .auth-guest a { text-underline-offset: 2px; }
        .auth-guest .block { display: block; }
        .auth-guest .w-full:not(.auth-card-half) { width: 100%; }
        .auth-guest .mt-1 { margin-top: 0.25rem; }
        .auth-guest .mt-2 { margin-top: 0.5rem; }
        .auth-guest .mt-4 { margin-top: 1rem; }
        .auth-guest .mt-5 { margin-top: 1.25rem; }
        .auth-guest .mb-4 { margin-bottom: 1rem; }
        .auth-guest .text-sm { font-size: 0.875rem; line-height: 1.25rem; }
        .auth-guest .font-medium { font-weight: 600; }
        .auth-guest .text-gray-700 { color: #334155; }
        .auth-guest .text-green-600 { color: #16a34a; }
        .auth-guest .text-red-600 { color: #dc2626; }
        .auth-guest .space-y-1 > :not([hidden]) ~ :not([hidden]) { margin-top: 0.25rem; }

        /* ── Reduced motion ── */
        @media (prefers-reduced-motion: reduce) {
            .aurora-orb { animation: none !important; }
            *, *::before, *::after { transition-duration: 0.01ms !important; animation-duration: 0.01ms !important; }
        }

        /* ── Responsive ── */
        @media (min-width: 768px) {
            .auth-card-half {
                width: min(30%, 30rem);
                min-width: 26rem;
                padding: 2.25rem 2rem;
            }
        }
    </style>
</head>
<body class="auth-guest antialiased">

    {{-- Animated aurora background --}}
    <div class="aurora-bg" aria-hidden="true">
        <div class="aurora-orb aurora-orb--1"></div>
        <div class="aurora-orb aurora-orb--2"></div>
        <div class="aurora-orb aurora-orb--3"></div>
        <div class="aurora-orb aurora-orb--4"></div>
        <div class="aurora-orb aurora-orb--5"></div>
    </div>

    <div class="auth-shell">
        <div class="auth-card-half">
            {{ $slot }}
        </div>
    </div>

    <script>
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            if (!input) return;
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            btn.querySelector('svg').innerHTML = show
                ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="m14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>'
                : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
        }
    </script>
</body>
</html>
