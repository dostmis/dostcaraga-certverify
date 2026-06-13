<x-public-layout>
  <style>
    .intake-shell {
      width: 100%;
      max-width: 980px;
    }

    .intake-card {
      position: relative;
      overflow: hidden;
      border-radius: 24px;
      border: 1px solid #d7e2ee;
      background: linear-gradient(180deg, #f7fbff 0%, #ffffff 46%);
      box-shadow: 0 18px 45px rgba(14, 42, 71, 0.12);
    }

    .intake-card::before {
      content: "";
      position: absolute;
      top: -100px;
      right: -70px;
      width: 280px;
      height: 280px;
      border-radius: 999px;
      background: radial-gradient(circle at center, rgba(14, 165, 160, 0.2) 0%, rgba(14, 165, 160, 0) 70%);
      pointer-events: none;
    }

    .intake-header {
      position: relative;
      border-bottom: 1px solid rgba(255, 255, 255, 0.28);
      background: linear-gradient(130deg, #0f3d66 0%, #0d5f97 52%, #0f9d8f 100%);
      padding: 2rem;
      color: #f8fbff;
    }

    .intake-brand {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 0.9rem;
    }

    .intake-brand-main {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.35rem;
      min-width: 0;
      text-align: center;
    }

    .intake-logo-wrap {
      flex: 0 0 auto;
      width: clamp(220px, 36vw, 460px);
      height: auto;
    }

    .intake-logo {
      width: auto;
      max-width: 100%;
      height: auto;
      max-height: 120px;
      display: block;
      object-fit: contain;
      margin: 0 auto;
    }

    .intake-kicker {
      display: inline-flex;
      align-items: center;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.18);
      padding: 0.25rem 0.65rem;
      font-size: 0.7rem;
      font-weight: 800;
      letter-spacing: 0.09em;
      text-transform: uppercase;
    }

    .intake-headline {
      margin-top: 0.45rem;
      font-size: clamp(1.45rem, 2.2vw, 2rem);
      font-weight: 900;
      line-height: 1.12;
      letter-spacing: -0.02em;
    }

    .intake-subtitle {
      margin-top: 0.7rem;
      max-width: 52rem;
      font-size: 0.95rem;
      color: #d9ecff;
    }

    .intake-trust {
      display: inline-flex;
      align-items: center;
      border-radius: 999px;
      border: 1px solid rgba(255, 255, 255, 0.35);
      background: rgba(5, 33, 60, 0.38);
      padding: 0.35rem 0.72rem;
      font-size: 0.72rem;
      font-weight: 800;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: #ecf6ff;
      white-space: nowrap;
      align-self: center;
    }

    .toast-backdrop {
      position: fixed;
      inset: 0;
      z-index: 9998;
      background: rgba(15, 23, 42, 0.45);
      backdrop-filter: blur(4px);
      -webkit-backdrop-filter: blur(4px);
      animation: fadeIn 0.25s ease;
    }

    .toast-backdrop.fade-out {
      animation: fadeOut 0.2s ease forwards;
    }

    .toast-container {
      position: fixed;
      inset: 0;
      z-index: 9999;
      display: flex;
      align-items: center;
      justify-content: center;
      pointer-events: none;
    }

    .toast {
      pointer-events: auto;
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 18px 20px;
      border-radius: 18px;
      backdrop-filter: blur(24px);
      -webkit-backdrop-filter: blur(24px);
      box-shadow: 0 20px 60px rgba(15, 23, 42, 0.30);
      animation: toastPop 0.35s cubic-bezier(0.16, 1, 0.3, 1);
      font-size: 0.92rem;
      line-height: 1.55;
      font-weight: 600;
      max-width: min(92vw, 480px);
      width: 100%;
    }

    .toast.toast-out {
      animation: toastPopOut 0.2s ease forwards;
    }

    .toast-success {
      background: rgba(236, 253, 245, 0.97);
      border: 1.5px solid #86efac;
      color: #065f46;
    }

    .toast-error {
      background: rgba(254, 242, 242, 0.97);
      border: 1.5px solid #fca5a5;
      color: #991b1b;
    }

    .toast-icon {
      flex-shrink: 0;
      width: 24px;
      height: 24px;
      margin-top: 1px;
    }

    .toast-body {
      flex: 1;
      min-width: 0;
    }

    .toast-body ul {
      margin: 8px 0 0;
      padding-left: 18px;
      font-weight: 500;
    }

    .toast-close {
      flex-shrink: 0;
      background: none;
      border: none;
      cursor: pointer;
      color: inherit;
      opacity: 0.45;
      padding: 3px;
      border-radius: 8px;
      transition: opacity 0.15s, background 0.15s;
      margin-top: 0;
    }

    .toast-close:hover {
      opacity: 1;
      background: rgba(0,0,0,0.06);
    }

    @keyframes toastPop {
      from { opacity: 0; transform: scale(0.90) translateY(10px); }
      to   { opacity: 1; transform: scale(1) translateY(0); }
    }

    @keyframes toastPopOut {
      from { opacity: 1; transform: scale(1) translateY(0); }
      to   { opacity: 0; transform: scale(0.92) translateY(-8px); }
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to   { opacity: 1; }
    }

    @keyframes fadeOut {
      from { opacity: 1; }
      to   { opacity: 0; }
    }

    .form-area {
      position: relative;
      z-index: 1;
      padding: 1.5rem 1.5rem 2rem;
    }

    .consent-panel {
      border-radius: 18px;
      border: 1px solid #cfe3f4;
      background: linear-gradient(180deg, #f8fbff 0%, #eef6ff 100%);
      padding: 1.25rem;
    }

    .consent-head {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
    }

    .consent-title {
      font-size: 1.22rem;
      font-weight: 900;
      color: #0b365a;
      letter-spacing: -0.02em;
    }

    .consent-badge {
      border-radius: 999px;
      border: 1px solid #93c5fd;
      background: #dbeafe;
      padding: 0.2rem 0.65rem;
      font-size: 0.72rem;
      font-weight: 800;
      color: #1d4ed8;
      letter-spacing: 0.05em;
      text-transform: uppercase;
    }

    .consent-copy {
      margin-top: 0.95rem;
      max-height: 320px;
      overflow: auto;
      border-radius: 14px;
      border: 1px solid #dae8f5;
      background: #ffffff;
      padding: 1rem;
      font-size: 0.94rem;
      line-height: 1.63;
      color: #334155;
    }

    .consent-copy ul {
      margin: 0.7rem 0 0;
      list-style: none;
      padding-left: 0;
    }

    .consent-copy li {
      position: relative;
      margin-top: 0.5rem;
      padding-left: 1.35rem;
    }

    .consent-copy li::before {
      content: "✓";
      position: absolute;
      left: 0;
      top: 0;
      color: #0f766e;
      font-weight: 900;
    }

    .consent-accept {
      margin-top: 1rem;
      display: flex;
      align-items: flex-start;
      gap: 0.75rem;
      border-radius: 14px;
      border: 1px solid #bfd6eb;
      background: #ffffff;
      padding: 0.9rem;
    }

    .consent-check {
      margin-top: 0.2rem;
      height: 1.05rem;
      width: 1.05rem;
      border-radius: 0.3rem;
      border-color: #94a3b8;
      color: #0f172a;
    }

    .consent-question {
      font-size: 0.95rem;
      font-weight: 800;
      color: #0f2f4a;
      line-height: 1.45;
    }

    .form-stage.is-hidden {
      display: none;
    }

    .form-stage.is-visible {
      display: block;
      animation: rise 280ms ease;
    }

    .form-block {
      margin-top: 1rem;
      border-radius: 16px;
      border: 1px solid #d9e5f1;
      background: #ffffff;
      padding: 1.15rem;
      box-shadow: 0 3px 10px rgba(15, 23, 42, 0.04);
    }

    .form-block-title {
      font-size: 1.03rem;
      font-weight: 900;
      color: #0f172a;
      letter-spacing: -0.02em;
    }

    .form-block-subtitle {
      margin-top: 0.2rem;
      font-size: 0.84rem;
      color: #64748b;
    }

    .field-label {
      display: block;
      margin-bottom: 0.32rem;
      font-size: 0.8rem;
      font-weight: 800;
      letter-spacing: 0.01em;
      text-transform: uppercase;
      color: #334155;
    }

    .field-input {
      width: 100%;
      border-radius: 0.82rem;
      border: 1px solid #cbd5e1;
      background: #f9fcff;
      padding: 0.7rem 0.82rem;
      font-size: 0.93rem;
      font-weight: 600;
      color: #1e293b;
      transition: border-color 160ms ease, box-shadow 160ms ease, background-color 160ms ease;
    }

    .field-input:focus {
      border-color: #0d5f97;
      background: #ffffff;
      box-shadow: 0 0 0 3px rgba(13, 95, 151, 0.16);
      outline: none;
    }

    .field-hint {
      margin-top: 4px;
      font-size: 0.76rem;
      color: #0d5f97;
      font-weight: 600;
      line-height: 1.4;
    }

    .choice-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: flex-start;
    }

    .choice-grid .choice-pill {
      flex: 1 1 100%;
      width: 100%;
      border-radius: 14px;
    }

    .choice-inline {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }

    .choice-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      border: 1px solid #c7d5e4;
      border-radius: 999px;
      background: #f8fbff;
      padding: 7px 12px;
      font-size: 0.86rem;
      font-weight: 700;
      color: #1e293b;
    }

    .choice-pill input {
      width: 15px;
      height: 15px;
      accent-color: #0d5f97;
      margin: 0;
    }

    .submit-wrap {
      margin-top: 1.35rem;
      display: flex;
      justify-content: flex-end;
    }

    .submit-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      border-radius: 999px;
      border: 0;
      background: linear-gradient(130deg, #0c4d80 0%, #0d5f97 52%, #0f9d8f 100%);
      padding: 0.75rem 1.4rem;
      font-size: 0.94rem;
      font-weight: 800;
      color: #ffffff;
      box-shadow: 0 8px 20px rgba(13, 95, 151, 0.35);
      transition: transform 150ms ease, box-shadow 150ms ease, filter 150ms ease;
    }

    .submit-btn:hover {
      transform: translateY(-1px);
      filter: brightness(1.03);
      box-shadow: 0 12px 24px rgba(13, 95, 151, 0.38);
    }

    .search-input-wrap {
      position: relative;
    }

    .search-input-wrap .search-icon {
      position: absolute;
      left: 0.82rem;
      top: 50%;
      transform: translateY(-50%);
      color: #94a3b8;
      pointer-events: none;
    }

    .search-input-wrap .field-input {
      padding-left: 2.4rem;
    }

    .search-results {
      position: absolute;
      z-index: 50;
      left: 0;
      right: 0;
      bottom: 100%;
      margin-bottom: 4px;
      border-radius: 12px;
      border: 1px solid #d7e2ee;
      background: #ffffff;
      box-shadow: 0 -4px 32px rgba(14, 42, 71, 0.18);
      max-height: 260px;
      overflow-y: auto;
    }

    .search-result-item {
      display: flex;
      flex-direction: column;
      gap: 1px;
      padding: 0.7rem 0.9rem;
      cursor: pointer;
      border-bottom: 1px solid #f1f5f9;
      transition: background-color 120ms ease;
    }

    .search-result-item:last-child {
      border-bottom: 0;
    }

    .search-result-item:hover,
    .search-result-item:focus {
      background: #f0f7ff;
    }

    .search-result-name {
      font-size: 0.89rem;
      font-weight: 800;
      color: #0f172a;
    }

    .search-result-email {
      font-size: 0.78rem;
      color: #64748b;
    }

    .search-result-org {
      font-size: 0.76rem;
      color: #0d5f97;
      font-weight: 600;
    }

    .search-result-empty {
      padding: 0.9rem;
      text-align: center;
      font-size: 0.86rem;
      color: #94a3b8;
    }

    .verify-panel {
      border-radius: 12px;
      border: 1px solid #fcd34d;
      background: #fffbeb;
      padding: 0.9rem 1rem;
    }

    .verify-panel-title {
      font-size: 0.85rem;
      font-weight: 800;
      color: #92400e;
    }

    .verify-panel-subtitle {
      margin-top: 0.2rem;
      font-size: 0.8rem;
      color: #a16207;
    }

    .verify-panel .field-input {
      margin-top: 0.5rem;
    }

    .verify-panel-actions {
      margin-top: 0.6rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .verify-btn {
      display: inline-flex;
      align-items: center;
      border-radius: 999px;
      border: 0;
      background: #0d5f97;
      padding: 0.45rem 1rem;
      font-size: 0.82rem;
      font-weight: 800;
      color: #ffffff;
      cursor: pointer;
      transition: background-color 140ms ease;
    }

    .verify-btn:hover {
      background: #0b4d7a;
    }

    .verify-cancel {
      font-size: 0.8rem;
      font-weight: 700;
      color: #64748b;
      cursor: pointer;
      background: none;
      border: none;
    }

    .verify-cancel:hover {
      color: #334155;
    }

    .verify-error {
      margin-top: 0.45rem;
      font-size: 0.78rem;
      font-weight: 700;
      color: #dc2626;
    }

    .returning-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      border-radius: 999px;
      border: 1px solid #a7f3d0;
      background: #ecfdf5;
      padding: 0.2rem 0.65rem;
      font-size: 0.72rem;
      font-weight: 800;
      color: #065f46;
      letter-spacing: 0.05em;
      text-transform: uppercase;
    }

    .section-rise {
      animation: rise 300ms ease;
    }

    @keyframes rise {
      from { opacity: 0; transform: translateY(8px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 640px) {
      .intake-header {
        padding: 1.35rem;
      }

      .intake-brand-main {
        align-items: center;
      }

      .intake-logo-wrap {
        width: clamp(180px, 62vw, 320px);
        height: auto;
      }

      .intake-logo {
        max-height: 78px;
      }

      .intake-trust {
        white-space: normal;
      }

      .form-area {
        padding: 1.1rem 1rem 1.4rem;
      }

      .status-box {
        margin: 1rem 1rem 0;
      }
    }
  </style>
  <div class="intake-shell mx-auto">
    <div class="intake-card">
      <div class="intake-header">
        <div class="intake-brand">
          <div class="intake-brand-main">
            <div class="intake-logo-wrap">
              <img class="intake-logo" src="{{ asset('images/logo2-trimmed.png') }}" alt="DOST CERTiFY logo">
            </div>
            <br>
            <div>
              <h1 class="intake-headline">Participant Registration Form</h1>
              <p class="intake-subtitle">Kindly provide your complete and accurate information.
This will serve as our reference for post-training documentation and processing.
</p>
              @if (!empty($intakeEvent))
                <p class="intake-subtitle" style="margin-top:8px;font-weight:800;color:#f0f9ff;">
                  Event: {{ $intakeEvent->event_name }}
                </p>
              @endif
            </div>
          </div>
          <span class="intake-trust">Official Intake Portal</span>
        </div>
      </div>

      <div class="toast-container" id="toastContainer"></div>

      @php
        $loggedInRecipient = $loggedInRecipient ?? null;
        $hasPrivacyConsent = in_array(old('privacy_consent'), ['1', 1, true, 'true', 'on', 'yes'], true);
        $oldBeneficiaryPrograms = old('dost_program_beneficiary', []);
        if (!is_array($oldBeneficiaryPrograms)) {
          $oldBeneficiaryPrograms = [$oldBeneficiaryPrograms];
        }
        $oldDirectlyEmployedPrograms = old('directly_employed_programs', []);
        if (!is_array($oldDirectlyEmployedPrograms)) {
          $oldDirectlyEmployedPrograms = [$oldDirectlyEmployedPrograms];
        }
        $oldInterestedServices = old('interested_dost_services', []);
        if (!is_array($oldInterestedServices)) {
          $oldInterestedServices = [$oldInterestedServices];
        }
      @endphp

      <form id="participantIntakeForm" method="POST" action="{{ route('participant.intake.submit', ['token' => $intakeEvent->public_token]) }}" class="form-area space-y-5">
        @csrf

        @if (!empty($loggedInRecipient))
          <div class="rounded-lg bg-indigo-50 border border-indigo-200 p-4 text-sm text-indigo-800">
            <p class="font-semibold">Welcome back, {{ $loggedInRecipient->name }}!</p>
            <p class="mt-1">Your details are pre-filled from your CERTiFY account. Any updates you make here will not change your account profile.</p>
          </div>
        @endif

        <section class="consent-panel section-rise">
          <div class="consent-head">
            <h2 class="consent-title">Data Privacy Consent</h2>
            <span class="consent-badge">Required</span>
          </div>
          <div class="consent-copy">
            <p>
              In compliance with the Data Privacy Act (DPA) of 2012, and its Implementing Rules and Regulations (IRR) effective since September 8, 2016, I allow the Department of Science and Technology (DOST) - Caraga to provide me certain services declared in relation to the services I obtained.
            </p>
            <p class="mt-2">As such, I agree and authorize DOST-Caraga to:</p>
            <ul>
              <li>Retain my information for a period of three years from the date after the completion of relevant transactions in accordance with agency regulation.</li>
              <li>Share my information to affiliates and necessary parties for any legitimate business purpose. I am assured that security systems are employed to protect my information.</li>
              <li>Inform me of future customer campaigns and promotional efforts based on the personal information I shared with the agency, if applicable.</li>
            </ul>
            <p class="mt-2">
              I also acknowledge and warrant that I have acquired the consent from all parties relevant to this consent and hold free and harmless and indemnify DOST-Caraga from any complaint, suit, or damages which any party may file or claim in relation to my consent.
            </p>
            <p class="mt-2">
              Should you have questions or concerns about this consent form, please call (085) 226-3831 or email us at ord@caraga.dost.gov.ph.
            </p>
            <p class="mt-2">
              For more information on how DOST-Caraga protects its data, you may visit our Privacy Statement at
              <a href="https://caraga.dost.gov.ph/privacy-policy" target="_blank" rel="noopener noreferrer" class="font-bold text-blue-700 underline">
                caraga.dost.gov.ph/privacy-policy
              </a>.
            </p>
          </div>

          <label class="consent-accept">
            <input id="privacyConsentCheckbox" type="checkbox" name="privacy_consent" value="1" @checked($hasPrivacyConsent) required class="consent-check">
            <span class="consent-question">
              Do you agree that DOST-Caraga will collect, use, disclose, and process personal information necessary for this purpose?
            </span>
          </label>
        </section>

        <div id="intakeFormFields" class="form-stage {{ $hasPrivacyConsent ? 'is-visible' : 'is-hidden' }}">
          <section class="form-block section-rise">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <h3 class="form-block-title">Are you a Returning Participant?</h3>
              <span class="returning-badge">Time Saver</span>
            </div>
            <p class="form-block-subtitle">If you have registered before, you can search for your name and auto-fill and submits the form.</p>

            <div class="mt-3">
              <div class="choice-inline">
                @foreach (['Yes', 'No'] as $option)
                  <label class="choice-pill">
                    <input type="radio" name="is_returning_participant" value="{{ $option }}" @checked(old('is_returning_participant') === $option)>
                    <span>{{ strtoupper($option) }}</span>
                  </label>
                @endforeach
              </div>
            </div>

            <div id="returningParticipantSearch" class="mt-3" style="display:none;">
              <div class="search-input-wrap">
                <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input
                  id="participantSearchInput"
                  type="text"
                  class="field-input"
                  placeholder="Type your name to search..."
                  autocomplete="off"
                >
                <div id="participantSearchResults" class="search-results" style="display:none;"></div>
              </div>

              <div id="participantVerifyPanel" class="verify-panel mt-3" style="display:none;">
                <div class="verify-panel-title">Identity Verification</div>
                <p class="verify-panel-subtitle">Please enter your registered mobile number to verify your identity.</p>
                <input
                  id="participantVerifyInput"
                  type="tel"
                  class="field-input"
                  placeholder="e.g., 09171234567"
                  autocomplete="off"
                >
                <div class="verify-panel-actions">
                  <button id="participantVerifyBtn" type="button" class="verify-btn">Verify</button>
                  <button id="participantVerifyCancel" class="verify-cancel">Cancel</button>
                </div>
                <div id="participantVerifyError" class="verify-error" style="display:none;"></div>
              </div>
            </div>
          </section>

          <div id="intakeFormSections" style="display:none;">

          <section class="form-block section-rise">
            <h3 class="form-block-title">A. Basic Information</h3>
            <p class="form-block-subtitle">Kindly ensure that all information provided is complete and accurate.</p>

            <div class="mt-4 grid gap-4 sm:grid-cols-3">
              <div>
                <label class="field-label">First Name</label>
                <input name="first_name" value="{{ old('first_name', $loggedInRecipient ? explode(' ', $loggedInRecipient->name)[0] ?? '' : '') }}" required class="field-input">
              </div>
              <div>
                <label class="field-label">Middle Initial (Optional)</label>
                <input name="middle_initial" value="{{ old('middle_initial') }}" maxlength="10" class="field-input">
              </div>
              <div>
                <label class="field-label">Last Name</label>
                <input name="last_name" value="{{ old('last_name') }}" required class="field-input">
              </div>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
              <div>
                <label class="field-label">Email</label>
                <input type="email" name="email" value="{{ old('email', $loggedInRecipient->email ?? '') }}" required class="field-input" placeholder="your.email@example.com">
                <p class="field-hint">This will serve as your certificate repository account. Make sure it's correct.</p>
              </div>
              <div>
                <label class="field-label">Contact Number</label>
                <input type="tel" name="contact_number" value="{{ old('contact_number', $loggedInRecipient->contact_number ?? '') }}" required class="field-input" placeholder="e.g., 09171234567">
                <p class="field-hint">This will be used for verification and OTPs. Make sure it is correct.</p>
              </div>
            </div>
          </section>

          <section class="form-block section-rise">
            <h3 class="form-block-title">B. Geographic Coverage</h3>

            <div class="mt-4 grid gap-4 sm:grid-cols-3">
              <div>
                <label class="field-label">Region</label>
                <select class="psgc-region field-input" name="region" data-old="{{ old('region') }}" required>
                  <option value="">-- Select --</option>
                </select>
                <span class="psgc-loading text-sm text-gray-500 hidden">Loading...</span>
              </div>
              <div>
                <label class="field-label">Province</label>
                <select class="psgc-province field-input" name="province" data-old="{{ old('province') }}" required disabled>
                  <option value="">-- Select --</option>
                </select>
                <span class="psgc-loading text-sm text-gray-500 hidden">Loading...</span>
              </div>
              <div>
                <label class="field-label">City / Municipality</label>
                <select class="psgc-city field-input" name="city_municipality" data-old="{{ old('city_municipality') }}" required disabled>
                  <option value="">-- Select --</option>
                </select>
                <span class="psgc-loading text-sm text-gray-500 hidden">Loading...</span>
              </div>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
              <div>
                <label class="field-label">Barangay</label>
                <select class="psgc-brgy field-input" name="barangay" data-old="{{ old('barangay') }}" required disabled>
                  <option value="">-- Select --</option>
                </select>
                <span class="psgc-loading text-sm text-gray-500 hidden">Loading...</span>
              </div>
              <div>
                <label class="field-label">Block / Lot / Purok (Optional)</label>
                <input name="block_lot_purok" value="{{ old('block_lot_purok') }}" class="field-input">
              </div>
            </div>
          </section>

          <section class="form-block section-rise">
            <h3 class="form-block-title">C. Demographic Information</h3>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
              <div>
                <label class="field-label">Sex</label>
                <select name="gender" required class="field-input">
                  <option value="">-- Select --</option>
                  <option value="Male" @selected(old('gender', $loggedInRecipient->gender ?? '') === 'Male')>Male</option>
                  <option value="Female" @selected(old('gender', $loggedInRecipient->gender ?? '') === 'Female')>Female</option>
                </select>
              </div>
              <div>
                <label class="field-label">Age Range</label>
                <select name="age_range" required class="field-input">
                  <option value="">-- Select --</option>
                  <option value="18-24 years old" @selected(old('age_range') === '18-24 years old')>18-24 years old</option>
                  <option value="25-34 years old" @selected(old('age_range') === '25-34 years old')>25-34 years old</option>
                  <option value="35-44 years old" @selected(old('age_range') === '35-44 years old')>35-44 years old</option>
                  <option value="45-54 years old" @selected(old('age_range') === '45-54 years old')>45-54 years old</option>
                  <option value="55-64 years old" @selected(old('age_range') === '55-64 years old')>55-64 years old</option>
                  <option value="65 years old and above" @selected(old('age_range') === '65 years old and above')>65 years old and above</option>
                </select>
              </div>
            </div>

            <div class="mt-4">
              <label class="field-label">Are you a Person with Disability (PWD)?</label>
              <div class="choice-inline">
                @foreach (($yesNoOptions ?? ['Yes', 'No']) as $option)
                  <label class="choice-pill">
                    <input type="radio" name="pwd_status" value="{{ $option }}" @checked(old('pwd_status') === $option) required>
                    <span>{{ strtoupper($option) }}</span>
                  </label>
                @endforeach
              </div>
            </div>
          </section>

          <section class="form-block section-rise">
            <h3 class="form-block-title">D. Socio-Economic Profile</h3>

            <div class="mt-4">
              <label class="field-label">Are you a 4PS Beneficiary?</label>
              <div class="choice-inline">
                @foreach (($yesNoOptions ?? ['Yes', 'No']) as $option)
                  <label class="choice-pill">
                    <input type="radio" name="is_4ps_beneficiary" value="{{ $option }}" @checked(old('is_4ps_beneficiary') === $option) required>
                    <span>{{ strtoupper($option) }}</span>
                  </label>
                @endforeach
              </div>
            </div>
          </section>

          <section class="form-block section-rise">
            <h3 class="form-block-title">E. Priority Community Classification</h3>

            <div class="mt-4">
              <label class="field-label">Are you Part of an ELCAC Community?</label>
              <div class="choice-inline">
                @foreach (($yesNoOptions ?? ['Yes', 'No']) as $option)
                  <label class="choice-pill">
                    <input type="radio" name="is_elcac_community" value="{{ $option }}" @checked(old('is_elcac_community') === $option) required>
                    <span>{{ strtoupper($option) }}</span>
                  </label>
                @endforeach
              </div>
            </div>
          </section>

          <section class="form-block section-rise">
            <h3 class="form-block-title">F. Organization Profile</h3>

            <div class="mt-4 grid gap-4 sm:grid-cols-3">
              <div>
                <label class="field-label">Organization Name</label>
                <input name="organization_name" value="{{ old('organization_name') }}" required class="field-input">
              </div>
              <div>
                <label class="field-label">Affiliation / Sector</label>
                <select name="industry" required class="field-input">
                  <option value="">-- Select --</option>
                  <option value="Student" @selected(old('industry') === 'Student')>Student</option>
                  <option value="Micro, Small, and Medium Enterprise (MSME)" @selected(old('industry') === 'Micro, Small, and Medium Enterprise (MSME)')>Micro, Small, and Medium Enterprise (MSME)</option>
                  <option value="Local Government Unit (LGU)" @selected(old('industry') === 'Local Government Unit (LGU)')>Local Government Unit (LGU)</option>
                  <option value="National Government Agency (NGA)" @selected(old('industry') === 'National Government Agency (NGA)')>National Government Agency (NGA)</option>
                  <option value="Non-Government Organization (NGO)" @selected(old('industry') === 'Non-Government Organization (NGO)')>Non-Government Organization (NGO)</option>
                  <option value="Government-Owned and Controlled Corporation (GOCC)" @selected(old('industry') === 'Government-Owned and Controlled Corporation (GOCC)')>Government-Owned and Controlled Corporation (GOCC)</option>
                  <option value="Civil Society Organization (CSO)" @selected(old('industry') === 'Civil Society Organization (CSO)')>Civil Society Organization (CSO)</option>
                  <option value="People's Organization (PO)" @selected(old('industry') === "People's Organization (PO)")>People's Organization (PO)</option>
                  <option value="Private Sector" @selected(old('industry') === 'Private Sector')>Private Sector</option>
                  <option value="Private Individual" @selected(old('industry') === 'Private Individual')>Private Individual</option>
                  <option value="Academe" @selected(old('industry') === 'Academe')>Academe</option>
                  <option value="Others" @selected(old('industry') === 'Others')>Others</option>
                </select>
              </div>
              <div>
                <label class="field-label">Position / Designation</label>
                <input name="position_designation" value="{{ old('position_designation') }}" required class="field-input">
              </div>
            </div>
          </section>

          <section class="form-block section-rise">
            <h3 class="form-block-title">G. DOST Engagement Profile</h3>

            <div class="mt-4 space-y-6">
              <div>
                <label class="field-label">Are you a Beneficiary/Recipient of any of the following DOST Programs?</label>
                <div class="choice-grid">
                  @foreach (($beneficiaryProgramOptions ?? []) as $option)
                    <label class="choice-pill">
                      <input
                        type="checkbox"
                        name="dost_program_beneficiary[]"
                        value="{{ $option }}"
                        @checked(in_array($option, $oldBeneficiaryPrograms, true))
                      >
                      <span>{{ $option }}</span>
                    </label>
                  @endforeach
                </div>
              </div>

              <div>
                <label class="field-label">Are you Directly Employed under any of the following Programs?</label>
                <div class="choice-grid">
                  @foreach (($employedProgramOptions ?? []) as $option)
                    <label class="choice-pill">
                      <input
                        type="checkbox"
                        name="directly_employed_programs[]"
                        value="{{ $option }}"
                        @checked(in_array($option, $oldDirectlyEmployedPrograms, true))
                      >
                      <span>{{ $option }}</span>
                    </label>
                  @endforeach
                </div>
              </div>
            </div>
          </section>

          <section class="form-block section-rise">
            <h3 class="form-block-title">H. Participation History</h3>

            <div class="mt-4">
              <label class="field-label">Have you previously attended a DOST Caraga Training?</label>
              <div class="choice-inline">
                @foreach (($yesNoOptions ?? ['Yes', 'No']) as $option)
                  <label class="choice-pill">
                    <input type="radio" name="has_attended_dost_training" value="{{ $option }}" @checked(old('has_attended_dost_training') === $option) required>
                    <span>{{ strtoupper($option) }}</span>
                  </label>
                @endforeach
              </div>
            </div>
          </section>

          <section class="form-block section-rise">
            <h3 class="form-block-title">I. Service Interest</h3>

            <div class="mt-4">
              <label class="field-label">Which DOST Services are you interested in Availing?</label>
              <div class="choice-grid">
                @foreach (($serviceInterestOptions ?? []) as $option)
                  <label class="choice-pill">
                    <input
                      type="checkbox"
                      name="interested_dost_services[]"
                      value="{{ $option }}"
                      @checked(in_array($option, $oldInterestedServices, true))
                    >
                    <span>{{ $option }}</span>
                  </label>
                @endforeach
              </div>
              <div class="mt-3" id="interestedServiceOtherWrap" style="{{ in_array('Others', $oldInterestedServices, true) ? '' : 'display:none;' }}">
                <label class="field-label">If Others, please specify</label>
                <input
                  type="text"
                  id="interestedServiceOtherInput"
                  name="interested_dost_services_other"
                  value="{{ old('interested_dost_services_other') }}"
                  class="field-input"
                  maxlength="255"
                  {{ in_array('Others', $oldInterestedServices, true) ? 'required' : '' }}
                >
              </div>
            </div>
          </section>

          <div class="submit-wrap">
            <button class="submit-btn">
              Submit Form
            </button>
          </div>

          </div>{{-- end #intakeFormSections --}}
        </div>{{-- end #intakeFormFields --}}
      </form>
    </div>
  </div>

  <script>
    // ── Toast notifications (centered + dim backdrop) ──
    (function() {
      const container = document.getElementById('toastContainer');
      if (!container) return;

      const icons = {
        success: '<svg class="toast-icon" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        error: '<svg class="toast-icon" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>'
      };

      const closeSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';

      let backdrop = null;

      function removeBackdrop() {
        if (backdrop) {
          backdrop.classList.add('fade-out');
          setTimeout(function() { if (backdrop && backdrop.parentNode) backdrop.remove(); backdrop = null; }, 200);
        }
      }

      function dismissToast(toast) {
        toast.classList.add('toast-out');
        removeBackdrop();
        setTimeout(function() { if (toast.parentNode) toast.remove(); }, 200);
      }

      window.showToast = function(type, message, errorsList) {
        // Remove existing backdrop first
        removeBackdrop();

        // Create dim backdrop
        backdrop = document.createElement('div');
        backdrop.className = 'toast-backdrop';
        backdrop.addEventListener('click', function() {
          var t = container.querySelector('.toast');
          if (t) dismissToast(t);
        });
        document.body.appendChild(backdrop);

        // Create toast
        const toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        let bodyHtml = '<div class="toast-body"><div>' + message + '</div>';
        if (errorsList && errorsList.length) {
          bodyHtml += '<ul>' + errorsList.map(function(e) { return '<li>' + e + '</li>'; }).join('') + '</ul>';
        }
        bodyHtml += '</div>';
        toast.innerHTML = icons[type] + bodyHtml + '<button class="toast-close">' + closeSvg + '</button>';

        // Close handler
        toast.querySelector('.toast-close').addEventListener('click', function() {
          dismissToast(toast);
        });

        container.appendChild(toast);

        if (type === 'success') {
          setTimeout(function() {
            if (toast.parentNode) dismissToast(toast);
          }, 5000);
        }
      };

      // Render server-side messages
      @if (session('success'))
        showToast('success', {!! json_encode(session('success')) !!});
      @endif

      @if ($errors->any())
        showToast('error', 'Please check the form:', {!! json_encode($errors->all()) !!});
      @endif
    })();

    const privacyConsentCheckbox = document.getElementById('privacyConsentCheckbox');
    const intakeFormFields = document.getElementById('intakeFormFields');
    const toggleIntakeFields = () => {
      if (!privacyConsentCheckbox || !intakeFormFields) return;
      const show = privacyConsentCheckbox.checked;
      intakeFormFields.classList.toggle('is-visible', show);
      intakeFormFields.classList.toggle('is-hidden', !show);
    };
    if (privacyConsentCheckbox && intakeFormFields) {
      privacyConsentCheckbox.addEventListener('change', toggleIntakeFields);
      toggleIntakeFields();
    }

    const nameInputs = document.querySelectorAll('input[name="first_name"], input[name="middle_initial"], input[name="last_name"]');

    const toTitleCaseWords = (value) => value
      .toLocaleLowerCase()
      .replace(/(^|[\s'-])([a-zA-ZÀ-ÖØ-öø-ÿ])/g, (match, boundary, letter) => `${boundary}${letter.toLocaleUpperCase()}`);

    const normalizeNameInput = (input) => {
      const normalizedValue = toTitleCaseWords(input.value);
      if (normalizedValue === input.value) {
        return;
      }

      const selectionStart = input.selectionStart;
      const selectionEnd = input.selectionEnd;

      input.value = normalizedValue;

      if (typeof selectionStart === 'number' && typeof selectionEnd === 'number') {
        input.setSelectionRange(selectionStart, selectionEnd);
      }
    };

    if (nameInputs.length) {
      nameInputs.forEach((input) => {
        input.addEventListener('input', () => normalizeNameInput(input));
        input.addEventListener('blur', () => normalizeNameInput(input));
        normalizeNameInput(input);
      });
    }

    const serviceChecks = document.querySelectorAll('input[name="interested_dost_services[]"]');
    const interestedServiceOtherWrap = document.getElementById('interestedServiceOtherWrap');
    const interestedServiceOtherInput = document.getElementById('interestedServiceOtherInput');

    const toggleInterestedServiceOther = () => {
      if (!interestedServiceOtherWrap || !interestedServiceOtherInput || !serviceChecks.length) {
        return;
      }

      const hasOthers = Array.from(serviceChecks).some((box) => box.value === 'Others' && box.checked);
      interestedServiceOtherWrap.style.display = hasOthers ? '' : 'none';
      interestedServiceOtherInput.required = hasOthers;
      if (!hasOthers) {
        interestedServiceOtherInput.value = '';
      }
    };

    if (serviceChecks.length) {
      serviceChecks.forEach((box) => box.addEventListener('change', toggleInterestedServiceOther));
      toggleInterestedServiceOther();
    }

    const enforceNotApplicableExclusivity = (groupName) => {
      const boxes = document.querySelectorAll(`input[name="${groupName}[]"]`);
      if (!boxes.length) {
        return;
      }

      const onChange = (event) => {
        const target = event.target;
        const isNotApplicable = target.value === 'Not Applicable';

        if (isNotApplicable && target.checked) {
          boxes.forEach((box) => {
            if (box !== target) {
              box.checked = false;
            }
          });
        }

        if (!isNotApplicable && target.checked) {
          boxes.forEach((box) => {
            if (box.value === 'Not Applicable') {
              box.checked = false;
            }
          });
        }
      };

      boxes.forEach((box) => box.addEventListener('change', onChange));
    };

    enforceNotApplicableExclusivity('dost_program_beneficiary');
    enforceNotApplicableExclusivity('directly_employed_programs');

    // ============================================================
    // PSGC Cascading Dropdowns (via API proxy)
    // ============================================================

    (function() {
      const regionSel   = document.querySelector('.psgc-region');
      const provinceSel = document.querySelector('.psgc-province');
      const citySel     = document.querySelector('.psgc-city');
      const brgySel     = document.querySelector('.psgc-brgy');

      if (!regionSel || !provinceSel || !citySel || !brgySel) return;

      // --- Utilities ---

      const clearSelect = (sel, label) => {
        sel.innerHTML = '';
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = label || '-- Select --';
        sel.appendChild(opt);
      };

      const showLoading = (sel) => {
        const spinner = sel.parentNode.querySelector('.psgc-loading');
        if (spinner) spinner.classList.remove('hidden');
        sel.disabled = true;
      };

      const hideLoading = (sel) => {
        const spinner = sel.parentNode.querySelector('.psgc-loading');
        if (spinner) spinner.classList.add('hidden');
        sel.disabled = false;
      };

      const showError = (sel, msg) => {
        clearSelect(sel);
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = msg || 'Unable to load. Please try again.';
        opt.disabled = true;
        sel.appendChild(opt);
        sel.disabled = true;
      };

      const populateSelect = (sel, items, placeholder) => {
        clearSelect(sel, placeholder);
        items.forEach((item) => {
          const opt = document.createElement('option');
          opt.value = item.name;
          opt.textContent = item.name;
          opt.dataset.psgcCode = item.psgc_code;
          sel.appendChild(opt);
        });
      };

      // --- API fetch helpers ---

      const fetchFromProxy = async (endpoint, params = {}) => {
        const url = new URL(endpoint, window.location.origin);
        Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
        const res = await fetch(url.toString());
        if (!res.ok) {
          console.error('PSGC proxy error:', res.status, res.statusText);
          return [];
        }
        const data = await res.json();
        if (!Array.isArray(data)) {
          console.error('PSGC proxy returned non-array:', data);
          return [];
        }
        return data;
      };

      // --- Cascading loaders ---

      const loadRegions = async () => {
        showLoading(regionSel);
        try {
          const items = await fetchFromProxy('/api/psgc/regions');
          if (items.length === 0) {
            showError(regionSel, 'Unable to load regions. Please try again.');
            return [];
          }
          populateSelect(regionSel, items, '-- Select region --');
          return items;
        } catch (e) {
          console.error('Failed to load regions:', e);
          showError(regionSel, 'Unable to load regions. Please try again.');
          return [];
        } finally {
          hideLoading(regionSel);
        }
      };

      const loadProvinces = async (regPsgcCode) => {
        clearSelect(provinceSel, '-- Select province --');
        clearSelect(citySel, '-- Select city/municipality --');
        clearSelect(brgySel, '-- Select barangay --');
        citySel.disabled = true;
        brgySel.disabled = true;

        showLoading(provinceSel);
        provinceSel.disabled = true;
        try {
          const items = await fetchFromProxy('/api/psgc/provinces',
            { reg_code: regPsgcCode });
          if (items.length === 0) {
            showError(provinceSel, 'Unable to load provinces. Please try again.');
            return [];
          }
          populateSelect(provinceSel, items, '-- Select province --');
          provinceSel.disabled = false;
          return items;
        } catch (e) {
          console.error('Failed to load provinces:', e);
          showError(provinceSel, 'Unable to load provinces. Please try again.');
          return [];
        } finally {
          hideLoading(provinceSel);
        }
      };

      const loadCities = async (provinceName) => {
        clearSelect(citySel, '-- Select city/municipality --');
        clearSelect(brgySel, '-- Select barangay --');
        brgySel.disabled = true;

        showLoading(citySel);
        citySel.disabled = true;
        try {
          const items = await fetchFromProxy('/api/psgc/municipalities',
            { province_name: provinceName });
          if (items.length === 0) {
            showError(citySel, 'Unable to load cities. Please try again.');
            return [];
          }
          populateSelect(citySel, items, '-- Select city/municipality --');
          citySel.disabled = false;
          return items;
        } catch (e) {
          console.error('Failed to load cities:', e);
          showError(citySel, 'Unable to load cities. Please try again.');
          return [];
        } finally {
          hideLoading(citySel);
        }
      };

      const loadBarangays = async (cityName, provinceName) => {
        clearSelect(brgySel, '-- Select barangay --');

        showLoading(brgySel);
        brgySel.disabled = true;
        try {
          const items = await fetchFromProxy('/api/psgc/barangays',
            { city_name: cityName, province_name: provinceName });
          if (items.length === 0) {
            showError(brgySel, 'Unable to load barangays. Please try again.');
            return [];
          }
          populateSelect(brgySel, items, '-- Select barangay --');
          brgySel.disabled = false;
          return items;
        } catch (e) {
          console.error('Failed to load barangays:', e);
          showError(brgySel, 'Unable to load barangays. Please try again.');
          return [];
        } finally {
          hideLoading(brgySel);
        }
      };

      // --- Helper: get selected option's value (name) ---

      const selectedValue = (sel) => {
        const opt = sel.selectedOptions[0];
        return opt ? opt.value : '';
      };

      const selectedPsgcCode = (sel) => {
        const opt = sel.selectedOptions[0];
        return opt ? (opt.dataset.psgcCode || '') : '';
      };

      // --- Event handlers ---

      regionSel.addEventListener('change', async function () {
        const code = selectedPsgcCode(this);
        if (code) {
          await loadProvinces(code);
        } else {
          clearSelect(provinceSel, '-- Select province --');
          clearSelect(citySel, '-- Select city/municipality --');
          clearSelect(brgySel, '-- Select barangay --');
          provinceSel.disabled = true;
          citySel.disabled = true;
          brgySel.disabled = true;
        }
      });

      provinceSel.addEventListener('change', async function () {
        const name = selectedValue(this);
        if (name) {
          await loadCities(name);
        } else {
          clearSelect(citySel, '-- Select city/municipality --');
          clearSelect(brgySel, '-- Select barangay --');
          citySel.disabled = true;
          brgySel.disabled = true;
        }
      });

      citySel.addEventListener('change', async function () {
        const cityName = selectedValue(this);
        const provinceName = selectedValue(provinceSel);
        if (cityName && provinceName) {
          await loadBarangays(cityName, provinceName);
        } else {
          clearSelect(brgySel, '-- Select barangay --');
          brgySel.disabled = true;
        }
      });

      // --- Repopulate from old() values (validation failure) ---

      const repopulateOldValues = async () => {
        const oldRegion   = regionSel.dataset.old || '';
        const oldProvince = provinceSel.dataset.old || '';
        const oldCity     = citySel.dataset.old || '';
        const oldBarangay = brgySel.dataset.old || '';

        if (!oldRegion) return;

        regionSel.value = oldRegion;
        if (regionSel.value !== oldRegion) return;

        const regCode = selectedPsgcCode(regionSel);
        if (!regCode) return;

        if (!oldProvince) return;
        await loadProvinces(regCode);
        provinceSel.value = oldProvince;
        if (provinceSel.value !== oldProvince) return;

        const provinceName = selectedValue(provinceSel);
        if (!provinceName) return;

        if (!oldCity) return;
        await loadCities(provinceName);
        citySel.value = oldCity;
        if (citySel.value !== oldCity) return;

        const cityName = selectedValue(citySel);
        if (!cityName) return;

        if (!oldBarangay) return;
        await loadBarangays(cityName, provinceName);
        brgySel.value = oldBarangay;
      };

      // --- Initialize ---

      loadRegions().then(() => repopulateOldValues());
    })();

    // ============================================================
    // Returning Participant Search & Auto-Fill
    // ============================================================

    (function() {
      const returningRadios = document.querySelectorAll('input[name="is_returning_participant"]');
      const searchArea = document.getElementById('returningParticipantSearch');
      const searchInput = document.getElementById('participantSearchInput');
      const searchResults = document.getElementById('participantSearchResults');
      const verifyPanel = document.getElementById('participantVerifyPanel');
      const verifyInput = document.getElementById('participantVerifyInput');
      const verifyBtn = document.getElementById('participantVerifyBtn');
      const verifyCancel = document.getElementById('participantVerifyCancel');
      const verifyError = document.getElementById('participantVerifyError');
      const form = document.getElementById('participantIntakeForm');

      if (!returningRadios.length || !searchArea || !searchInput || !searchResults) return;

      let debounceTimer;
      let selectedParticipant = null;

      const escapeHtml = (str) => {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
      };

      const normalizeMobile = (num) => {
        if (!num) return '';
        return num.replace(/\D/g, '');
      };

      // --- Toggle search area ---

      returningRadios.forEach(function(radio) {
        radio.addEventListener('change', function() {
          var formSections = document.getElementById('intakeFormSections');
          if (radio.value === 'Yes' && radio.checked) {
            searchArea.style.display = '';
            if (formSections) formSections.style.display = 'none';
            setTimeout(function() { searchInput.focus(); }, 100);
          } else {
            searchArea.style.display = 'none';
            searchInput.value = '';
            searchResults.style.display = 'none';
            searchResults.innerHTML = '';
            selectedParticipant = null;
            hideVerifyPanel();
            if (formSections) formSections.style.display = '';
          }
        });
      });

      // --- Close dropdown when clicking outside ---

      document.addEventListener('click', function(e) {
        if (!searchArea.contains(e.target)) {
          searchResults.style.display = 'none';
        }
      });

      // --- Debounced search ---

      searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        hideVerifyPanel();
        var q = searchInput.value.trim();
        if (q.length < 2) {
          searchResults.style.display = 'none';
          searchResults.innerHTML = '';
          return;
        }
        debounceTimer = setTimeout(function() {
          fetch('/api/participants/search?q=' + encodeURIComponent(q))
            .then(function(res) { return res.json(); })
            .then(function(data) { renderResults(data); })
            .catch(function(e) {
              console.error('Participant search failed:', e);
              searchResults.style.display = 'none';
            });
        }, 300);
      });

      // --- Show/hide verify panel ---

      function showVerifyPanel(participant) {
        selectedParticipant = participant;
        searchResults.style.display = 'none';
        searchResults.innerHTML = '';
        if (verifyPanel) verifyPanel.style.display = '';
        if (verifyInput) {
          verifyInput.value = '';
          setTimeout(function() { verifyInput.focus(); }, 100);
        }
        if (verifyError) verifyError.style.display = 'none';
      }

      function hideVerifyPanel() {
        selectedParticipant = null;
        if (verifyPanel) verifyPanel.style.display = 'none';
        if (verifyInput) verifyInput.value = '';
        if (verifyError) verifyError.style.display = 'none';
      }

      // --- Verify button handler ---

      if (verifyBtn) {
        verifyBtn.addEventListener('click', function() {
          if (!selectedParticipant) return;
          var entered = normalizeMobile(verifyInput.value.trim());
          var expected = normalizeMobile(selectedParticipant.contact_number);

          if (!entered) {
            if (verifyError) {
              verifyError.textContent = 'Please enter your mobile number.';
              verifyError.style.display = '';
            }
            return;
          }

          if (entered === expected) {
            var participant = selectedParticipant;
            hideVerifyPanel();
            searchInput.value = participant.participant_name;
            searchInput.disabled = true;
            // Ensure privacy consent is checked for auto-submit
            if (privacyConsentCheckbox && !privacyConsentCheckbox.checked) {
              privacyConsentCheckbox.checked = true;
              toggleIntakeFields();
            }
            autofillForm(participant).then(function() {
              // Auto-submit after populating fields
              if (form) {
                var submitBtn = form.querySelector('.submit-btn');
                if (submitBtn) {
                  submitBtn.textContent = 'Registering...';
                  submitBtn.disabled = true;
                }
                form.submit();
              }
            });
          } else {
            if (verifyError) {
              verifyError.innerHTML = '<ul style="list-style-type: disc; padding-left: 1.5rem; text-align: left; margin: 0;"><li>The mobile number does not match our records. Please try again.</li><li>If you forgot your registered number or entered an invalid one, please contact the MIS Team at mis@caraga.dost.gov.ph.</li></ul>';
              verifyError.style.display = '';
            }
            if (verifyInput) verifyInput.value = '';
          }
        });

        verifyInput.addEventListener('keydown', function(e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            verifyBtn.click();
          }
        });
      }

      // --- Cancel button handler ---

      if (verifyCancel) {
        verifyCancel.addEventListener('click', function() {
          hideVerifyPanel();
          searchInput.value = '';
          searchInput.focus();
        });
      }

      // --- Render dropdown (name only, no email/org) ---

      function renderResults(participants) {
        searchResults.innerHTML = '';
        if (!participants.length) {
          searchResults.innerHTML = '<div class="search-result-empty">No matching participants found.</div>';
          searchResults.style.display = '';
          return;
        }
        participants.forEach(function(p) {
          var item = document.createElement('div');
          item.className = 'search-result-item';
          item.innerHTML = '<span class="search-result-name">' + escapeHtml(p.participant_name) + '</span>';
          item.addEventListener('click', function() { showVerifyPanel(p); });
          searchResults.appendChild(item);
        });
        searchResults.style.display = '';
      }

      // --- Helper: set value on a text/email/tel input ---

      function setInputValue(selector, value) {
        var el = document.querySelector(selector);
        if (el && value) {
          el.value = value;
          el.dispatchEvent(new Event('input', { bubbles: true }));
        }
      }

      // --- Helper: set value on a select ---

      function setSelectValue(selector, value) {
        var el = document.querySelector(selector);
        if (!el || !value) return;
        for (var i = 0; i < el.options.length; i++) {
          if (el.options[i].value === value) {
            el.value = value;
            return;
          }
        }
        // Value not in options yet; try setting directly
        el.value = value;
      }

      // --- Helper: set radio button by name ---

      function setRadioValue(name, value) {
        if (!value) return;
        var radios = document.querySelectorAll('input[name="' + name + '"]');
        radios.forEach(function(r) {
          r.checked = (r.value === value);
        });
      }

      // --- Helper: set checkboxes by name ---

      function setCheckboxValues(name, values) {
        if (!values || !values.length) return;
        var boxes = document.querySelectorAll('input[name="' + name + '"]');
        boxes.forEach(function(b) {
          var shouldCheck = (values.indexOf(b.value) !== -1);
          if (b.checked !== shouldCheck) {
            b.checked = shouldCheck;
            b.dispatchEvent(new Event('change', { bubbles: true }));
          }
        });
      }

      // --- Helper: wait for a select to be ready (enabled + populated) ---

      function waitForSelectReady(sel, timeout) {
        timeout = timeout || 5000;
        var start = Date.now();
        return new Promise(function(resolve) {
          function check() {
            if (!sel.disabled && sel.options.length > 1) {
              resolve(true);
              return;
            }
            if (Date.now() - start > timeout) {
              resolve(false);
              return;
            }
            setTimeout(check, 80);
          }
          check();
        });
      }

      // --- Auto-fill PSGC cascading dropdowns ---

      async function autofillPsgc(participant) {
        var regionSel = document.querySelector('.psgc-region');
        var provinceSel = document.querySelector('.psgc-province');
        var citySel = document.querySelector('.psgc-city');
        var brgySel = document.querySelector('.psgc-brgy');

        if (!regionSel || !provinceSel || !citySel || !brgySel) return;

        // Case-insensitive option lookup — stored DB values may differ in
        // capitalisation from what the PSGC API currently returns (e.g.
        // "REGION XIII (Caraga)" vs "Region XIII (Caraga)").
        function findOption(sel, target) {
          if (!target) return null;
          var lower = target.toLowerCase();
          for (var i = 0; i < sel.options.length; i++) {
            if (sel.options[i].value.toLowerCase() === lower) {
              return sel.options[i].value;
            }
          }
          return null;
        }

        // Set region
        if (participant.region) {
          var regionMatch = findOption(regionSel, participant.region);
          if (regionMatch && regionSel.value !== regionMatch) {
            regionSel.value = regionMatch;
            regionSel.dispatchEvent(new Event('change', { bubbles: true }));
            await waitForSelectReady(provinceSel);
          }
        }

        // Set province
        if (participant.province && !provinceSel.disabled) {
          var provinceMatch = findOption(provinceSel, participant.province);
          if (provinceMatch && provinceSel.value !== provinceMatch) {
            provinceSel.value = provinceMatch;
            provinceSel.dispatchEvent(new Event('change', { bubbles: true }));
            await waitForSelectReady(citySel);
          }
        }

        // Set city
        if (participant.city_municipality && !citySel.disabled) {
          var cityMatch = findOption(citySel, participant.city_municipality);
          if (cityMatch && citySel.value !== cityMatch) {
            citySel.value = cityMatch;
            citySel.dispatchEvent(new Event('change', { bubbles: true }));
            await waitForSelectReady(brgySel);
          }
        }

        // Set barangay
        if (participant.barangay && !brgySel.disabled) {
          var brgyMatch = findOption(brgySel, participant.barangay);
          if (brgyMatch) brgySel.value = brgyMatch;
        }
      }

      // --- Main auto-fill function ---

      async function autofillForm(participant) {
        selectedParticipant = participant;

        // Show form sections now that we're auto-filling
        var formSections = document.getElementById('intakeFormSections');
        if (formSections) formSections.style.display = '';

        // Update search input to show selected name
        searchInput.value = participant.participant_name;
        searchResults.style.display = 'none';
        searchResults.innerHTML = '';

        // Basic info
        setInputValue('input[name="first_name"]', participant.first_name);
        setInputValue('input[name="middle_initial"]', participant.middle_initial);
        setInputValue('input[name="last_name"]', participant.last_name);
        setInputValue('input[name="email"]', participant.email);
        setInputValue('input[name="contact_number"]', participant.contact_number);

        // Demographic
        setSelectValue('select[name="gender"]', participant.gender);
        setSelectValue('select[name="age_range"]', participant.age_range);
        setRadioValue('pwd_status', participant.pwd_status);

        // Socio-economic
        setRadioValue('is_4ps_beneficiary', participant.is_4ps_beneficiary);

        // Priority community
        setRadioValue('is_elcac_community', participant.is_elcac_community);

        // Organization profile
        setInputValue('input[name="organization_name"]', participant.organization_name);
        setSelectValue('select[name="industry"]', participant.industry);
        setInputValue('input[name="position_designation"]', participant.position_designation);

        // DOST engagement
        var beneficiaryPrograms = participant.dost_program_beneficiary || [];
        if (typeof beneficiaryPrograms === 'string') {
          try { beneficiaryPrograms = JSON.parse(beneficiaryPrograms); } catch(e) { beneficiaryPrograms = [beneficiaryPrograms]; }
        }
        setCheckboxValues('dost_program_beneficiary[]', beneficiaryPrograms);

        var employedPrograms = participant.directly_employed_programs || [];
        if (typeof employedPrograms === 'string') {
          try { employedPrograms = JSON.parse(employedPrograms); } catch(e) { employedPrograms = [employedPrograms]; }
        }
        setCheckboxValues('directly_employed_programs[]', employedPrograms);

        // Participation history
        setRadioValue('has_attended_dost_training', participant.has_attended_dost_training);

        // Service interest
        var interestedServices = participant.interested_dost_services || [];
        if (typeof interestedServices === 'string') {
          try { interestedServices = JSON.parse(interestedServices); } catch(e) { interestedServices = [interestedServices]; }
        }
        setCheckboxValues('interested_dost_services[]', interestedServices);

        // "Others" toggle for service interest
        var otherWrap = document.getElementById('interestedServiceOtherWrap');
        var otherInput = document.getElementById('interestedServiceOtherInput');
        if (otherWrap && otherInput) {
          if (interestedServices.indexOf('Others') !== -1) {
            otherWrap.style.display = '';
            otherInput.required = true;
            if (participant.interested_dost_services_other) {
              otherInput.value = participant.interested_dost_services_other;
            }
          } else {
            otherWrap.style.display = 'none';
            otherInput.required = false;
            otherInput.value = '';
          }
        }

        // Geo
        setInputValue('input[name="block_lot_purok"]', participant.block_lot_purok);

        // PSGC cascading (async)
        await autofillPsgc(participant);
      }

      // --- Initialize: show search if "Yes" was old() value ---

      var oldReturning = document.querySelector('input[name="is_returning_participant"][value="Yes"]');
      if (oldReturning && oldReturning.checked) {
        searchArea.style.display = '';
        var formSections = document.getElementById('intakeFormSections');
        if (formSections) formSections.style.display = 'none';
      }
    })();
  </script>
</x-public-layout>
