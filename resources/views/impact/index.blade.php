<x-guest-layout>
  <div style="max-width:960px;margin:0 auto;color:#0f172a;" x-data="impactAtlas(@js(url('/impact/data.json')))" x-init="load()">
    <header style="text-align:center;margin:8px 0 28px;">
      <div style="font-size:12.5px;letter-spacing:.14em;text-transform:uppercase;color:#64748b;font-weight:700;">DOST Caraga</div>
      <h1 style="font-size:30px;font-weight:800;letter-spacing:-.02em;margin:6px 0 8px;">Caraga Reach Atlas</h1>
      <p style="color:#64748b;font-size:14.5px;max-width:560px;margin:0 auto;">
        Aggregate, privacy-safe view of DOST Caraga training certificates issued across Region XIII.
      </p>
    </header>

    <template x-if="loading"><p style="text-align:center;color:#64748b;">Loading…</p></template>
    <template x-if="error"><p style="text-align:center;color:#b91c1c;">Could not load the atlas. <button @click="load()">Retry</button></p></template>

    <section x-show="!loading && !error" x-cloak>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:22px;">
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:18px;text-align:center;">
          <div style="font-size:30px;font-weight:800;" x-text="fmt(totalVisible)"></div>
          <div style="font-size:12.5px;color:#64748b;font-weight:600;">Certificates (shown)</div>
        </div>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:18px;text-align:center;">
          <div style="font-size:30px;font-weight:800;" x-text="provinces"></div>
          <div style="font-size:12.5px;color:#64748b;font-weight:600;">Provinces</div>
        </div>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:18px;text-align:center;">
          <div style="font-size:30px;font-weight:800;" x-text="municipalities"></div>
          <div style="font-size:12.5px;color:#64748b;font-weight:600;">Municipalities</div>
        </div>
      </div>
      <p style="font-size:12px;color:#94a3b8;text-align:center;border-top:1px dashed #e2e8f0;padding-top:14px;">
        Figures are aggregated; any group smaller than 5 is suppressed and shown as “&lt;5”. No barangay-level or
        personal data is published. The full scroll-through atlas (maps, journey, parity) renders here in the next iteration.
      </p>
    </section>
  </div>

  <script>
    function impactAtlas(dataUrl) {
      return {
        dataUrl, loading: false, error: false, reach: [],
        get totalVisible() { return this.reach.reduce((s, r) => s + (r.cert_count || 0), 0); },
        get provinces() { return new Set(this.reach.filter(r => r.province_code).map(r => r.province_code)).size; },
        get municipalities() { return new Set(this.reach.filter(r => r.municipality_code).map(r => r.municipality_code)).size; },
        fmt(v) { return new Intl.NumberFormat('en-PH').format(v || 0); },
        async load() {
          this.loading = true; this.error = false;
          try {
            const res = await fetch(this.dataUrl, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const body = await res.json();
            this.reach = body.reach ?? [];
          } catch (e) { this.error = true; console.error('[impact] load failed', e); }
          finally { this.loading = false; }
        },
      };
    }
  </script>
</x-guest-layout>
