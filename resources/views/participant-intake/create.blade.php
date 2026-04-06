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

    .status-box {
      margin: 1.5rem 1.5rem 0;
      border-radius: 14px;
      padding: 0.85rem 1rem;
      font-size: 0.92rem;
      line-height: 1.5;
    }

    .status-box.success {
      border: 1px solid #a7f3d0;
      background: #ecfdf5;
      color: #065f46;
    }

    .status-box.error {
      border: 1px solid #fecaca;
      background: #fef2f2;
      color: #991b1b;
    }

    .status-box ul {
      margin: 0.5rem 0 0;
      padding-left: 1.2rem;
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
        align-items: flex-start;
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

      @if (session('success'))
        <div class="status-box success">
          <div class="font-bold">{{ session('success') }}</div>
        </div>
      @endif

      @if ($errors->any())
        <div class="status-box error">
          <div class="font-bold">Please check the form:</div>
          <ul>
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      @php
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
            <h3 class="form-block-title">A. Basic Information</h3>
            <p class="form-block-subtitle">Kindly ensure that all information provided is complete and accurate.</p>

            <div class="mt-4 grid gap-4 sm:grid-cols-3">
              <div>
                <label class="field-label">First Name</label>
                <input name="first_name" value="{{ old('first_name') }}" required class="field-input">
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
                <input type="email" name="email" value="{{ old('email') }}" required class="field-input">
              </div>
              <div>
                <label class="field-label">Contact Number</label>
                <input type="tel" name="contact_number" value="{{ old('contact_number') }}" required class="field-input" placeholder="e.g., 09171234567">
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
              </div>
              <div>
                <label class="field-label">Province</label>
                <select class="psgc-province field-input" name="province" data-old="{{ old('province') }}" required disabled>
                  <option value="">-- Select --</option>
                </select>
              </div>
              <div>
                <label class="field-label">City / Municipality</label>
                <select class="psgc-city field-input" name="city_municipality" data-old="{{ old('city_municipality') }}" required disabled>
                  <option value="">-- Select --</option>
                </select>
              </div>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
              <div>
                <label class="field-label">Barangay</label>
                <select class="psgc-brgy field-input" name="barangay" data-old="{{ old('barangay') }}" required disabled>
                  <option value="">-- Select --</option>
                </select>
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
                  <option value="Male" @selected(old('gender') === 'Male')>Male</option>
                  <option value="Female" @selected(old('gender') === 'Female')>Female</option>
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
        </div>
      </form>
    </div>
  </div>

  <script>
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

    const psgcUrl = "{{ asset('data/psgc.json') }}";
    let psgcCache = null;

    const normalizePsgc = (raw) => {
      const regions = [];
      for (const [regionName, regionObj] of Object.entries(raw || {})) {
        if (typeof regionObj !== 'object') continue;
        const provinces = [];
        for (const [provName, provObj] of Object.entries(regionObj)) {
          if (provName === 'population' || typeof provObj !== 'object') continue;
          const cities = [];
          for (const [cityName, cityObj] of Object.entries(provObj)) {
            if (cityName === 'population' || cityName === 'class' || cityName === 'cityClass' || typeof cityObj !== 'object') continue;
            const barangays = [];
            for (const [brgyName, brgyObj] of Object.entries(cityObj)) {
              if (typeof brgyObj === 'object' && brgyObj !== null && 'population' in brgyObj) {
                barangays.push(brgyName);
              }
            }
            cities.push({ name: cityName, barangays: barangays.sort((a, b) => a.localeCompare(b)) });
          }
          provinces.push({ name: provName, cities });
        }
        provinces.sort((a, b) => a.name.localeCompare(b.name));
        regions.push({ name: regionName, provinces });
      }
      regions.sort((a, b) => a.name.localeCompare(b.name));
      return regions;
    };

    const loadPsgc = async () => {
      if (psgcCache) return psgcCache;
      const res = await fetch(psgcUrl);
      const raw = await res.json();
      psgcCache = normalizePsgc(raw);
      return psgcCache;
    };

    const clearSelect = (sel, label = '-- Select --') => {
      sel.innerHTML = '';
      const opt = document.createElement('option');
      opt.value = '';
      opt.textContent = label;
      sel.appendChild(opt);
    };

    const initPsgc = async () => {
      const regionSel = document.querySelector('.psgc-region');
      const provinceSel = document.querySelector('.psgc-province');
      const citySel = document.querySelector('.psgc-city');
      const brgySel = document.querySelector('.psgc-brgy');
      if (!regionSel || !provinceSel || !citySel || !brgySel) return;

      const regions = await loadPsgc();

      const fillRegions = () => {
        clearSelect(regionSel, '-- Select region --');
        regions.forEach((r) => {
          const o = document.createElement('option');
          o.value = r.name;
          o.textContent = r.name;
          regionSel.appendChild(o);
        });
      };

      const fillProvinces = (regionName) => {
        clearSelect(provinceSel, '-- Select province --');
        clearSelect(citySel, '-- Select city/municipality --');
        clearSelect(brgySel, '-- Select barangay --');
        provinceSel.disabled = !regionName;
        citySel.disabled = true;
        brgySel.disabled = true;
        const region = regions.find((r) => r.name === regionName);
        if (!region) return;
        region.provinces.forEach((p) => {
          const o = document.createElement('option');
          o.value = p.name;
          o.textContent = p.name;
          provinceSel.appendChild(o);
        });
      };

      const fillCities = (regionName, provinceName) => {
        clearSelect(citySel, '-- Select city/municipality --');
        clearSelect(brgySel, '-- Select barangay --');
        citySel.disabled = !provinceName;
        brgySel.disabled = true;
        const region = regions.find((r) => r.name === regionName);
        if (!region) return;
        const province = region.provinces.find((p) => p.name === provinceName);
        if (!province) return;
        province.cities.forEach((c) => {
          const o = document.createElement('option');
          o.value = c.name;
          o.textContent = c.name;
          citySel.appendChild(o);
        });
      };

      const fillBarangays = (regionName, provinceName, cityName) => {
        clearSelect(brgySel, '-- Select barangay --');
        brgySel.disabled = !cityName;
        const region = regions.find((r) => r.name === regionName);
        if (!region) return;
        const province = region.provinces.find((p) => p.name === provinceName);
        if (!province) return;
        const city = province.cities.find((c) => c.name === cityName);
        if (!city) return;
        city.barangays.forEach((b) => {
          const o = document.createElement('option');
          o.value = b;
          o.textContent = b;
          brgySel.appendChild(o);
        });
      };

      regionSel.addEventListener('change', (e) => fillProvinces(e.target.value));
      provinceSel.addEventListener('change', (e) => fillCities(regionSel.value, e.target.value));
      citySel.addEventListener('change', (e) => fillBarangays(regionSel.value, provinceSel.value, e.target.value));

      fillRegions();

      const oldRegion = regionSel.dataset.old || '';
      const oldProvince = provinceSel.dataset.old || '';
      const oldCity = citySel.dataset.old || '';
      const oldBarangay = brgySel.dataset.old || '';

      if (oldRegion) {
        regionSel.value = oldRegion;
        fillProvinces(oldRegion);
      }
      if (oldProvince) {
        provinceSel.value = oldProvince;
        fillCities(oldRegion, oldProvince);
      }
      if (oldCity) {
        citySel.value = oldCity;
        fillBarangays(oldRegion, oldProvince, oldCity);
      }
      if (oldBarangay) {
        brgySel.value = oldBarangay;
      }
    };

    initPsgc();
  </script>
</x-public-layout>
