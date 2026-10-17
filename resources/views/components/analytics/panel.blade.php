@props(['name', 'title', 'description' => null])

<figure {{ $attributes->merge(['class' => 'an-card an-panel']) }}
        role="group" aria-label="{{ $title }}">
  <figcaption class="an-card-head">
    <h3>{{ $title }}</h3>
    <div class="an-actions">
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
