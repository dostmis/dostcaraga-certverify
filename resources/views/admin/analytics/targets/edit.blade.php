<x-admin-layout title="Program targets">
  <div class="an-scope tg">
    @include('admin.analytics._tokens')

    <header class="tg-head">
      <div>
        <a class="tg-back" href="{{ route('admin.dashboard') }}">← Back to dashboard</a>
        <h1>Program targets</h1>
        <p class="tg-sub">
          Annual targets per DOST program. The dashboard's <strong>Pace</strong> chapter
          compares actuals against these and projects year-end at the current rate.
        </p>
      </div>

      <form method="GET" action="{{ route('admin.analytics.targets.edit') }}" class="tg-year">
        <label for="tg-year-select">Planning year</label>
        <div class="tg-year-row">
          <select id="tg-year-select" name="year" onchange="this.form.submit()">
            @foreach ($years as $y)
              <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
            @endforeach
          </select>
          <noscript><button type="submit" class="an-btn">Go</button></noscript>
        </div>
      </form>
    </header>

    @if (session('status'))
      <div class="an-note an-note-ok" role="status">✓ {{ session('status') }}</div>
    @endif

    @if ($errors->any())
      <div class="an-note an-note-error" role="alert">
        <strong>Nothing was saved.</strong>
        <ul class="tg-errors">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    @if ($copyFrom !== null && $copyFrom !== $year)
      <div class="an-note" role="status">
        Showing <strong>{{ $copyFrom }}</strong> targets prefilled into <strong>{{ $year }}</strong>.
        Nothing is saved until you press <strong>Save targets</strong>.
      </div>
    @endif

    <div class="tg-bar">
      <p class="tg-meta">
        @if ($lastUpdated)
          Last saved {{ $lastUpdated->diffForHumans() }}@if ($lastUpdatedBy) by {{ $lastUpdatedBy }}@endif.
        @else
          No targets saved for {{ $year }} yet.
        @endif
      </p>
      <a class="an-btn" href="{{ route('admin.analytics.targets.edit', ['year' => $year, 'copy_from' => $year - 1]) }}">
        Prefill from {{ $year - 1 }}
      </a>
    </div>

    <form method="POST" action="{{ route('admin.analytics.targets.update') }}"
          class="an-card tg-card an-brackets"
          x-data="{ extra: [], nextKey: 0 }">
      <span class="an-bk an-bk-tl" aria-hidden="true"></span>
      <span class="an-bk an-bk-tr" aria-hidden="true"></span>
      <span class="an-bk an-bk-bl" aria-hidden="true"></span>
      <span class="an-bk an-bk-br" aria-hidden="true"></span>
      @csrf
      <input type="hidden" name="period_year" value="{{ $year }}">

      <div class="tg-scroll">
        <table class="an-table tg-table">
          <caption class="tg-caption">
            Leave a field blank for “no target”. A blank row clears any target already saved for that program.
          </caption>
          <thead>
            <tr>
              <th scope="col">DOST program</th>
              <th scope="col" class="an-num">Certificates</th>
              <th scope="col" class="an-num">People reached</th>
              <th scope="col">Notes <span class="tg-opt">optional</span></th>
            </tr>
          </thead>
          <tbody>
            @foreach ($rows as $i => $row)
              <tr>
                <th scope="row" class="tg-prog">
                  <span class="tg-swatch" aria-hidden="true"></span>
                  <span class="tg-prog-name">{{ $row['program_code'] }}</span>
                  @unless ($row['is_canonical'])
                    <span class="tg-tag">custom</span>
                  @endunless
                  <input type="hidden" name="rows[{{ $i }}][program_code]" value="{{ $row['program_code'] }}">
                </th>
                <td class="an-num">
                  <input class="tg-input" type="number" min="0" step="1" inputmode="numeric"
                         name="rows[{{ $i }}][target_certificates]"
                         value="{{ old("rows.$i.target_certificates", $row['target_certificates']) }}"
                         aria-label="Certificate target for {{ $row['program_code'] }}">
                </td>
                <td class="an-num">
                  <input class="tg-input" type="number" min="0" step="1" inputmode="numeric"
                         name="rows[{{ $i }}][target_people]"
                         value="{{ old("rows.$i.target_people", $row['target_people']) }}"
                         aria-label="People-reached target for {{ $row['program_code'] }}">
                </td>
                <td>
                  <input class="tg-input tg-input-text" type="text" maxlength="1000"
                         name="rows[{{ $i }}][notes]"
                         value="{{ old("rows.$i.notes", $row['notes']) }}"
                         placeholder="Why this number"
                         aria-label="Notes for {{ $row['program_code'] }}">
                </td>
              </tr>
            @endforeach

            {{-- Programs with no certificates yet: targets are normally set
                 before the first certificate exists, so a program has to be
                 addable by hand. --}}
            <template x-for="(row, n) in extra" :key="row.key">
              <tr>
                <th scope="row" class="tg-prog">
                  <span class="tg-swatch" aria-hidden="true"></span>
                  <input class="tg-input tg-input-text" type="text" maxlength="255" required
                         :name="`rows[${ {{ $rows->count() }} + n }][program_code]`"
                         x-model="row.program"
                         placeholder="New program name"
                         aria-label="New program name">
                </th>
                <td class="an-num">
                  <input class="tg-input" type="number" min="0" step="1" inputmode="numeric"
                         :name="`rows[${ {{ $rows->count() }} + n }][target_certificates]`"
                         aria-label="Certificate target for the new program">
                </td>
                <td class="an-num">
                  <input class="tg-input" type="number" min="0" step="1" inputmode="numeric"
                         :name="`rows[${ {{ $rows->count() }} + n }][target_people]`"
                         aria-label="People-reached target for the new program">
                </td>
                <td class="tg-row-actions">
                  <input class="tg-input tg-input-text" type="text" maxlength="1000"
                         :name="`rows[${ {{ $rows->count() }} + n }][notes]`"
                         placeholder="Why this number"
                         aria-label="Notes for the new program">
                  <button type="button" class="tg-remove" @click="extra.splice(n, 1)"
                          aria-label="Remove this program row">×</button>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <div class="tg-actions">
        <button type="button" class="an-btn" @click="extra.push({ key: nextKey++, program: '' })">
          + Add a program
        </button>
        <button type="submit" class="an-btn an-btn-secondary">Save targets for {{ $year }}</button>
      </div>
    </form>
  </div>

  <style>
    .tg{max-width:1060px;margin:0 auto;padding:24px 18px 64px;min-height:100vh}
    .tg-head{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;flex-wrap:wrap;margin-bottom:20px}
    .tg-back{font-size:12.5px;font-weight:600;color:var(--ink-2);text-decoration:none}
    .tg-back:hover{color:var(--accent)}
    .tg-head h1{font-family:var(--font-ui);font-size:23px;font-weight:700;letter-spacing:-.015em;margin:8px 0 0;color:var(--ink)}
    .tg-sub{font-size:13.5px;line-height:1.6;color:var(--ink-2);margin:8px 0 0;max-width:64ch}
    .tg-sub strong{color:var(--ink);font-weight:650}
    .tg-year label{
      display:block;font-family:var(--font-ui);font-size:11px;font-weight:600;
      text-transform:uppercase;letter-spacing:.06em;color:var(--ink-muted);margin-bottom:6px;
    }
    .tg-year-row{display:flex;gap:8px}
    .tg-year select{
      min-height:38px;background:var(--surface-3);color:var(--ink);
      border:1px solid var(--border-strong);border-radius:var(--r-sm);
      padding:7px 32px 7px 12px;font-family:var(--font-ui);font-size:14px;font-weight:650;
      appearance:none;-webkit-appearance:none;
      background-image:linear-gradient(45deg,transparent 50%,currentColor 50%),
                       linear-gradient(135deg,currentColor 50%,transparent 50%);
      background-position:calc(100% - 17px) 16px,calc(100% - 12px) 16px;
      background-size:5px 5px,5px 5px;background-repeat:no-repeat;
    }
    .tg-year select:hover{border-color:var(--accent)}
    .tg-year select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-ring)}
    .tg-errors{margin:6px 0 0;padding-left:18px}
    .tg-bar{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;margin:0 0 12px}
    .tg-meta{font-size:12.5px;color:var(--ink-muted);margin:0}
    .tg-card{padding:4px 18px 18px;position:relative}
    .tg-scroll{overflow-x:auto}
    .tg-caption{caption-side:top;text-align:left;font-size:12.5px;color:var(--ink-muted);padding:14px 8px 12px}
    .tg-table{min-width:740px}
    .tg-prog{
      display:flex;align-items:center;gap:10px;max-width:352px;
      font-family:var(--font-ui)!important;font-size:13px!important;font-weight:600!important;
      color:var(--ink)!important;text-transform:none!important;letter-spacing:0!important;
    }
    .tg-prog-name{overflow-wrap:anywhere}
    /* The swatch cycles the six chapter accents so the grid reads as colorful
       without implying the color encodes anything. Driven by row position so
       it covers both server-rendered and Alpine-added rows. */
    .tg-swatch{flex:0 0 auto;width:3px;height:22px;border-radius:2px;background:var(--sw);--sw:var(--ch-reach)}
    .tg-table tbody tr:nth-child(6n+2) .tg-swatch{--sw:var(--ch-who)}
    .tg-table tbody tr:nth-child(6n+3) .tg-swatch{--sw:var(--ch-pace)}
    .tg-table tbody tr:nth-child(6n+4) .tg-swatch{--sw:var(--ch-proof)}
    .tg-table tbody tr:nth-child(6n+5) .tg-swatch{--sw:var(--ch-delivery)}
    .tg-table tbody tr:nth-child(6n+6) .tg-swatch{--sw:var(--ch-integrity)}
    .tg-tag{
      font-family:var(--font-mono);font-size:9px;letter-spacing:.1em;text-transform:uppercase;
      color:var(--warning);background:color-mix(in srgb,var(--warning) 12%,transparent);
      border:1px solid color-mix(in srgb,var(--warning) 32%,var(--border));border-radius:999px;padding:2px 7px;
    }
    .tg-opt{font-size:10px;font-weight:500;letter-spacing:.04em;color:var(--ink-muted)}
    .tg-input{
      width:120px;min-height:36px;background:var(--surface-3);color:var(--ink);
      border:1px solid var(--border-strong);border-radius:var(--r-sm);
      padding:7px 11px;font-family:var(--font-ui);font-size:13.5px;text-align:right;
      font-variant-numeric:tabular-nums;transition:border-color .15s,box-shadow .15s,background .15s;
    }
    .tg-input:hover{border-color:var(--accent);background:var(--surface)}
    .tg-input:focus{outline:none;border-color:var(--accent);background:var(--surface);box-shadow:0 0 0 3px var(--accent-ring)}
    .tg-input::placeholder{color:var(--ink-muted)}
    .tg-input-text{width:100%;min-width:152px;text-align:left;font-variant-numeric:normal}
    .tg-row-actions{display:flex;gap:8px;align-items:center}
    .tg-remove{
      flex:0 0 auto;width:32px;height:32px;border-radius:var(--r-sm);
      border:1px solid color-mix(in srgb,var(--critical) 32%,var(--border));
      background:transparent;color:var(--critical);font-size:16px;line-height:1;cursor:pointer;
    }
    .tg-remove:hover{background:color-mix(in srgb,var(--critical) 12%,transparent);border-color:var(--critical)}
    .tg-actions{
      display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;
      padding-top:17px;margin-top:6px;border-top:1px solid var(--border);
    }
    @media (max-width:640px){
      .tg-input{width:96px}
      .tg-actions{flex-direction:column-reverse}
      .tg-actions .an-btn{justify-content:center}
    }
  </style>
</x-admin-layout>
