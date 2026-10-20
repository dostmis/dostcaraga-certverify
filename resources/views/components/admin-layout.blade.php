@props(['title' => null])

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $title ?? config('app.name', 'DOST CERTiFY') }}</title>

  {{-- Inter for everything readable, JetBrains Mono for telemetry metadata only.
       Same provider the other layouts already use; both fall back to the local
       sans/mono stacks if blocked. --}}
  <link rel="preconnect" href="https://fonts.bunny.net">
  <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|jetbrains-mono:400,500&display=swap" rel="stylesheet">

  {{-- Applies the saved theme before first paint, so the analytics pages never
       flash the wrong one. Defaults to the HUD dark theme. --}}
  <script>
    (function () {
      try {
        var t = localStorage.getItem('certify.theme');
        document.documentElement.setAttribute('data-theme', t === 'light' ? 'light' : 'dark');
      } catch (e) {
        document.documentElement.setAttribute('data-theme', 'dark');
      }
    })();
  </script>

  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
  <main class="px-4 py-8">
    {{ $slot }}
  </main>
</body>
</html>
