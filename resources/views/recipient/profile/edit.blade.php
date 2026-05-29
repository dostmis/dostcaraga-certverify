<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Profile — DOST Caraga CERTiFY</title>
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
            max-width: 960px;
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
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
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

        .form-section {
            margin-bottom: 24px;
        }

        .form-section:last-child {
            margin-bottom: 0;
        }

        .form-section-title {
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            margin-bottom: 14px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f1f5f9;
        }

        .form-row {
            display: grid;
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-row-2 { grid-template-columns: 1fr 1fr; }
        .form-row-3 { grid-template-columns: 1fr 1fr 1fr; }

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

        .field-hint {
            margin-top: 4px;
            font-size: 11px;
            color: #0891B2;
            font-weight: 600;
            line-height: 1.4;
        }

        select.field-input {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 36px;
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
            border: 1px solid #cbd5e1;
            border-radius: 999px;
            background: #f8fafc;
            padding: 7px 14px;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            cursor: pointer;
            transition: all 0.15s;
        }

        .choice-pill:hover {
            border-color: #0891B2;
            background: #f0fdff;
        }

        .choice-pill input {
            width: 14px;
            height: 14px;
            accent-color: #0891B2;
            margin: 0;
        }

        .choice-pill input[type="radio"] {
            border-radius: 50%;
        }

        .checkbox-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .save-bar {
            display: flex;
            justify-content: flex-end;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            margin-top: 8px;
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

        @media (max-width: 640px) {
            .form-row-2, .form-row-3 {
                grid-template-columns: 1fr;
            }
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
                <a href="{{ route('recipient.profile.edit') }}" class="profile-tab active">Edit Profile</a>
                <a href="{{ route('recipient.profile.password') }}" class="profile-tab">Password</a>
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

        <form method="POST" action="{{ route('recipient.profile.update') }}">
            @csrf
            @method('PUT')

            <div class="profile-card">
                <div class="profile-card-header">
                    <h2>Personal Information</h2>
                </div>
                <div class="profile-card-body">

                    {{-- Basic Info --}}
                    <div class="form-section">
                        <div class="form-section-title">A. Basic Information</div>
                        <div class="form-row form-row-3">
                            <div>
                                <label class="field-label">First Name</label>
                                <input name="first_name" type="text" class="field-input" value="{{ old('first_name', $recipient->first_name) }}" required>
                            </div>
                            <div>
                                <label class="field-label">Middle Initial (Optional)</label>
                                <input name="middle_initial" type="text" class="field-input" value="{{ old('middle_initial', $recipient->middle_initial) }}" maxlength="10">
                            </div>
                            <div>
                                <label class="field-label">Last Name</label>
                                <input name="last_name" type="text" class="field-input" value="{{ old('last_name', $recipient->last_name) }}" required>
                            </div>
                        </div>
                        <div class="form-row form-row-2">
                            <div>
                                <label class="field-label">Email</label>
                                <input name="email" type="email" class="field-input" value="{{ old('email', $recipient->email) }}" required placeholder="your.email@example.com">
                                <p class="field-hint">This serves as your certificate repository account.</p>
                            </div>
                            <div>
                                <label class="field-label">Contact Number</label>
                                <input name="contact_number" type="tel" class="field-input" value="{{ old('contact_number', $recipient->contact_number) }}" placeholder="e.g., 09171234567">
                            </div>
                        </div>
                    </div>

                    {{-- Geographic --}}
                    <div class="form-section">
                        <div class="form-section-title">B. Geographic Coverage</div>
                        <div class="form-row form-row-3">
                            <div>
                                <label class="field-label">Region</label>
                                <select class="psgc-region field-input" name="region" data-old="{{ old('region', $recipient->region) }}">
                                    <option value="">-- Select --</option>
                                </select>
                                <span class="psgc-loading text-sm text-gray-500 hidden" style="font-size:11px;color:#94a3b8;">Loading...</span>
                            </div>
                            <div>
                                <label class="field-label">Province</label>
                                <select class="psgc-province field-input" name="province" data-old="{{ old('province', $recipient->province) }}" disabled>
                                    <option value="">-- Select --</option>
                                </select>
                                <span class="psgc-loading text-sm text-gray-500 hidden" style="font-size:11px;color:#94a3b8;">Loading...</span>
                            </div>
                            <div>
                                <label class="field-label">City / Municipality</label>
                                <select class="psgc-city field-input" name="city_municipality" data-old="{{ old('city_municipality', $recipient->city_municipality) }}" disabled>
                                    <option value="">-- Select --</option>
                                </select>
                                <span class="psgc-loading text-sm text-gray-500 hidden" style="font-size:11px;color:#94a3b8;">Loading...</span>
                            </div>
                        </div>
                        <div class="form-row form-row-2">
                            <div>
                                <label class="field-label">Barangay</label>
                                <select class="psgc-brgy field-input" name="barangay" data-old="{{ old('barangay', $recipient->barangay) }}" disabled>
                                    <option value="">-- Select --</option>
                                </select>
                                <span class="psgc-loading text-sm text-gray-500 hidden" style="font-size:11px;color:#94a3b8;">Loading...</span>
                            </div>
                            <div>
                                <label class="field-label">Block / Lot / Purok (Optional)</label>
                                <input name="block_lot_purok" type="text" class="field-input" value="{{ old('block_lot_purok', $recipient->block_lot_purok) }}">
                            </div>
                        </div>
                    </div>

                    {{-- Demographic --}}
                    <div class="form-section">
                        <div class="form-section-title">C. Demographic Information</div>
                        <div class="form-row form-row-3">
                            <div>
                                <label class="field-label">Sex</label>
                                <select name="gender" class="field-input">
                                    <option value="">-- Select --</option>
                                    <option value="Male" @selected(old('gender', $recipient->gender) === 'Male')>Male</option>
                                    <option value="Female" @selected(old('gender', $recipient->gender) === 'Female')>Female</option>
                                </select>
                            </div>
                            <div>
                                <label class="field-label">Birthdate</label>
                                <input name="birthdate" type="date" class="field-input" value="{{ old('birthdate', $recipient->birthdate?->format('Y-m-d')) }}">
                            </div>
                            <div>
                                <label class="field-label">Age Range</label>
                                <select name="age_range" class="field-input">
                                    <option value="">-- Select --</option>
                                    <option value="18-24 years old" @selected(old('age_range', $recipient->age_range) === '18-24 years old')>18-24 years old</option>
                                    <option value="25-34 years old" @selected(old('age_range', $recipient->age_range) === '25-34 years old')>25-34 years old</option>
                                    <option value="35-44 years old" @selected(old('age_range', $recipient->age_range) === '35-44 years old')>35-44 years old</option>
                                    <option value="45-54 years old" @selected(old('age_range', $recipient->age_range) === '45-54 years old')>45-54 years old</option>
                                    <option value="55-64 years old" @selected(old('age_range', $recipient->age_range) === '55-64 years old')>55-64 years old</option>
                                    <option value="65 years old and above" @selected(old('age_range', $recipient->age_range) === '65 years old and above')>65 years old and above</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row form-row-2" style="margin-top:16px;">
                            <div>
                                <label class="field-label">Person with Disability (PWD)?</label>
                                <div class="choice-inline" style="padding-top:4px;">
                                    @foreach (['Yes', 'No'] as $opt)
                                        <label class="choice-pill">
                                            <input type="radio" name="pwd_status" value="{{ $opt }}" @checked(old('pwd_status', $recipient->pwd_status) === $opt)>
                                            <span>{{ $opt }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Socio-Economic --}}
                    <div class="form-section">
                        <div class="form-section-title">D. Socio-Economic Profile</div>
                        <div class="form-row form-row-2">
                            <div>
                                <label class="field-label">4PS Beneficiary?</label>
                                <div class="choice-inline" style="padding-top:4px;">
                                    @foreach (['Yes', 'No'] as $opt)
                                        <label class="choice-pill">
                                            <input type="radio" name="is_4ps_beneficiary" value="{{ $opt }}" @checked(old('is_4ps_beneficiary', $recipient->is_4ps_beneficiary) === $opt)>
                                            <span>{{ $opt }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <div>
                                <label class="field-label">Part of an ELCAC Community?</label>
                                <div class="choice-inline" style="padding-top:4px;">
                                    @foreach (['Yes', 'No'] as $opt)
                                        <label class="choice-pill">
                                            <input type="radio" name="is_elcac_community" value="{{ $opt }}" @checked(old('is_elcac_community', $recipient->is_elcac_community) === $opt)>
                                            <span>{{ $opt }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Organization --}}
                    <div class="form-section">
                        <div class="form-section-title">E. Organization Profile</div>
                        <div class="form-row form-row-3">
                            <div>
                                <label class="field-label">Organization Name</label>
                                <input name="organization_name" type="text" class="field-input" value="{{ old('organization_name', $recipient->organization_name) }}">
                            </div>
                            <div>
                                <label class="field-label">Affiliation / Sector</label>
                                <select name="industry" class="field-input">
                                    <option value="">-- Select --</option>
                                    <option value="Student" @selected(old('industry', $recipient->industry) === 'Student')>Student</option>
                                    <option value="Micro, Small, and Medium Enterprise (MSME)" @selected(old('industry', $recipient->industry) === 'Micro, Small, and Medium Enterprise (MSME)')>MSME</option>
                                    <option value="Local Government Unit (LGU)" @selected(old('industry', $recipient->industry) === 'Local Government Unit (LGU)')>LGU</option>
                                    <option value="National Government Agency (NGA)" @selected(old('industry', $recipient->industry) === 'National Government Agency (NGA)')>NGA</option>
                                    <option value="Non-Government Organization (NGO)" @selected(old('industry', $recipient->industry) === 'Non-Government Organization (NGO)')>NGO</option>
                                    <option value="Government-Owned and Controlled Corporation (GOCC)" @selected(old('industry', $recipient->industry) === 'Government-Owned and Controlled Corporation (GOCC)')>GOCC</option>
                                    <option value="Civil Society Organization (CSO)" @selected(old('industry', $recipient->industry) === 'Civil Society Organization (CSO)')>CSO</option>
                                    <option value="People's Organization (PO)" @selected(old('industry', $recipient->industry) === "People's Organization (PO)")>People's Organization (PO)</option>
                                    <option value="Private Sector" @selected(old('industry', $recipient->industry) === 'Private Sector')>Private Sector</option>
                                    <option value="Private Individual" @selected(old('industry', $recipient->industry) === 'Private Individual')>Private Individual</option>
                                    <option value="Academe" @selected(old('industry', $recipient->industry) === 'Academe')>Academe</option>
                                    <option value="Others" @selected(old('industry', $recipient->industry) === 'Others')>Others</option>
                                </select>
                            </div>
                            <div>
                                <label class="field-label">Position / Designation</label>
                                <input name="position_designation" type="text" class="field-input" value="{{ old('position_designation', $recipient->position_designation) }}">
                            </div>
                        </div>
                    </div>

                    {{-- DOST Engagement --}}
                    <div class="form-section">
                        <div class="form-section-title">F. DOST Engagement Profile</div>
                        <div>
                            <label class="field-label">Beneficiary/Recipient of DOST Programs?</label>
                            <div class="checkbox-grid" style="margin-top:4px;">
                                @php $beneficiaryPrograms = old('dost_program_beneficiary', $recipient->dost_program_beneficiary ?? []); @endphp
                                @foreach (['SETUP', 'S&T Scholarship', 'Community Empowerment thru S&T (CEST)', 'Small Enterprise Technology Upgrading Program', 'Technical Consultancy', 'Grants-in-Aid (GIA)', 'Not Applicable'] as $prog)
                                    <label class="choice-pill">
                                        <input type="checkbox" name="dost_program_beneficiary[]" value="{{ $prog }}" @checked(in_array($prog, $beneficiaryPrograms))>
                                        <span>{{ $prog }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div style="margin-top:14px;">
                            <label class="field-label">Directly Employed under any of the following Programs?</label>
                            <div class="checkbox-grid" style="margin-top:4px;">
                                @php $employedPrograms = old('directly_employed_programs', $recipient->directly_employed_programs ?? []); @endphp
                                @foreach (['SETUP', 'S&T Scholarship', 'CEST', 'Grants-in-Aid (GIA)', 'Not Applicable'] as $prog)
                                    <label class="choice-pill">
                                        <input type="checkbox" name="directly_employed_programs[]" value="{{ $prog }}" @checked(in_array($prog, $employedPrograms))>
                                        <span>{{ $prog }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Participation History --}}
                    <div class="form-section">
                        <div class="form-section-title">G. Participation History</div>
                        <div>
                            <label class="field-label">Previously attended a DOST Caraga Training?</label>
                            <div class="choice-inline" style="margin-top:4px;">
                                @foreach (['Yes', 'No'] as $opt)
                                    <label class="choice-pill">
                                        <input type="radio" name="has_attended_dost_training" value="{{ $opt }}" @checked(old('has_attended_dost_training', $recipient->has_attended_dost_training) === $opt)>
                                        <span>{{ $opt }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Service Interest --}}
                    <div class="form-section">
                        <div class="form-section-title">H. Service Interest</div>
                        <div>
                            <label class="field-label">Interested in Availing DOST Services?</label>
                            <div class="checkbox-grid" style="margin-top:4px;">
                                @php $interestedServices = old('interested_dost_services', $recipient->interested_dost_services ?? []); @endphp
                                @foreach (['Technical Consultancy', 'Laboratory Testing', 'Packaging & Labeling', 'S&T Training', 'Technology Transfer', 'Others'] as $svc)
                                    <label class="choice-pill">
                                        <input type="checkbox" name="interested_dost_services[]" value="{{ $svc }}" @checked(in_array($svc, $interestedServices))>
                                        <span>{{ $svc }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div style="margin-top:12px;" id="interestedServiceOtherWrap" style="{{ in_array('Others', $interestedServices) ? '' : 'display:none;' }}">
                            <label class="field-label">If Others, please specify</label>
                            <input name="interested_dost_services_other" type="text" class="field-input" value="{{ old('interested_dost_services_other', $recipient->interested_dost_services_other) }}" maxlength="255" style="max-width:400px;">
                        </div>
                    </div>

                </div>
            </div>

            <div class="save-bar" style="margin-top:16px;">
                <button type="submit" class="btn-save">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" style="width:16px;height:16px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    Save Changes
                </button>
            </div>
        </form>
    </div>

    {{-- PSGC Cascading Dropdowns (reused from participant intake) --}}
    <script>
    (function() {
        const regionSel   = document.querySelector('.psgc-region');
        const provinceSel = document.querySelector('.psgc-province');
        const citySel     = document.querySelector('.psgc-city');
        const brgySel     = document.querySelector('.psgc-brgy');

        if (!regionSel || !provinceSel || !citySel || !brgySel) return;

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
            opt.textContent = msg || 'Unable to load.';
            opt.disabled = true;
            sel.appendChild(opt);
            sel.disabled = true;
        };

        const populateSelect = (sel, items, placeholder) => {
            clearSelect(sel, placeholder);
            items.forEach(item => {
                const opt = document.createElement('option');
                opt.value = item.name;
                opt.textContent = item.name;
                opt.dataset.psgcCode = item.psgc_code;
                sel.appendChild(opt);
            });
        };

        const fetchFromProxy = async (endpoint, params = {}) => {
            const url = new URL(endpoint, window.location.origin);
            Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
            const res = await fetch(url.toString());
            if (!res.ok) return [];
            const data = await res.json();
            return Array.isArray(data) ? data : [];
        };

        const selectedValue = (sel) => {
            const opt = sel.selectedOptions[0];
            return opt ? opt.value : '';
        };

        const selectedPsgcCode = (sel) => {
            const opt = sel.selectedOptions[0];
            return opt ? (opt.dataset.psgcCode || '') : '';
        };

        const loadRegions = async () => {
            showLoading(regionSel);
            try {
                const items = await fetchFromProxy('/api/psgc/regions');
                if (items.length === 0) { showError(regionSel, 'Unable to load regions.'); return []; }
                populateSelect(regionSel, items, '-- Select region --');
                return items;
            } catch (e) {
                showError(regionSel, 'Unable to load regions.');
                return [];
            } finally { hideLoading(regionSel); }
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
                const items = await fetchFromProxy('/api/psgc/provinces', { reg_code: regPsgcCode });
                if (items.length === 0) { showError(provinceSel, 'Unable to load provinces.'); return []; }
                populateSelect(provinceSel, items, '-- Select province --');
                provinceSel.disabled = false;
                return items;
            } catch (e) {
                showError(provinceSel, 'Unable to load provinces.');
                return [];
            } finally { hideLoading(provinceSel); }
        };

        const loadCities = async (provinceName) => {
            clearSelect(citySel, '-- Select city/municipality --');
            clearSelect(brgySel, '-- Select barangay --');
            brgySel.disabled = true;
            showLoading(citySel);
            citySel.disabled = true;
            try {
                const items = await fetchFromProxy('/api/psgc/municipalities', { province_name: provinceName });
                if (items.length === 0) { showError(citySel, 'Unable to load cities.'); return []; }
                populateSelect(citySel, items, '-- Select city/municipality --');
                citySel.disabled = false;
                return items;
            } catch (e) {
                showError(citySel, 'Unable to load cities.');
                return [];
            } finally { hideLoading(citySel); }
        };

        const loadBarangays = async (cityName, provinceName) => {
            clearSelect(brgySel, '-- Select barangay --');
            showLoading(brgySel);
            brgySel.disabled = true;
            try {
                const items = await fetchFromProxy('/api/psgc/barangays', { city_name: cityName, province_name: provinceName });
                if (items.length === 0) { showError(brgySel, 'Unable to load barangays.'); return []; }
                populateSelect(brgySel, items, '-- Select barangay --');
                brgySel.disabled = false;
                return items;
            } catch (e) {
                showError(brgySel, 'Unable to load barangays.');
                return [];
            } finally { hideLoading(brgySel); }
        };

        regionSel.addEventListener('change', async function () {
            const code = selectedPsgcCode(this);
            if (code) { await loadProvinces(code); } else {
                clearSelect(provinceSel, '-- Select province --');
                clearSelect(citySel, '-- Select city/municipality --');
                clearSelect(brgySel, '-- Select barangay --');
                provinceSel.disabled = true; citySel.disabled = true; brgySel.disabled = true;
            }
        });

        provinceSel.addEventListener('change', async function () {
            const name = selectedValue(this);
            if (name) { await loadCities(name); } else {
                clearSelect(citySel, '-- Select city/municipality --');
                clearSelect(brgySel, '-- Select barangay --');
                citySel.disabled = true; brgySel.disabled = true;
            }
        });

        citySel.addEventListener('change', async function () {
            const cityName = selectedValue(this);
            const provinceName = selectedValue(provinceSel);
            if (cityName && provinceName) { await loadBarangays(cityName, provinceName); } else {
                clearSelect(brgySel, '-- Select barangay --'); brgySel.disabled = true;
            }
        });

        // Repopulate from old() values
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

        loadRegions().then(() => repopulateOldValues());

        // "Others" toggle for service interest
        const serviceChecks = document.querySelectorAll('input[name="interested_dost_services[]"]');
        const otherWrap = document.getElementById('interestedServiceOtherWrap');
        const otherInput = document.querySelector('input[name="interested_dost_services_other"]');

        const toggleOther = () => {
            if (!otherWrap || !otherInput) return;
            const hasOthers = Array.from(serviceChecks).some(box => box.value === 'Others' && box.checked);
            otherWrap.style.display = hasOthers ? '' : 'none';
            if (!hasOthers) otherInput.value = '';
        };

        if (serviceChecks.length) {
            serviceChecks.forEach(box => box.addEventListener('change', toggleOther));
            toggleOther();
        }
    })();
    </script>
</body>
</html>
