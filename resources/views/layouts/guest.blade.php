<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'DOST Verifier') }}</title>

        <!-- Fonts -->
        <link rel="icon" href="{{ asset('favicon.ico') }}?v=3">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            :root {
                color-scheme: light;
            }

            body.auth-guest {
                margin: 0;
                min-height: 100vh;
                font-family: "Figtree", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                color: #111827;
                background:
                    radial-gradient(circle at top, rgba(14, 116, 144, 0.10), transparent 32%),
                    linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
            }

            .auth-shell {
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 1.5rem 1rem;
            }

            .auth-card-half {
                width: min(100%, 28rem);
                margin-top: 1.5rem;
                padding: 1.5rem;
                background: #fff;
                border: 1px solid rgba(148, 163, 184, 0.28);
                border-radius: 1rem;
                box-shadow: 0 18px 45px rgba(15, 23, 42, 0.12);
                overflow: hidden;
            }

            .auth-guest ul {
                margin: 0;
                padding: 0;
                list-style: none;
            }

            .auth-guest a {
                text-underline-offset: 2px;
            }

            .auth-guest .block {
                display: block;
            }

            .auth-guest .w-full:not(.auth-card-half) {
                width: 100%;
            }

            .auth-guest .mt-1 {
                margin-top: 0.25rem;
            }

            .auth-guest .mt-2 {
                margin-top: 0.5rem;
            }

            .auth-guest .mt-4 {
                margin-top: 1rem;
            }

            .auth-guest .mt-5 {
                margin-top: 1.25rem;
            }

            .auth-guest .mb-4 {
                margin-bottom: 1rem;
            }

            .auth-guest .text-sm {
                font-size: 0.875rem;
                line-height: 1.25rem;
            }

            .auth-guest .font-medium {
                font-weight: 600;
            }

            .auth-guest .text-gray-700 {
                color: #374151;
            }

            .auth-guest .text-green-600 {
                color: #16a34a;
            }

            .auth-guest .text-red-600 {
                color: #dc2626;
            }

            .auth-guest .space-y-1 > :not([hidden]) ~ :not([hidden]) {
                margin-top: 0.25rem;
            }

            .auth-guest input[type="text"],
            .auth-guest input[type="email"],
            .auth-guest input[type="password"],
            .auth-guest select,
            .auth-guest textarea {
                display: block;
                width: 100%;
                margin-top: 0.25rem;
                padding: 0.75rem 0.875rem;
                border: 1px solid #d1d5db;
                border-radius: 0.5rem;
                background: #fff;
                color: #111827;
                box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
                transition: border-color 0.2s ease, box-shadow 0.2s ease;
            }

            .auth-guest input[type="checkbox"] {
                width: 1rem;
                height: 1rem;
                accent-color: #111827;
            }

            .auth-guest input[type="text"]:focus,
            .auth-guest input[type="email"]:focus,
            .auth-guest input[type="password"]:focus,
            .auth-guest select:focus,
            .auth-guest textarea:focus {
                outline: none;
                border-color: #6366f1;
                box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.16);
            }

            @media (min-width: 768px) {
                .auth-card-half {
                    width: min(30%, 30rem);
                    min-width: 26rem;
                }
            }
        </style>
    </head>
    <body class="auth-guest font-sans text-gray-900 antialiased">
        <div class="auth-shell min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
            

            <div class="auth-card-half mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
