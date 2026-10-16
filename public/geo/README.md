# Geographic boundary data (Caraga / Region XIII)

PSGC-coded GeoJSON boundaries used by the CERTiFY analytics choropleth.

## Sources & licenses

- **Primary — [faeldon/philippines-json-maps](https://github.com/faeldon/philippines-json-maps)**,
  `2023/geojson` dataset (2023 PSGC codes), **low-res** simplification (`0.001`).
  Province layer (all 5) and 72 of the 73 municipality features.
  **License:** MIT © James Faeldon (upstream from PSA / PhilGIS).
- **Secondary — OpenStreetMap**, Butuan City administrative boundary
  (relation `14141553`, `admin_level=6`), retrieved via Nominatim.
  **License:** ODbL © OpenStreetMap contributors.
  Added because faeldon's 2023 set groups Caraga strictly by its 5 provinces and
  ships no standalone polygon for Butuan City (a Highly Urbanized City).
- **Retrieved:** 2026-10-17.

### Butuan City code note

faeldon labels `1600200000` as the **Agusan del Norte province** (adm2). Butuan
City is an independent HUC with no distinct municity entry in that dataset, so
the OSM-sourced Butuan feature is assigned the **locally-reserved** municipality
code **`adm3_psgc = 1600299000`** (region 16, province 002 = Agusan del Norte,
municipality slot `99` = reserved HUC marker; it cannot collide with Agusan del
Norte's real municipalities `01`–`12`). Its `adm2_psgc` remains `1600200000` so
province-level roll-ups stay correct. This reserved code is used consistently by
the analytics PSGC crosswalk.

## Files

| File | Level | Features | Key properties |
|------|-------|----------|----------------|
| `caraga-provinces.0.001.json` | Province (adm2) | 5 | `adm2_psgc` (code), `adm2_en` (name), `adm1_psgc` |
| `caraga-municipalities.0.001.json` | City/Municipality (adm3) | 73 | `adm3_psgc` (code), `adm3_en` (name), `adm2_psgc` |

Both are already low-res (≤ ~80 KB each), so no further `mapshaper`
simplification was required (target was ≤ 500 KB/level).

## Join verification against live certificate data (413 certs)

Normalized name → PSGC code join (lowercase, strip accents/parentheticals,
drop `City of`/`City`, collapse whitespace):

- **Province: 100%** (413/413)
- **Municipality: 100%** (413/413) after adding the OSM Butuan City polygon
  (was 81.6% from faeldon alone; the only gap had been Butuan's 76 certs).
