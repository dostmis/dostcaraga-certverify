@php
  $menuUser = auth()->user();
  $menuId = $menuId ?? 'admin-action-menu';
  $menuVariant = $menuVariant ?? 'light';
  $menuIsRegionalDirector = $menuUser && $menuUser->isRegionalDirector();
  $menuRole = (string) ($menuUser?->role ?? '');
  $menuCanViewAnalytics = $menuIsRegionalDirector || $menuRole === \App\Models\User::ROLE_ORGANIZER;
  $menuCanCreateCertificates = $menuIsRegionalDirector || in_array($menuRole, \App\Models\User::endorserRoles(), true);
  $menuCanViewIntakes = $menuIsRegionalDirector || in_array($menuRole, [
    \App\Models\User::ROLE_UNIT_SUPERVISOR,
    \App\Models\User::ROLE_ORGANIZER,
  ], true);
  $menuPendingEndorsementsCount = $pendingEndorsementsCount ?? null;
  $showAnalyticsExports = (bool) ($showAnalyticsExports ?? false);
  $showIntakeActions = (bool) ($showIntakeActions ?? false);
  $showIntakeToggle = $showIntakeActions && $menuIsRegionalDirector && isset($intakeEnabled);
  $showIntakeExport = $showIntakeActions && $menuIsRegionalDirector;
  $showActiveIntakeForm = $showIntakeActions && !$menuIsRegionalDirector && !empty($activeEventUrl);
@endphp

@once
  <style>
    [x-cloak] {
      display: none !important;
    }

    .admin-action-menu {
      position: relative;
      display: inline-flex;
      justify-content: flex-end;
      z-index: 60;
    }

    .admin-action-menu-trigger {
      min-width: 112px;
      min-height: 42px;
      border: 1px solid #cbd5e1;
      border-radius: 12px;
      padding: 10px 14px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 9px;
      background: #fff;
      color: #334155;
      font-size: 13px;
      font-weight: 900;
      line-height: 1;
      cursor: pointer;
    }

    .admin-action-menu-dark .admin-action-menu-trigger {
      border-color: rgba(255, 255, 255, 0.48);
      background: rgba(255, 255, 255, 0.14);
      color: #fff;
      backdrop-filter: blur(8px);
    }

    .admin-action-menu-trigger:hover,
    .admin-action-menu-trigger[aria-expanded="true"] {
      background: #f8fafc;
    }

    .admin-action-menu-dark .admin-action-menu-trigger:hover,
    .admin-action-menu-dark .admin-action-menu-trigger[aria-expanded="true"] {
      background: rgba(255, 255, 255, 0.24);
    }

    .admin-action-menu-icon {
      width: 18px;
      height: 18px;
      flex: 0 0 18px;
    }

    .admin-action-menu-panel {
      position: absolute;
      top: calc(100% + 10px);
      right: 0;
      width: min(310px, calc(100vw - 32px));
      border: 1px solid #dbe5ef;
      border-radius: 14px;
      background: #fff;
      color: #0f172a;
      box-shadow: 0 22px 55px rgba(15, 23, 42, 0.22);
      padding: 8px;
      z-index: 80;
    }

    .admin-action-menu-item,
    .admin-action-menu-form button {
      width: 100%;
      min-height: 42px;
      border: 0;
      border-radius: 10px;
      padding: 10px 12px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      background: transparent;
      color: #334155;
      text-decoration: none;
      font-size: 13px;
      font-weight: 800;
      line-height: 1.2;
      text-align: left;
      cursor: pointer;
    }

    .admin-action-menu-item:hover,
    .admin-action-menu-form button:hover {
      background: #f1f5f9;
      color: #0f172a;
    }

    .admin-action-menu-primary {
      background: #eff6ff;
      color: #1e40af;
    }

    .admin-action-menu-success {
      color: #047857;
    }

    .admin-action-menu-danger,
    .admin-action-menu-form button.admin-action-menu-danger {
      color: #b91c1c;
    }

    .admin-action-menu-count {
      min-width: 28px;
      border-radius: 999px;
      padding: 3px 8px;
      background: #e0f2fe;
      color: #075985;
      font-size: 12px;
      font-weight: 900;
      text-align: center;
    }

    .admin-action-menu-separator {
      height: 1px;
      margin: 6px 4px;
      background: #e2e8f0;
    }

    @media (max-width: 640px) {
      .admin-action-menu-trigger {
        min-width: 44px;
        width: 44px;
        height: 44px;
        padding: 0;
      }

      .admin-action-menu-panel {
        width: min(320px, calc(100vw - 32px));
        max-width: calc(100vw - 32px);
      }

      .admin-action-menu-label {
        display: none;
      }
    }
  </style>
