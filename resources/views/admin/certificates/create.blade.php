<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create Certificate</title>
  <style>
    :root {
      --bg-a: #eaf2ff;
      --bg-b: #f4fbf8;
      --card: #ffffff;
      --card-border: #d9e4f1;
      --text: #0f172a;
      --muted: #52637a;
      --label: #1e293b;
      --accent: #0d4f8c;
      --accent-2: #0f8b8d;
      --focus: rgba(13, 79, 140, 0.18);
      --danger-bg: #fff1f2;
      --danger-border: #fecdd3;
      --danger-text: #9f1239;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      padding: 24px;
      color: var(--text);
      font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
      background:
        radial-gradient(1000px 360px at 0% -10%, #d8e6ff 0%, transparent 58%),
        radial-gradient(900px 420px at 100% -20%, #d7f4ef 0%, transparent 60%),
        linear-gradient(145deg, var(--bg-a) 0%, var(--bg-b) 100%);
      min-height: 100vh;
    }

    .shell {
      width: 100%;
      max-width: 930px;
      margin: 0 auto;
      border: 1px solid var(--card-border);
      border-radius: 22px;
      background: var(--card);
      box-shadow: 0 22px 60px rgba(15, 23, 42, 0.12);
      overflow: hidden;
    }

    .hero {
      padding: 24px 24px 18px;
      border-bottom: 1px solid #e4edf7;
      background: linear-gradient(130deg, #f7fbff 0%, #ecf6ff 52%, #eefcf8 100%);
      display: flex;
      justify-content: space-between;
      gap: 14px;
      align-items: flex-start;
    }

    .eyebrow {
      margin: 0;
      display: inline-flex;
      align-items: center;
      border-radius: 999px;
      border: 1px solid #b9d6ef;
      background: #e8f3ff;
      color: #0b4c8c;
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.07em;
      text-transform: uppercase;
      padding: 5px 10px;
    }

    h1 {
      margin: 10px 0 0;
      font-size: clamp(28px, 3.1vw, 36px);
      font-weight: 900;
      letter-spacing: -0.03em;
      line-height: 1.05;
    }

    .subtitle {
      margin: 10px 0 0;
      max-width: 600px;
      color: var(--muted);
      font-size: 14px;
      line-height: 1.45;
    }

    .back-link {
      margin-top: 6px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      padding: 10px 14px;
      border: 1px solid #bcd1e5;
      border-radius: 12px;
      background: #ffffff;
      color: #0b57d0;
      text-decoration: none;
      font-weight: 800;
      font-size: 14px;
      white-space: nowrap;
      transition: background-color 140ms ease, border-color 140ms ease, transform 140ms ease;
    }

    .back-link:hover {
      background: #f5f9ff;
      border-color: #8fb7db;
      transform: translateY(-1px);
    }

    .content {
      padding: 20px 24px 24px;
    }

    .err {
      border: 1px solid var(--danger-border);
      background: var(--danger-bg);
      color: var(--danger-text);
      border-radius: 14px;
      padding: 12px 14px;
      margin: 0 0 16px;
      font-size: 14px;
    }

    .err ul {
      margin: 8px 0 0 18px;
      padding: 0;
    }

    .form-stack {
      display: grid;
      gap: 14px;
    }

    .panel {
      border: 1px solid #dde8f4;
      border-radius: 16px;
      background: #fbfdff;
      padding: 14px;
    }

    .panel-title {
      margin: 0 0 10px;
      font-size: 13px;
      font-weight: 900;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: #0f3e63;
    }

    .row {
      margin-bottom: 12px;
    }

    .row:last-child {
      margin-bottom: 0;
    }

    label {
      display: block;
      color: var(--label);
      font-size: 12px;
      font-weight: 800;
      letter-spacing: 0.01em;
      margin-bottom: 6px;
    }

    input,
    select {
      width: 100%;
      border: 1px solid #bdd0e2;
      border-radius: 12px;
      background: #ffffff;
      color: #0f172a;
      font-size: 14px;
      font-weight: 600;
      padding: 11px 12px;
      outline: none;
      transition: border-color 140ms ease, box-shadow 140ms ease;
    }

    input:focus,
    select:focus {
      border-color: #67a2d1;
      box-shadow: 0 0 0 4px var(--focus);
    }

    input[type="file"] {
      padding: 8px;
      font-weight: 500;
    }

    .grid-2 {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px;
    }

    .muted {
      margin-top: 8px;
      color: var(--muted);
      font-size: 12.5px;
      line-height: 1.45;
    }

    .muted b,
    .muted code {
      color: #0f3e63;
      font-weight: 800;
    }

    .muted code {
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
      font-size: 12px;
      background: #edf4fb;
      border: 1px solid #d7e5f2;
      border-radius: 6px;
      padding: 1px 5px;
      display: inline-block;
      margin: 2px 2px 0 0;
    }

    .actions {
      margin-top: 4px;
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      flex-wrap: wrap;
    }

    .btn {
      border: 0;
      border-radius: 12px;
      padding: 12px 16px;
      font-size: 14px;
      font-weight: 900;
      cursor: pointer;
      transition: transform 130ms ease, filter 130ms ease, box-shadow 130ms ease;
    }

    .btn:hover {
      transform: translateY(-1px);
      filter: brightness(1.02);
    }

    .btn-main {
      color: #ffffff;
      background: linear-gradient(130deg, var(--accent) 0%, var(--accent-2) 100%);
      box-shadow: 0 8px 18px rgba(13, 79, 140, 0.28);
    }

    .btn-ghost {
      color: #0f3e63;
      background: #ffffff;
      border: 1px solid #c3d7ea;
    }

    @media (max-width: 720px) {
      body {
        padding: 12px;
      }

      .hero,
      .content {
        padding: 14px;
      }

      .hero {
        flex-direction: column;
      }

      .grid-2 {
        grid-template-columns: 1fr;
      }

      .back-link {
        margin-top: 0;
      }
    }
  </style>
</head>
<body>
  @php
    $canGenerateNow = (bool) ($isRegionalDirector ?? false);
    $submitAction = $canGenerateNow ? route('admin.certs.store') : route('admin.certs.endorse');
    $submitLabel = $canGenerateNow ? 'Create Certificates with QR Code' : 'Endorse to Regional Director';
  @endphp
  <div class="shell">
    <header class="hero">
      <div>
        <p class="eyebrow">Certificate Issuance</p>
        <h1>Create Certificate</h1>
        <p class="subtitle">
          {{ $canGenerateNow
              ? 'Regional Director can generate standardized, QR-enabled certificates directly.'
              : 'Prepare all training details and files, then endorse to the Regional Director for approval and QR generation.' }}
        </p>
      </div>
      <a class="back-link" href="{{ route('admin.certs.index') }}">← Back to Certificate Dashboard</a>
    </header>

    <div class="content">
      @if (session('success'))
        <div class="err" style="border-color:#bbf7d0;background:#ecfdf5;color:#166534;margin-bottom:16px;">
          {{ session('success') }}
        </div>
      @endif

      @if ($errors->any())
        <div class="err">
          <b>Please fix the errors:</b>
          <ul>
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <form method="POST" action="{{ $submitAction }}" enctype="multipart/form-data">
        @csrf

        <div class="form-stack">
          <section class="panel">
            <h2 class="panel-title">Document Details</h2>

            <div class="row">
              <label>Title</label>
              <input name="training_title" value="{{ old('training_title') }}" required>
            </div>

            <div class="row">
              <label>Recipient Type</label>
              <select name="recipient_type" id="recipientTypeSelect" required>
                <option value="" disabled @selected(old('recipient_type', '') === '')>Select Recipient Type</option>
                @foreach ($recipientTypes as $recipientType)
                  <option value="{{ $recipientType }}" @selected(old('recipient_type') === $recipientType)>{{ $recipientType }}</option>
                @endforeach
              </select>
            </div>

            <div class="row" id="recipientTypeOtherRow" style="{{ old('recipient_type') === 'Others' ? '' : 'display:none;' }}">
              <label>If Others, please specify recipient type</label>
              <input
                type="text"
                id="recipientTypeOtherInput"
                name="recipient_type_other"
                value="{{ old('recipient_type_other') }}"
                maxlength="255"
                {{ old('recipient_type') === 'Others' ? 'required' : '' }}
              >
            </div>

            <div class="grid-2">
              <div class="row">
                <label>Activity Type</label>
                <select name="activity_type" required>
                  <option value="" disabled @selected(old('activity_type', '') === '')>Select Activity Type</option>
                  @foreach ($activityTypes as $activityType)
                    <option value="{{ $activityType }}" @selected(old('activity_type') === $activityType)>{{ $activityType }}</option>
                  @endforeach
                </select>
              </div>

              <div class="row">
                <label>Certificate Type</label>
                <select name="certificate_type" id="certificateTypeSelect" required>
                  <option value="" disabled @selected(old('certificate_type', '') === '')>Select Certificate Type</option>
                  @foreach ($certificateTypes as $certificateType)
                    <option value="{{ $certificateType }}" @selected(old('certificate_type') === $certificateType)>{{ $certificateType }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="row">
              <label>Venue</label>
              <input name="venue" value="{{ old('venue') }}" required>
            </div>

            <div class="row">
              <label>Topic</label>
              <select name="topic" required>
                @foreach ($topics as $topic)
                  <option value="{{ $topic }}" @selected(old('topic') === $topic)>{{ $topic }}</option>
                @endforeach
              </select>
            </div>

            <div class="row" id="topicOtherRow" style="{{ old('topic') === 'Others' ? '' : 'display:none;' }}">
              <label>If Others, please specify topic</label>
              <input
                type="text"
                id="topicOtherInput"
                name="topic_other"
                value="{{ old('topic_other') }}"
                maxlength="255"
                {{ old('topic') === 'Others' ? 'required' : '' }}
              >
            </div>

            <div class="grid-2">
              <div class="row">
                <label>Date (From)</label>
                <input type="date" name="training_date_from" value="{{ old('training_date_from') }}" required>
              </div>

              <div class="row">
                <label>Date (To)</label>
                <input type="date" name="training_date_to" value="{{ old('training_date_to') }}" required>
              </div>
            </div>

            <div class="row">
              <label id="trainingHoursLabel">Number of Training Hours</label>
              <input type="number" min="1" name="number_of_training_hours" value="{{ old('number_of_training_hours') }}" required>
            </div>

            <div class="grid-2">
              <div class="row">
                <label>DOST Program</label>
                <select name="dost_program" required>
                  <option value="" disabled @selected(old('dost_program', '') === '')>Select DOST Program</option>
                  @foreach ($dostPrograms as $dostProgram)
                    <option value="{{ $dostProgram }}" @selected(old('dost_program') === $dostProgram)>{{ $dostProgram }}</option>
                  @endforeach
                </select>
              </div>

              <div class="row">
                <label id="dostProjectLabel">{{ old('dost_program') === ($setupProgramLabel ?? null) ? 'DOST Office/Province' : 'DOST Project' }}</label>
                <select name="dost_project" id="dostProjectSelect" required>
                  <option value="" disabled @selected(old('dost_project', '') === '')>Select DOST Project</option>
                  @foreach ($dostProjects as $project)
                    <option
                      value="{{ $project['name'] }}"
                      data-code="{{ $project['code'] }}"
                      data-program-prefix="{{ $project['program_prefix'] ?? '' }}"
                      @selected(old('dost_project') === $project['name'])
                    >
                      {{ $project['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="row" id="dostProgramOtherRow" style="{{ old('dost_program') === 'Others' ? '' : 'display:none;' }}">
              <label>If Others, please specify DOST Program</label>
              <input
                type="text"
                id="dostProgramOtherInput"
                name="dost_program_other"
                value="{{ old('dost_program_other') }}"
                maxlength="255"
                {{ old('dost_program') === 'Others' ? 'required' : '' }}
              >
            </div>

            <div class="grid-2">
              <div class="row">
                <label>Pillar</label>
                <select name="pillar" required>
                  <option value="" disabled @selected(old('pillar', '') === '')>Select Pillar</option>
                  @foreach ($pillars as $pillar)
                    <option value="{{ $pillar }}" @selected(old('pillar') === $pillar)>{{ $pillar }}</option>
                  @endforeach
                </select>
              </div>

              <div class="row">
                <label>Source of Funds</label>
                <select name="source_of_funds" id="sourceOfFundsSelect" required>
                  <option value="" disabled @selected(old('source_of_funds', '') === '')>Select Source of Funds</option>
                  @foreach ($sourceOfFundsOptions as $sourceOfFunds)
                    <option value="{{ $sourceOfFunds }}" @selected(old('source_of_funds') === $sourceOfFunds)>{{ $sourceOfFunds }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="row" id="projectCodeRow" style="{{ old('dost_program') === ($setupProgramLabel ?? null) ? 'display:none;' : '' }}">
              <label>Project Code</label>
              <input
                id="projectCodeInput"
                name="project_code"
                value="{{ old('project_code') }}"
                readonly
                class="readonly-input"
              >
            </div>

            <div class="row">
              <label id="trainingBudgetLabel">Training Budget</label>
              <input
                type="number"
                name="training_budget"
                min="0"
                step="0.01"
                value="{{ old('training_budget') }}"
                placeholder="e.g. 50000.00"
              >
            </div>

            <div class="row">
              <label>Expected Number of Recipients</label>
              <input type="number" min="1" name="expected_number_of_participants" value="{{ old('expected_number_of_participants') }}">
            </div>

            <div class="row">
              <label>Issuing Office/Organizing Unit</label>
              <select name="issuing_office" required>
                @php
                  $officeValue = old('issuing_office', $defaults['issuing_office']);
                  $offices = [
                    'DOST Caraga - Fields Operation Division',
                    'DOST Caraga - Financial and Administrative Services',
                    'DOST Caraga - Office of the Regional Director',
                    'DOST Caraga - Technical Support Services',
                    'DOST Caraga - Innovation Unit',
                    'DOST Caraga - Management Information Systems - Information Communication Technology',
                    'DOST Caraga - Regional Standards and Testing Laboratory',
                    'DOST Caraga - Regional Metrology Laboratory',
                    'DOST Caraga - PSTO-Agusan Del Norte',
                    'DOST Caraga - PSTO-Agusan Del Sur',
                    'DOST Caraga - PSTO-Surigao Del Norte',
                    'DOST Caraga - PSTO-Surigao Del Sur',
                    'DOST Caraga - PSTO-Province of Dinagat Island',
                  ];
                @endphp
                @foreach ($offices as $office)
                  <option value="{{ $office }}" @selected($officeValue === $office)>{{ $office }}</option>
                @endforeach
              </select>
            </div>
          </section>

          <section class="panel">
            <h2 class="panel-title">Import Files</h2>

            <div class="row">
              <label>Certificate Template PDF</label>
              <input type="file" name="certificate_pdf_shared" accept="application/pdf" required>
              <div class="muted">
                Upload one PDF template used for all imported participants.
              </div>
            </div>

            <div class="row">
              <label>Import Participants (CSV/XLSX)</label>
              <input type="file" id="participantsFile" name="participants_file" accept=".csv,.xlsx" required>
              <div class="muted">
                Participants are loaded only from this file.
                <br>
                Headers supported:
                <code>participant_name</code> <code>participant name</code> <code>name</code>
                or split name fields
                <code>first_name</code> <code>middle_initial</code> <code>last_name</code>,
                plus
                <code>email</code> <code>gender</code> <code>sex</code> <code>age</code> <code>age_range</code>
                <code>region</code> <code>province</code> <code>state</code> <code>province/state</code>
                <code>city</code> <code>municipality</code> <code>city_municipality</code>
                <code>barangay</code> <code>brgy</code>
                <code>block</code> <code>lot</code> <code>purok</code>
                <code>industry</code> <code>affiliation/sector</code>.
              </div>
            </div>
          </section>
        </div>

        <div class="actions">
          <button class="btn btn-ghost" type="submit" formaction="{{ route('admin.certs.preview') }}" formtarget="_blank">
            Preview First Participant
          </button>
          <button class="btn btn-main" type="submit">{{ $submitLabel }}</button>
        </div>
      </form>
    </div>
  </div>
  <script>
    const topicSelect = document.querySelector('select[name="topic"]');
    const topicOtherRow = document.getElementById('topicOtherRow');
    const topicOtherInput = document.getElementById('topicOtherInput');
    const recipientTypeSelect = document.getElementById('recipientTypeSelect');
    const recipientTypeOtherRow = document.getElementById('recipientTypeOtherRow');
    const recipientTypeOtherInput = document.getElementById('recipientTypeOtherInput');
    const certificateTypeSelect = document.getElementById('certificateTypeSelect');
    const dostProgramSelect = document.querySelector('select[name="dost_program"]');
    const dostProgramOtherRow = document.getElementById('dostProgramOtherRow');
    const dostProgramOtherInput = document.getElementById('dostProgramOtherInput');
    const activityTypeSelect = document.querySelector('select[name="activity_type"]');
    const trainingHoursLabel = document.getElementById('trainingHoursLabel');
    const trainingBudgetLabel = document.getElementById('trainingBudgetLabel');
    const dostProjectLabel = document.getElementById('dostProjectLabel');
    const dostProjectSelect = document.getElementById('dostProjectSelect');
    const sourceOfFundsSelect = document.getElementById('sourceOfFundsSelect');
    const projectCodeRow = document.getElementById('projectCodeRow');
    const projectCodeInput = document.getElementById('projectCodeInput');
    const automaticCertificateTypeByRecipientType = @json($automaticCertificateTypeByRecipientType ?? []);
    const certificateTypeOptions = @json($certificateTypes ?? []);
    const automaticSourceOfFundsByProgram = @json($automaticSourceOfFundsByProgram ?? []);
    const nationalRegularProgramLabel = @json($nationalRegularProgramLabel ?? null);
    const dostProgramProjectPrefixes = @json($dostProgramProjectPrefixes ?? []);
    const setupProgramLabel = @json($setupProgramLabel ?? null);
    const setupOfficeProvinces = @json($setupOfficeProvinces ?? []);
    const sourceOfFundsOptions = @json($sourceOfFundsOptions ?? []);
    const notApplicableValue = 'Not Applicable';
    const allDostProjectOptions = dostProjectSelect
      ? Array.from(dostProjectSelect.options)
        .filter((option) => option.value !== '')
        .map((option) => ({
          value: option.value,
          label: option.textContent,
          code: option.dataset.code || '',
          programPrefix: option.dataset.programPrefix || '',
        }))
      : [];
    const notApplicableProjectOption = allDostProjectOptions.find((option) => option.value === notApplicableValue)
      || {
        value: notApplicableValue,
        label: notApplicableValue,
        code: notApplicableValue,
        programPrefix: '',
      };

    const toggleTopicOther = () => {
      if (!topicSelect || !topicOtherRow || !topicOtherInput) {
        return;
      }

      const isOthers = topicSelect.value === 'Others';
      topicOtherRow.style.display = isOthers ? '' : 'none';
      topicOtherInput.required = isOthers;
    };

    if (topicSelect) {
      topicSelect.addEventListener('change', toggleTopicOther);
      toggleTopicOther();
    }

    const toggleRecipientTypeOther = () => {
      if (!recipientTypeSelect || !recipientTypeOtherRow || !recipientTypeOtherInput) {
        return;
      }

      const isOthers = recipientTypeSelect.value === 'Others';
      recipientTypeOtherRow.style.display = isOthers ? '' : 'none';
      recipientTypeOtherInput.required = isOthers;
    };

    const buildCertificateTypeOptions = (availableOptions, previousValue, lockSelection = false) => {
      if (!certificateTypeSelect) {
        return;
      }

      certificateTypeSelect.innerHTML = '';

      let placeholderOption = null;
      if (!lockSelection) {
        placeholderOption = document.createElement('option');
        placeholderOption.value = '';
        placeholderOption.textContent = 'Select Certificate Type';
        placeholderOption.disabled = true;
        placeholderOption.selected = true;
        certificateTypeSelect.appendChild(placeholderOption);
      }

      availableOptions.forEach((optionValue) => {
        const option = document.createElement('option');
        option.value = optionValue;
        option.textContent = optionValue;
        option.selected = lockSelection
          ? optionValue === availableOptions[0]
          : optionValue === previousValue;
        if (option.selected && placeholderOption) {
          placeholderOption.selected = false;
        }
        certificateTypeSelect.appendChild(option);
      });

      if (lockSelection && availableOptions[0]) {
        certificateTypeSelect.value = availableOptions[0];
        return;
      }

      if (!availableOptions.includes(previousValue)) {
        certificateTypeSelect.value = '';
      }
    };

    const syncCertificateTypeOptions = () => {
      if (!recipientTypeSelect || !certificateTypeSelect) {
        return;
      }

      const selectedRecipientType = recipientTypeSelect.value;
      const previousValue = certificateTypeSelect.value;
      const automaticValue = automaticCertificateTypeByRecipientType[selectedRecipientType] || '';

      if (automaticValue) {
        buildCertificateTypeOptions([automaticValue], previousValue, true);
        return;
      }

      buildCertificateTypeOptions(certificateTypeOptions, previousValue);
    };

    if (recipientTypeSelect) {
      recipientTypeSelect.addEventListener('change', toggleRecipientTypeOther);
      recipientTypeSelect.addEventListener('change', syncCertificateTypeOptions);
      toggleRecipientTypeOther();
      syncCertificateTypeOptions();
    }

    const toggleDostProgramOther = () => {
      if (!dostProgramSelect || !dostProgramOtherRow || !dostProgramOtherInput) {
        return;
      }

      const isOthers = dostProgramSelect.value === 'Others';
      dostProgramOtherRow.style.display = isOthers ? '' : 'none';
      dostProgramOtherInput.required = isOthers;
    };

    if (dostProgramSelect) {
      dostProgramSelect.addEventListener('change', toggleDostProgramOther);
      toggleDostProgramOther();
    }

    const syncActivityLabels = () => {
      if (!trainingHoursLabel && !trainingBudgetLabel) {
        return;
      }

      const activityType = activityTypeSelect ? activityTypeSelect.value.trim() : '';
      if (trainingHoursLabel) {
        trainingHoursLabel.textContent = activityType
          ? `Number of ${activityType} Hours`
          : 'Number of Training Hours';
      }
      if (trainingBudgetLabel) {
        trainingBudgetLabel.textContent = activityType
          ? `${activityType} Budget`
          : 'Training Budget';
      }
    };

    if (activityTypeSelect) {
      activityTypeSelect.addEventListener('change', syncActivityLabels);
    }
    syncActivityLabels();

    const syncProjectCode = () => {
      if (!dostProjectSelect || !projectCodeInput) {
        return;
      }

      const selectedOption = dostProjectSelect.options[dostProjectSelect.selectedIndex];
      const code = selectedOption ? (selectedOption.dataset.code || '') : '';
      projectCodeInput.value = code;
    };

    const buildDostProjectOptions = (availableOptions, placeholderText, previousValue, lockSelection = false) => {
      if (!dostProjectSelect) {
        return;
      }

      dostProjectSelect.innerHTML = '';

      let placeholderOption = null;
      if (!lockSelection) {
        placeholderOption = document.createElement('option');
        placeholderOption.value = '';
        placeholderOption.textContent = placeholderText;
        placeholderOption.disabled = true;
        placeholderOption.selected = true;
        dostProjectSelect.appendChild(placeholderOption);
      }

      availableOptions.forEach((optionData) => {
        const option = document.createElement('option');
        option.value = optionData.value;
        option.textContent = optionData.label;
        option.dataset.code = optionData.code;
        option.dataset.programPrefix = optionData.programPrefix;
        option.selected = lockSelection
          ? optionData.value === availableOptions[0]?.value
          : optionData.value === previousValue;
        if (option.selected && placeholderOption) {
          placeholderOption.selected = false;
        }
        dostProjectSelect.appendChild(option);
      });

      if (lockSelection && availableOptions[0]) {
        dostProjectSelect.value = availableOptions[0].value;
        return;
      }

      if (!availableOptions.some((option) => option.value === previousValue)) {
        dostProjectSelect.value = '';
      }
    };

    const buildSourceOfFundsOptions = (availableOptions, previousValue, lockSelection = false) => {
      if (!sourceOfFundsSelect) {
        return;
      }

      sourceOfFundsSelect.innerHTML = '';

      let placeholderOption = null;
      if (!lockSelection) {
        placeholderOption = document.createElement('option');
        placeholderOption.value = '';
        placeholderOption.textContent = 'Select Source of Funds';
        placeholderOption.disabled = true;
        placeholderOption.selected = true;
        sourceOfFundsSelect.appendChild(placeholderOption);
      }

      availableOptions.forEach((optionValue) => {
        const option = document.createElement('option');
        option.value = optionValue;
        option.textContent = optionValue;
        option.selected = lockSelection
          ? optionValue === availableOptions[0]
          : optionValue === previousValue;
        if (option.selected && placeholderOption) {
          placeholderOption.selected = false;
        }
        sourceOfFundsSelect.appendChild(option);
      });

      if (lockSelection && availableOptions[0]) {
        sourceOfFundsSelect.value = availableOptions[0];
        return;
      }

      if (!availableOptions.includes(previousValue)) {
        sourceOfFundsSelect.value = '';
      }
    };

    const syncSourceOfFundsOptions = () => {
      if (!dostProgramSelect || !sourceOfFundsSelect) {
        return;
      }

      const selectedProgram = dostProgramSelect.value;
      const previousValue = sourceOfFundsSelect.value;
      const automaticValue = automaticSourceOfFundsByProgram[selectedProgram] || '';

      if (automaticValue) {
        buildSourceOfFundsOptions([automaticValue], previousValue, true);
        return;
      }

      buildSourceOfFundsOptions(sourceOfFundsOptions, previousValue);
    };

    const syncDostProjectOptions = () => {
      if (!dostProgramSelect || !dostProjectSelect) {
        return;
      }

      const selectedProgram = dostProgramSelect.value;
      const previousValue = dostProjectSelect.value;
      const isSetup = selectedProgram === setupProgramLabel;
      const isNationalRegular = selectedProgram === nationalRegularProgramLabel;

      if (dostProjectLabel) {
        dostProjectLabel.textContent = isSetup ? 'DOST Office/Province' : 'DOST Project';
      }

      if (projectCodeRow) {
        projectCodeRow.style.display = isSetup ? 'none' : '';
      }

      if (isSetup) {
        buildDostProjectOptions(
          setupOfficeProvinces.map((office) => ({
            value: office,
            label: office,
            code: '',
            programPrefix: '',
          })),
          'Select DOST Office/Province',
          previousValue
        );
        if (projectCodeInput) {
          projectCodeInput.value = '';
        }
        return;
      }

      if (isNationalRegular) {
        buildDostProjectOptions([notApplicableProjectOption], 'Select DOST Project', previousValue, true);
        syncProjectCode();
        return;
      }

      const requiredPrefix = dostProgramProjectPrefixes[selectedProgram] || '';
      const matchingOptions = requiredPrefix === ''
        ? []
        : allDostProjectOptions.filter((option) => option.programPrefix === requiredPrefix);
      const availableOptions = matchingOptions.length > 0
        ? matchingOptions
        : allDostProjectOptions;

      buildDostProjectOptions(availableOptions, 'Select DOST Project', previousValue);

      syncProjectCode();
    };

    if (dostProjectSelect) {
      dostProjectSelect.addEventListener('change', syncProjectCode);
    }

    if (dostProgramSelect) {
      dostProgramSelect.addEventListener('change', syncDostProjectOptions);
      dostProgramSelect.addEventListener('change', syncSourceOfFundsOptions);
      syncDostProjectOptions();
      syncSourceOfFundsOptions();
    } else {
      syncProjectCode();
    }
  </script>
</body>
</html>
