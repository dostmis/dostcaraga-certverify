@props(['name', 'title', 'description' => null, 'code' => null])

{{-- A telemetry module: dark glass, digital corner brackets, and the export
     toggles. `code` is an optional grid-reference artifact shown in the header
     (e.g. "GEO//01"); it defaults to the panel name upper-cased. --}}
<figure {{ $attributes->merge(['class' => 'an-card an-panel an-brackets']) }}
        role="group" aria-label="{{ $title }}">
  <span class="an-bk an-bk-tl" aria-hidden="true"></span>
  <span class="an-bk an-bk-tr" aria-hidden="true"></span>
  <span class="an-bk an-bk-bl" aria-hidden="true"></span>
  <span class="an-bk an-bk-br" aria-hidden="true"></span>

  <figcaption class="an-card-head">
    <h3>{{ $title }}</h3>
    <div class="an-actions">
      <span class="an-tele" aria-hidden="true">{{ $code ?? strtoupper(str_replace('-', '//', $name)) }}</span>
      <button type="button" class="an-act" title="Download PNG"
              @click="exportPng('{{ $name }}', '{{ $name }}')" aria-label="Download {{ $title }} as PNG">PNG</button>
      <button type="button" class="an-act" title="Download CSV"
              @click="exportCsv('{{ $name }}', '{{ $name }}')" aria-label="Download {{ $title }} data as CSV">CSV</button>
    </div>
  </figcaption>

  {{ $slot }}

  <div class="an-chart" data-panel="{{ $name }}" role="img"
       aria-label="{{ $description ?? $title }}"></div>
</figure>
