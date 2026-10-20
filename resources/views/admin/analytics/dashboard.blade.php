<x-admin-layout title="Dashboard">
  <div class="an-scope an" data-analytics-dashboard
       x-data="analyticsDashboard({
         dataUrl: @js(route('admin.analytics.data')),
         geoProvUrl: @js(asset('geo/caraga-provinces.0.001.json')),
         geoMuniUrl: @js(asset('geo/caraga-municipalities.0.001.json')),
         targetsUrl: @js(auth()->user()?->isRegionalDirector() ? route('admin.analytics.targets.edit') : null)
       })">

    {{-- Reading progress through the narrative. --}}
    <div class="an-progress" aria-hidden="true">
      <div class="an-progress-bar" :style="`width:${progress}%`"></div>
    </div>

    {{-- Sticky HUD: filters stay reachable at every scroll depth. --}}
    <div class="an-deck">
      <header class="an-head">
        <div>
          <h1>Impact Terminal</h1>
          <p class="an-sub">DOST Caraga CERTiFY — six chapters, read top to bottom. Filters apply to every chapter.</p>
          <div class="an-head-meta">
            <span class="an-stamp">{{ strtoupper(str_replace('_', ' ', auth()->user()?->role ?? 'guest')) }} · LIVE</span>
            <span class="an-tele"><span class="an-tele-key">node:</span> caraga-13</span>
            <span class="an-tele"><span class="an-tele-key">sweep:</span> {{ now()->format('Y.m.d H:i') }}</span>
            <span class="an-tele" x-show="data?.available===false" x-cloak>
              <span class="an-tele-key">read_model:</span> offline
            </span>
          </div>
        </div>
        <div class="an-head-actions">
          {{-- Theme toggle: HUD dark / high-contrast light. Two explicit
               buttons rather than one ambiguous switch, each announcing its
               pressed state to assistive tech. --}}
          <div class="an-theme" role="group" aria-label="Colour theme">
            <button type="button" :class="{'is-on': theme==='dark'}"
                    :aria-pressed="theme==='dark' ? 'true' : 'false'"
                    @click="setTheme('dark')">
              <span aria-hidden="true">◐</span> HUD
            </button>
            <button type="button" :class="{'is-on': theme==='light'}"
                    :aria-pressed="theme==='light' ? 'true' : 'false'"
                    @click="setTheme('light')">
              <span aria-hidden="true">☀</span> Light
            </button>
          </div>
          {{-- Secondary/outline on purpose: the KPI metrics are the visual
               priority on this screen. --}}
          <template x-if="targetsUrl">
            <a class="an-btn an-btn-secondary" :href="targetsUrl">Set program targets</a>
          </template>
        </div>
      </header>

      <div class="an-filters" role="group" aria-label="Filters">
        <label>Gender
          <select x-model="filters.gender" @change="afterFilterChange()">
            <option value="">All</option><option value="Male">Male</option>
            <option value="Female">Female</option><option value="Unknown">Unknown</option>
          </select>
        </label>
        <label>Age band
          <select x-model="filters.age_band" @change="afterFilterChange()">
            <option value="">All</option>
            <template x-for="b in ['<18','18-24','25-34','35-44','45-59','60+','Unknown']" :key="b">
              <option :value="b" x-text="b"></option>
            </template>
          </select>
        </label>
        <label>Program
          <select x-model="filters.program" @change="afterFilterChange()">
            <option value="">All</option>
            <template x-for="p in (data?.executive?.program_mix ?? [])" :key="p.program_code">
              <option :value="p.program_code" x-text="p.program_code"></option>
            </template>
          </select>
        </label>
        <label>From <input type="date" x-model="filters.date_from" @change="afterFilterChange()"></label>
        <label>To <input type="date" x-model="filters.date_to" @change="afterFilterChange()"></label>
      </div>

      <div class="an-chips" x-show="hasFilters()" x-cloak>
        <template x-for="chip in chips" :key="chip.key">
          <button type="button" class="an-chip" @click="removeChip(chip.key)">
            <span x-text="chip.label + ': ' + chip.value"></span><span aria-hidden="true">×</span>
          </button>
        </template>
        <button type="button" class="an-chip an-chip-reset" @click="resetAll()">Reset all</button>
      </div>
    </div>

    {{-- States --}}
    <div class="an-note" x-show="loading" x-cloak>Loading analytics…</div>
    <div class="an-note an-note-error" x-show="error" x-cloak>Could not load analytics. <button type="button" class="an-btn" @click="load()">Retry</button></div>
    <div class="an-note" x-show="!loading && !error && data && data.available===false" x-cloak>
      The analytics read-model is not built yet, so actuals are unavailable. Run
      <code>php artisan analytics:refresh</code> (requires PostgreSQL). Targets you have
      already set still appear in <strong>Pace</strong>.
    </div>

    <div class="an-body" x-show="!loading && !error && data" x-cloak>
      {{-- Chapter rail: a cyber-terminal menu that doubles as a position
           indicator. Numerals are bracketed — [02] WHO. --}}
      <nav class="an-rail" aria-label="Chapters">
        <p class="an-rail-title">// Sector Index</p>
        <ol>
          <template x-for="c in chapters" :key="c.id">
            <li x-show="chapterVisible(c.id)">
              <button type="button" :class="{'is-active': active===c.id}" :data-ch="c.id"
                      @click="jumpTo(c.id)" :aria-current="active===c.id ? 'true' : 'false'">
                <span class="an-rail-n" aria-hidden="true">[<span x-text="c.n"></span>]</span>
                <span class="an-rail-label" x-text="c.label"></span>
              </button>
            </li>
          </template>
        </ol>
      </nav>

      <div class="an-flow">

        {{-- ============ 01 · REACH ============ --}}
        <section class="an-ch" data-chapter="reach" data-ch="reach" id="reach" aria-labelledby="ch-reach-h">
          <div class="an-ch-head">
            <span class="an-ch-n" aria-hidden="true">01</span>
            <div>
              <p class="an-ch-kicker">Reach</p>
              <h2 id="ch-reach-h">How far did we get?</h2>
              <p class="an-ch-lead" x-text="leadReach"></p>
            </div>
          </div>

          <div class="an-kpis">
            <template x-for="k in kpiTiles" :key="k.key">
              <div class="an-kpi an-brackets">
                <span class="an-bk an-bk-tl" aria-hidden="true"></span>
                <span class="an-bk an-bk-br" aria-hidden="true"></span>
                <div class="an-kpi-label" x-text="k.label"></div>
                <div class="an-kpi-value" x-text="k.value"></div>
                <div class="an-kpi-delta" :class="k.dir" x-show="k.delta!==null" x-text="k.deltaText"></div>
                <div class="an-kpi-tele">
                  <span class="an-tele"><span class="an-tele-key" x-text="k.code + ':'"></span> <span x-text="k.raw"></span></span>
                  <span class="an-tele" x-text="k.grid"></span>
                </div>
              </div>
            </template>
          </div>

          <x-analytics.panel name="map" title="Certificates by location" code="GEO//SCAN"
                             class="an-col-2 an-map-wrap"
                             description="Certificates by province and municipality across Caraga.">
            <div class="an-map-hud" aria-hidden="true">
              <span class="an-stamp">Map ready</span>
              <span class="an-tele"><span class="an-tele-key">proj:</span> psgc / wgs84</span>
              <span class="an-tele"><span class="an-tele-key">layer:</span> <span x-text="mapLevel"></span></span>
            </div>
            <div class="an-breadcrumb" x-show="mapLevel==='municipality'" x-cloak>
              <button type="button" @click="drillUp()">← Caraga / Provinces</button>
              <span x-text="drill?.name"></span>
            </div>
            <p class="an-hint">
              Unknown location <strong x-text="(data?.executive?.geo?.unknown_municipality_share ?? 0)+'%'"></strong>
              — select a sector to drill in and filter every chapter below
            </p>
          </x-analytics.panel>
        </section>

        {{-- ============ 02 · WHO ============ --}}
        <section class="an-ch" data-chapter="who" data-ch="who" id="who" aria-labelledby="ch-who-h">
          <div class="an-ch-head">
            <span class="an-ch-n" aria-hidden="true">02</span>
            <div>
              <p class="an-ch-kicker">Who</p>
              <h2 id="ch-who-h">Who did we reach?</h2>
              <p class="an-ch-lead" x-text="leadWho"></p>
            </div>
          </div>

          <div class="an-grid">
            <x-analytics.panel name="age" title="Age distribution by gender" />
            <x-analytics.panel name="program" title="Program mix (click a bar to filter)" />
          </div>

          <div class="an-card an-pad an-brackets">
      <span class="an-bk an-bk-tl" aria-hidden="true"></span>
      <span class="an-bk an-bk-br" aria-hidden="true"></span>
            <div class="an-card-head"><h3>Top trainings</h3></div>
            <table class="an-table">
              <thead><tr><th>Training</th><th>Program</th><th class="an-num">Certificates</th></tr></thead>
              <tbody>
                <template x-for="t in topTrainings" :key="t.training_title_norm + '|' + t.program_code">
                  <tr><td x-text="t.training_title"></td><td class="an-prog" x-text="t.program_code"></td><td class="an-num" x-text="fmtNum(t.total)"></td></tr>
                </template>
                <tr x-show="!topTrainings.length"><td colspan="3" class="an-empty">No trainings recorded for the selected date range or filters.</td></tr>
              </tbody>
            </table>
          </div>
        </section>

        {{-- ============ 03 · PACE ============ --}}
        <section class="an-ch" data-chapter="pace" data-ch="pace" id="pace" aria-labelledby="ch-pace-h">
          <div class="an-ch-head">
            <span class="an-ch-n" aria-hidden="true">03</span>
            <div>
              <p class="an-ch-kicker">Pace</p>
              <h2 id="ch-pace-h">Are we on plan?</h2>
              <p class="an-ch-lead" x-text="leadPace"></p>
            </div>
          </div>

          {{-- Targets are annual and per-program. Saying so is not optional:
               comparing a demographically filtered actual against a
               region-wide annual target would read as authoritative and be
               wrong. --}}
          <div class="an-note an-note-warn" x-show="ignoredTargetFilters.length" x-cloak>
            ⚠ Targets are set per program for the whole region and whole year, so this chapter
            ignores your <strong x-text="ignoredTargetFilters.join(' and ')"></strong> filter.
            Every other chapter honours it.
          </div>

          <div class="an-note" x-show="data?.targets && !data.targets.has_targets" x-cloak>
            <template x-if="targetsUrl">
              <span>No targets set for <strong x-text="data?.targets?.year"></strong> yet —
                <a :href="targetsUrl">set them now</a> to turn this chapter on.</span>
            </template>
            <template x-if="!targetsUrl">
              <span>No targets set for <strong x-text="data?.targets?.year"></strong> yet. The Regional Director sets these.</span>
            </template>
          </div>

          <div class="an-grid">
            <x-analytics.panel name="trend" title="Monthly trend (brush to set the date range)" class="an-col-2" />
          </div>

          <div class="an-grid" x-show="targetPrograms.length" x-cloak>
            <x-analytics.panel name="targets" title="Target vs actual by program" class="an-col-2"
                               description="Actual certificates against the annual target per program, with a marker for the year-to-date pace." />
          </div>

          <div class="an-card an-pad an-brackets" x-show="targetPrograms.length" x-cloak>
      <span class="an-bk an-bk-tl" aria-hidden="true"></span>
      <span class="an-bk an-bk-br" aria-hidden="true"></span>
            <div class="an-card-head">
              <h3>Pacing against the <span x-text="data?.targets?.year"></span> plan</h3>
              <p class="an-hint" x-show="!data?.targets?.actuals_available" x-cloak>Actuals unavailable — read-model not built.</p>
            </div>
            <table class="an-table">
              <thead>
                <tr>
                  <th>Program</th><th class="an-num">Target</th><th class="an-num">Actual</th>
                  <th class="an-num">Expected by now</th><th class="an-num">Year-end pace</th><th>Status</th>
                </tr>
              </thead>
              <tbody>
                <template x-for="p in targetPrograms" :key="p.program_code">
                  <tr>
                    <td x-text="p.program_code"></td>
                    <td class="an-num" x-text="fmtNum(p.certificates.target)"></td>
                    <td class="an-num" x-text="fmtNum(p.certificates.actual)"></td>
                    <td class="an-num" x-text="fmtNum(p.certificates.expected_to_date)"></td>
                    <td class="an-num" x-text="fmtNum(p.certificates.projection)"></td>
                    <td>
                      {{-- Status carries an icon + word, never color alone. --}}
                      <span class="an-status" :style="`--st:${statusFor(p.certificates.status).color}`">
                        <span aria-hidden="true" x-text="statusFor(p.certificates.status).icon"></span>
                        <span x-text="statusFor(p.certificates.status).label"></span>
                      </span>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </section>

        {{-- ============ 04 · PROOF ============ --}}
        <section class="an-ch" data-chapter="proof" data-ch="proof" id="proof" aria-labelledby="ch-proof-h">
          <div class="an-ch-head">
            <span class="an-ch-n" aria-hidden="true">04</span>
            <div>
              <p class="an-ch-kicker">Proof</p>
              <h2 id="ch-proof-h">Is anyone verifying?</h2>
              <p class="an-ch-lead" x-text="leadProof"></p>
            </div>
          </div>

          <div class="an-note" x-show="data?.executive && !data.executive.verification?.available" x-cloak>
            Verification analytics need the verification views. Run <code>php artisan migrate</code>
            then <code>php artisan analytics:refresh</code>. Logging is already recording every
            lookup in the meantime, so no history is lost.
          </div>

          <div x-show="data?.executive?.verification?.available" x-cloak>
            <div class="an-kpis an-kpis-4">
              <template x-for="k in verificationKpis" :key="k.key">
                <div class="an-kpi an-brackets">
                  <span class="an-bk an-bk-tl" aria-hidden="true"></span>
                  <span class="an-bk an-bk-br" aria-hidden="true"></span>
                  <div class="an-kpi-label" x-text="k.label"></div>
                  <div class="an-kpi-value" x-text="k.value"></div>
                  <div class="an-kpi-tele">
                    <span class="an-tele"><span class="an-tele-key" x-text="k.code + ':'"></span> <span x-text="k.raw"></span></span>
                    <span class="an-tele" x-text="k.grid"></span>
                  </div>
                </div>
              </template>
            </div>

            <div class="an-grid">
              <x-analytics.panel name="verify-trend" title="Verification volume over time" class="an-col-2"
                                 description="Daily verification attempts that resolved to a certificate, and how many distinct verifiers they came from." />
              <x-analytics.panel name="verify-devices" title="How certificates are checked" />
              <div class="an-card an-panel an-pad an-brackets">
      <span class="an-bk an-bk-tl" aria-hidden="true"></span>
      <span class="an-bk an-bk-br" aria-hidden="true"></span>
                <div class="an-card-head"><h3>Most-verified trainings</h3></div>
                <table class="an-table">
                  <thead><tr><th>Training</th><th class="an-num">Verifications</th><th class="an-num">% ever verified</th></tr></thead>
                  <tbody>
                    <template x-for="t in topVerified" :key="t.training_title_norm + '|' + t.program_code">
                      <tr>
                        <td x-text="t.training_title"></td>
                        <td class="an-num" x-text="fmtNum(t.verifications)"></td>
                        <td class="an-num" x-text="fmtPct(t.verified_share)"></td>
                      </tr>
                    </template>
                    <tr x-show="!topVerified.length"><td colspan="3" class="an-empty">No verifications recorded for the selected date range.</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </section>

        {{-- ============ 05 · DELIVERY ============ --}}
        <section class="an-ch" data-chapter="delivery" data-ch="delivery" id="delivery" aria-labelledby="ch-delivery-h">
          <div class="an-ch-head">
            <span class="an-ch-n" aria-hidden="true">05</span>
            <div>
              <p class="an-ch-kicker">Delivery</p>
              <h2 id="ch-delivery-h">How well do we deliver?</h2>
              <p class="an-ch-lead" x-text="leadDelivery"></p>
            </div>
          </div>

          <p class="an-note" x-show="data?.operations?.scoped" x-cloak>
            Showing only packages and intake events you created or endorsed.
          </p>

          <div class="an-grid">
            <x-analytics.panel name="pipeline" title="Endorsement pipeline" />
            <div class="an-card an-panel an-pad an-brackets">
      <span class="an-bk an-bk-tl" aria-hidden="true"></span>
      <span class="an-bk an-bk-br" aria-hidden="true"></span>
              <div class="an-card-head"><h3>RD turnaround</h3></div>
              <div class="an-stat-row">
                <div><span>Average</span><strong x-text="(data?.operations?.turnaround?.avg_hours ?? '—')+' h'"></strong></div>
                <div><span>Median</span><strong x-text="(data?.operations?.turnaround?.p50_hours ?? '—')+' h'"></strong></div>
                <div><span>p90</span><strong x-text="(data?.operations?.turnaround?.p90_hours ?? '—')+' h'"></strong></div>
                <div><span>Decided</span><strong x-text="fmtNum(data?.operations?.turnaround?.decided ?? 0)"></strong></div>
              </div>
            </div>
            <x-analytics.panel name="errors" title="Rejections & generation errors" />
            <x-analytics.panel name="email" title="Email delivery" />
            <x-analytics.panel name="claim" title="Claim funnel" />
            <x-analytics.panel name="intake" title="Intake conversion by event" />
          </div>
        </section>

        {{-- ============ 06 · INTEGRITY (RD only) ============ --}}
        <section class="an-ch" data-chapter="integrity" data-ch="integrity" id="integrity"
                 x-show="chapterVisible('integrity')" x-cloak aria-labelledby="ch-integrity-h">
          <div class="an-ch-head">
            <span class="an-ch-n" aria-hidden="true">06</span>
            <div>
              <p class="an-ch-kicker">Integrity</p>
              <h2 id="ch-integrity-h">Is anyone abusing it?</h2>
              <p class="an-ch-lead" x-text="leadIntegrity"></p>
            </div>
          </div>

          <p class="an-hint">
            Read live from the verification log, not the nightly views — a certificate being
            hammered right now is only useful while it is happening.
          </p>

          <div class="an-kpis an-kpis-4">
            <div class="an-kpi an-brackets">
              <span class="an-bk an-bk-tl" aria-hidden="true"></span><span class="an-bk an-bk-br" aria-hidden="true"></span>
              <div class="an-kpi-label">Verification attempts</div>
              <div class="an-kpi-value" x-text="fmtNum(data?.integrity?.total_attempts)"></div>
              <div class="an-kpi-tele">
                <span class="an-tele"><span class="an-tele-key">REQ_TOT:</span> <span x-text="data?.integrity?.total_attempts ?? '--'"></span></span>
                <span class="an-tele">R01·C01</span>
              </div>
            </div>
            <div class="an-kpi an-brackets">
              <span class="an-bk an-bk-tl" aria-hidden="true"></span><span class="an-bk an-bk-br" aria-hidden="true"></span>
              <div class="an-kpi-label">Resolved to nothing</div>
              <div class="an-kpi-value" x-text="fmtPct(data?.integrity?.failure_rate)"></div>
              <div class="an-kpi-tele">
                <span class="an-tele"><span class="an-tele-key">ERR_RATE:</span> <span x-text="data?.integrity?.failure_rate ?? '--'"></span></span>
                <span class="an-tele">R01·C02</span>
              </div>
            </div>
            <div class="an-kpi an-brackets">
              <span class="an-bk an-bk-tl" aria-hidden="true"></span><span class="an-bk an-bk-br" aria-hidden="true"></span>
              <div class="an-kpi-label">From bots</div>
              <div class="an-kpi-value" x-text="fmtPct(data?.integrity?.bot_share)"></div>
              <div class="an-kpi-tele">
                <span class="an-tele"><span class="an-tele-key">BOT_SHR:</span> <span x-text="data?.integrity?.bot_share ?? '--'"></span></span>
                <span class="an-tele">R01·C03</span>
              </div>
            </div>
            <div class="an-kpi an-brackets">
              <span class="an-bk an-bk-tl" aria-hidden="true"></span><span class="an-bk an-bk-br" aria-hidden="true"></span>
              <div class="an-kpi-label">Failed lookups</div>
              <div class="an-kpi-value" x-text="fmtNum(data?.integrity?.failed_attempts)"></div>
              <div class="an-kpi-tele">
                <span class="an-tele"><span class="an-tele-key">MISS_CNT:</span> <span x-text="data?.integrity?.failed_attempts ?? '--'"></span></span>
                <span class="an-tele">R02·C01</span>
              </div>
            </div>
          </div>

          <div class="an-grid">
            <x-analytics.panel name="integrity-outcomes" title="Attempts by outcome" />
            <x-analytics.panel name="integrity-trend" title="Outcomes over time" />
          </div>

          <div class="an-grid">
            <div class="an-card an-panel an-pad an-brackets">
      <span class="an-bk an-bk-tl" aria-hidden="true"></span>
      <span class="an-bk an-bk-br" aria-hidden="true"></span>
              <div class="an-card-head"><h3>Revoked or invalid certificates being presented</h3></div>
              <table class="an-table">
                <thead><tr><th>Certificate</th><th>Status</th><th class="an-num">Attempts</th><th class="an-num">Verifiers</th></tr></thead>
                <tbody>
                  <template x-for="r in (data?.integrity?.bad_hits ?? [])" :key="r.certificate_id">
                    <tr>
                      <td>
                        <span class="an-mono" x-text="r.certificate_code"></span>
                        <span class="an-prog" x-text="r.training_title"></span>
                      </td>
                      <td><span class="an-status" :style="`--st:var(--critical)`"><span aria-hidden="true">▼</span><span x-text="r.status"></span></span></td>
                      <td class="an-num" x-text="fmtNum(r.attempts)"></td>
                      <td class="an-num" x-text="fmtNum(r.unique_verifiers)"></td>
                    </tr>
                  </template>
                  <tr x-show="!(data?.integrity?.bad_hits ?? []).length">
                    <td colspan="4" class="an-empty">No revoked or invalid certificate was looked up in this range.</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="an-card an-panel an-pad an-brackets">
      <span class="an-bk an-bk-tl" aria-hidden="true"></span>
      <span class="an-bk an-bk-br" aria-hidden="true"></span>
              <div class="an-card-head"><h3>Concentrated lookups</h3></div>
              <p class="an-hint">High volume from few distinct verifiers — a link being replayed or scraped rather than genuinely checked.</p>
              <table class="an-table">
                <thead><tr><th>Certificate ID</th><th class="an-num">Attempts</th><th class="an-num">Verifiers</th></tr></thead>
                <tbody>
                  <template x-for="r in (data?.integrity?.concentrated ?? [])" :key="r.certificate_id">
                    <tr>
                      <td class="an-mono" x-text="r.certificate_id"></td>
                      <td class="an-num" x-text="fmtNum(r.attempts)"></td>
                      <td class="an-num" x-text="fmtNum(r.unique_verifiers)"></td>
                    </tr>
                  </template>
                  <tr x-show="!(data?.integrity?.concentrated ?? []).length">
                    <td colspan="3" class="an-empty">No concentrated lookup patterns in the selected date range.</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="an-card an-panel an-pad an-brackets an-col-2">
      <span class="an-bk an-bk-tl" aria-hidden="true"></span>
      <span class="an-bk an-bk-br" aria-hidden="true"></span>
              <div class="an-card-head"><h3>Where verifications come from</h3></div>
              <p class="an-hint">Referring host only — never the full URL, which on a verification link carries the token itself.</p>
              <table class="an-table">
                <thead><tr><th>Host</th><th class="an-num">Verifications</th></tr></thead>
                <tbody>
                  <template x-for="r in (data?.integrity?.referrers ?? [])" :key="r.referer_host">
                    <tr><td class="an-mono" x-text="r.referer_host"></td><td class="an-num" x-text="fmtNum(r.total)"></td></tr>
                  </template>
                  <tr x-show="!(data?.integrity?.referrers ?? []).length">
                    <td colspan="2" class="an-empty">No referrers recorded for this range — consistent with QR scans, which send none.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>

  @include('admin.analytics._tokens')
  @include('admin.analytics._dashboard_styles')
</x-admin-layout>
