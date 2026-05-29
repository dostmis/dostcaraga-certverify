<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Change Password — DOST Caraga CERTiFY</title>
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

        .profile-shell {
            width: 100%;
            max-width: 640px;
            margin: 32px auto;
            padding: 0 16px;
        }

        .profile-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
        }

        .profile-header h1 {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin: 0;
        }

        .profile-tabs {
            display: flex;
            gap: 4px;
            background: #e2e8f0;
            border-radius: 10px;
            padding: 4px;
        }

        .profile-tab {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
            font-family: inherit;
        }

        .profile-tab.active {
            background: #fff;
            color: #0f172a;
            box-shadow: 0 1px 3px rgba(15,23,42,0.10);
        }

        .profile-tab:not(.active) {
            background: transparent;
            color: #64748b;
        }

        .profile-tab:not(.active):hover {
            color: #334155;
        }

        .profile-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(15,23,42,0.04);
            overflow: hidden;
        }

        .profile-card-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
        }

        .profile-card-header h2 {
            font-size: 16px;
            font-weight: 800;
            margin: 0;
            letter-spacing: -0.01em;
        }

        .profile-card-body {
            padding: 24px;
        }

        .field-label {
            display: block;
            margin-bottom: 4px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            color: #475569;
        }

        .field-input {
            width: 100%;
            border-radius: 10px;
            border: 1.5px solid #e2e8f0;
            background: #f8fafc;
            padding: 10px 12px;
            font-size: 14px;
            font-weight: 500;
            color: #0f172a;
            font-family: inherit;
            transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
            box-sizing: border-box;
        }

        .field-input:focus {
            outline: none;
            border-color: #0891B2;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(8,145,178,0.12);
        }

        .form-group {
            margin-bottom: 18px;
        }

        .save-bar {
            display: flex;
            justify-content: flex-end;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .btn-save {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 24px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            border: none;
            background: #0f172a;
            color: #fff;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(15,23,42,0.12);
        }

        .btn-save:hover {
            background: #1e293b;
            box-shadow: 0 4px 16px rgba(15,23,42,0.20);
            transform: translateY(-1px);
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

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 12px 18px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .alert-error ul {
            margin: 6px 0 0;
            padding-left: 20px;
        }

        .hint {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 4px;
        }

        @media (max-width: 640px) {
            .profile-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .profile-card-body {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    @include('recipient.partials.navigation')

    <div class="profile-shell">
        <div class="profile-header">
            <h1>Profile Settings</h1>
            <div class="profile-tabs">
                <a href="{{ route('recipient.profile.edit') }}" class="profile-tab">Edit Profile</a>
                <a href="{{ route('recipient.profile.password') }}" class="profile-tab active">Password</a>
            </div>
        </div>

        @if (session('success'))
            <div class="alert-success">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" style="width:18px;height:18px;flex-shrink:0;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert-error">
                <strong>Please fix the following:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('recipient.profile.password.update') }}">
            @csrf
            @method('PUT')

            <div class="profile-card">
                <div class="profile-card-header">
                    <h2>Change Password</h2>
                </div>
                <div class="profile-card-body">
                    <div class="form-group">
                        <label class="field-label">Current Password</label>
                        <input name="current_password" type="password" class="field-input" required
                               placeholder="Enter your current password" autocomplete="current-password">
                    </div>
                    <div class="form-group">
                        <label class="field-label">New Password</label>
                        <input name="password" type="password" class="field-input" required
                               placeholder="Enter new password" autocomplete="new-password">
                        <p class="hint">Must be at least 8 characters.</p>
                    </div>
                    <div class="form-group">
                        <label class="field-label">Confirm New Password</label>
                        <input name="password_confirmation" type="password" class="field-input" required
                               placeholder="Re-enter new password" autocomplete="new-password">
                    </div>
                </div>
            </div>

            <div class="save-bar" style="margin-top:16px;">
                <button type="submit" class="btn-save">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" style="width:16px;height:16px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    Update Password
                </button>
            </div>
        </form>
    </div>
</body>
</html>
