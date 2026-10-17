<x-guest-layout>
  <div class="atlas" data-impact-atlas
       x-data="impactAtlas({
         dataUrl: @js(url('/impact/data.json')),
         geoMuniUrl: @js(asset('geo/caraga-municipalities.0.001.json'))
       })">

    <header class="atlas-hero">
      <div class="atlas-eyebrow">DOST Caraga</div>
      <h1>Caraga Reach Atlas</h1>
      <p class="atlas-lede">Aggregate, privacy-safe view of DOST Caraga training certificates issued across Region XIII.</p>
    </header>

    <div class="atlas-note" x-show="loading" x-cloak>Loading…</div>
    <div class="atlas-note atlas-err" x-show="error" x-cloak>Could not load the atlas. <button @click="load()">Retry</button></div>

    <template x-if="!loading && !error">
      <div>
        <div class="atlas-stats">
          <div class="atlas-stat"><div class="n" x-text="fmt(totalVisible)"></div><div class="l">Certificates (shown)</div></div>
          <div class="atlas-stat"><div class="n" x-text="provinces"></div><div class="l">Provinces</div></div>
          <div class="atlas-stat"><div class="n" x-text="municipalities"></div><div class="l">Municipalities</div></div>
          <div class="atlas-stat"><div class="n" x-text="years"></div><div class="l">Years</div></div>
        </div>

        <section class="atlas-card atlas-wide">
          <h2>Where certificates reached</h2>
          <div class="atlas-chart atlas-map" data-panel="map" role="img" aria-label="Choropleth of certificates by municipality"></div>
        </section>

        <section class="atlas-card atlas-wide">
          <h2>Participant journey</h2>
          <p class="atlas-cap">From intake registration to a certificate, to a claimed recipient account.</p>
          <div class="atlas-chart" data-panel="journey" role="img" aria-label="Sankey of intake to certified to claimed"></div>
        </section>

        <section class="atlas-card atlas-wide">
          <h2>Training activity</h2>
          <p class="atlas-cap">Certificates issued by date (days with fewer than 5 are not shown).</p>
          <div class="atlas-chart atlas-cal" data-panel="calendar" role="img" aria-label="Calendar heatmap of certificates issued per day"></div>
        </section>

        <div class="atlas-grid">
          <section class="atlas-card">
            <h2>By program</h2>
            <div class="atlas-chart" data-panel="program" role="img" aria-label="Certificates by DOST program"></div>
          </section>
          <section class="atlas-card">
            <h2>Gender parity by program</h2>
            <div class="atlas-chart" data-panel="parity" role="img" aria-label="Gender split by program"></div>
          </section>
          <section class="atlas-card atlas-wide">
            <h2>Age distribution</h2>
            <div class="atlas-chart" data-panel="age" role="img" aria-label="Certificates by age band"></div>
          </section>
        </div>

        <footer class="atlas-foot">
          <strong>About these figures.</strong> All numbers are aggregated from issued certificates.
          Any group smaller than 5 is suppressed — shown as “&lt;5”, and days with fewer than 5
          certificates are omitted from the activity calendar. The participant journey shows only
          aggregate stage counts. No barangay-level or personal data is published. Boundaries:
          faeldon/philippines-json-maps (MIT) and OpenStreetMap (ODbL).
        </footer>
      </div>
    </template>
  </div>

  <style>
    [x-cloak]{display:none!important}
    .atlas{max-width:1040px;margin:0 auto;color:#0f172a}
    .atlas-hero{text-align:center;margin:8px 0 26px}
    .atlas-eyebrow{font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:#64748b;font-weight:700}
    .atlas-hero h1{font-size:32px;font-weight:800;letter-spacing:-.02em;margin:6px 0 8px}
    .atlas-lede{color:#64748b;font-size:15px;max-width:580px;margin:0 auto}
    .atlas-note{text-align:center;color:#64748b;padding:24px}
    .atlas-err{color:#b91c1c}
    .atlas-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:22px}
    .atlas-stat{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:18px;text-align:center}
    .atlas-stat .n{font-size:30px;font-weight:800;letter-spacing:-.02em}
    .atlas-stat .l{font-size:12.5px;color:#64748b;font-weight:600;margin-top:4px}
    .atlas-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:18px 20px;margin-bottom:16px}
    .atlas-card h2{font-size:15px;font-weight:700;margin:0 0 10px}
    .atlas-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
    .atlas-wide{grid-column:1 / -1}
    @media (max-width:800px){.atlas-grid{grid-template-columns:1fr}}
    .atlas-chart{width:100%;height:320px}
    .atlas-map{height:460px}
    .atlas-cal{height:220px}
    .atlas-cap{font-size:12.5px;color:#64748b;margin:0 0 10px}
    .atlas-foot{font-size:12.5px;color:#94a3b8;text-align:center;border-top:1px dashed #e2e8f0;padding-top:16px;margin-top:6px;line-height:1.6}
  </style>
</x-guest-layout>