@endonce

<div class="admin-action-menu admin-action-menu-{{ $menuVariant }}" x-data="{ open: false }" @keydown.escape.window="open = false">
  <button
    type="button"
    class="admin-action-menu-trigger"
    @click="open = ! open"
    :aria-expanded="open.toString()"
    aria-controls="{{ $menuId }}"
    aria-label="Open admin menu"
  >
    <svg class="admin-action-menu-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
      <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"></path>
    </svg>
    <span class="admin-action-menu-label">Menu</span>
  </button>

  <div
    id="{{ $menuId }}"
    class="admin-action-menu-panel"
    x-cloak
    x-show="open"
    x-transition.origin.top.right
    @click.outside="open = false"
  >
    <a href="{{ route('admin.certs.index') }}" class="admin-action-menu-item" @click="open = false">Issued Certificates</a>

    @if (! empty($menuRecipient))
      <a href="{{ route('recipient.certificates') }}" class="admin-action-menu-item" @click="open = false">My Certificates</a>
    @endif

    @if ($menuCanViewAnalytics)
      <a href="{{ route('admin.analytics.index') }}" class="admin-action-menu-item" @click="open = false">Analytics</a>
    @endif

    @if ($menuCanViewIntakes)
      <a href="{{ route('admin.participant-intakes.index') }}" class="admin-action-menu-item" @click="open = false">Intakes</a>
    @endif

    @if ($menuIsRegionalDirector)
      <a href="{{ route('admin.certs.approvals') }}" class="admin-action-menu-item" @click="open = false">
        <span>Endorsed Queue</span>
        @if ($menuPendingEndorsementsCount !== null)
          <span class="admin-action-menu-count">{{ number_format((int) $menuPendingEndorsementsCount) }}</span>
        @endif
      </a>
    @endif

    @if ($menuCanCreateCertificates)
      <a href="{{ route('admin.certs.create') }}" class="admin-action-menu-item admin-action-menu-primary" @click="open = false">
        {{ $menuIsRegionalDirector ? '+ Create (RD)' : '+ Create & Endorse' }}
      </a>
    @endif

    @if ($menuIsRegionalDirector)
      <div class="admin-action-menu-separator"></div>
      <a href="{{ route('admin.users.index') }}" class="admin-action-menu-item" @click="open = false">Users</a>
      <a href="{{ route('admin.account-settings.edit') }}" class="admin-action-menu-item" @click="open = false">Account Settings</a>
    @endif

    @if ($showAnalyticsExports)
      <div class="admin-action-menu-separator"></div>
      <a href="{{ route('admin.analytics.export', array_merge(request()->all(), ['format' => 'csv'])) }}" class="admin-action-menu-item" @click="open = false">Export Analytics CSV</a>
      <a href="{{ route('admin.analytics.export', array_merge(request()->all(), ['format' => 'xlsx'])) }}" class="admin-action-menu-item admin-action-menu-primary" @click="open = false">Export Analytics XLSX</a>
    @endif

    @if ($showIntakeToggle || $showActiveIntakeForm || $showIntakeExport)
      <div class="admin-action-menu-separator"></div>
    @endif

    @if ($showIntakeToggle)
      <form method="POST" action="{{ route('admin.participant-intakes.toggle') }}" class="admin-action-menu-form">
        @csrf
        <input type="hidden" name="enabled" value="{{ $intakeEnabled ? '0' : '1' }}">
        <button type="submit" class="{{ $intakeEnabled ? 'admin-action-menu-success' : 'admin-action-menu-danger' }}">
          {{ $intakeEnabled ? 'Turn Intake Off' : 'Turn Intake On' }}
        </button>
      </form>
    @endif

    @if ($showActiveIntakeForm)
      <a href="{{ $activeEventUrl }}" class="admin-action-menu-item" target="_blank" rel="noopener" @click="open = false">Open Intake Form</a>
    @endif

    @if ($showIntakeExport)
      <a href="{{ route('admin.participant-intakes.export', array_merge(request()->all(), ['format' => 'csv'])) }}" class="admin-action-menu-item" @click="open = false">Export Intakes CSV</a>
    @endif

    <div class="admin-action-menu-separator"></div>
    <form method="POST" action="{{ route('logout') }}" class="admin-action-menu-form">
      @csrf
      <button type="submit" class="admin-action-menu-danger">Logout</button>
    </form>
  </div>
</div>
