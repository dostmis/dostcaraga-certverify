<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Certificate - {{ $cert->certificate_code }}</title>
  <style>
    @page { size: A4 landscape; margin: 18mm; }
    body { margin:0; font-family: Arial, sans-serif; }
    .sheet {
      width: 100%;
      height: calc(100vh - 0px);
      position: relative;
      border: 2px solid #ddd;
      border-radius: 10px;
      padding: 30px;
      box-sizing: border-box;
    }
    .qr {
      position: absolute;
      right: 24px;
      bottom: 24px;
      text-align: center;
    }
    .qr small { display:block; margin-top:6px; font-size:10px; color:#333; }
    .qr .verify-link {
      max-width: 260px;
      word-break: break-all;
      font-size: 8px;
      line-height: 1.25;
    }
    .signatory {
      position: absolute;
      left: 50%;
      bottom: 34px;
      transform: translateX(-50%);
      text-align: center;
      min-width: 260px;
    }
    .signatory img {
      max-width: 240px;
      max-height: 90px;
      width: auto;
      height: auto;
      margin: 0 auto 4px;
      object-fit: contain;
    }
    .code { font-size:11px; font-weight:800; letter-spacing:.6px; }
  </style>
</head>
<body>
  <div class="sheet">
    {{-- Your certificate design here --}}
    <h1 style="text-align:center; margin-top:40px;">CERTIFICATE OF PARTICIPATION</h1>

    <p style="text-align:center; margin-top:40px; font-size:18px;">
      This certifies that <b>{{ $cert->participant_name }}</b> has participated in
      <b>{{ $cert->training_title }}</b>
      @php
        $dateFrom = $cert->training_date?->format('F d, Y');
        $dateTo = $cert->training_date_to?->format('F d, Y') ?? $dateFrom;
      @endphp
      held on <b>{{ $dateFrom === $dateTo ? $dateFrom : ($dateFrom . ' to ' . $dateTo) }}</b>,
      issued by <b>{{ $cert->issuing_office }}</b>.
    </p>

    {{-- QR bottom-right --}}
    <div class="qr">
      {!! QrCode::format('svg')->size(140)->margin(1)->generate($verifyUrl) !!}
      <small class="code">{{ $cert->certificate_code }}</small>
      <small class="verify-link">{{ $verifyUrl }}</small>
      <small>Scan to verify</small>
    </div>

    @if (!empty($regionalDirectorSignatory['enabled']) && !empty($regionalDirectorSignatory['image_url']))
      <div class="signatory">
        <img src="{{ $regionalDirectorSignatory['image_url'] }}" alt="Regional Director e-signature">
      </div>
    @endif
  </div>

  <script>
    // Optional auto-open print dialog
    // window.print();
  </script>
</body>
</html>
