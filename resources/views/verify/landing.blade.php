<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Certificate Verification</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100">
  <div class="max-w-xl mx-auto mt-20 bg-white p-8 rounded-xl shadow">
    <h1 class="text-2xl font-bold mb-4">Certificate Verification</h1>

    <form method="GET" action="{{ route('cert.verify') }}">
      <input
        name="t"
        class="w-full border rounded p-3 mb-4"
        placeholder="Paste verification token"
        required
      >
      <button class="bg-black text-white px-4 py-2 rounded">
        Verify
      </button>
    </form>
  </div>
</body>
</html>
