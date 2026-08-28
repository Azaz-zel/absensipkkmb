<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Login' }} — Absensi Maba PKKMB INSTIKI 2026</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <div class="flex min-h-screen items-center justify-center px-4 py-12">
        <div class="w-full max-w-sm">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
