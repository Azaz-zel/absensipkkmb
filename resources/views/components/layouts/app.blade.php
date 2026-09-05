<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Dashboard' }} — Absensi Maba PKKMB INSTIKI 2026</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <input type="checkbox" id="sidebar-toggle" class="peer hidden">

    <div class="fixed inset-0 z-30 bg-slate-900/50 opacity-0 pointer-events-none transition-opacity peer-checked:opacity-100 peer-checked:pointer-events-auto lg:hidden">
        <label for="sidebar-toggle" class="absolute inset-0 cursor-pointer"></label>
    </div>

    <aside class="fixed inset-y-0 left-0 z-40 w-72 -translate-x-full overflow-y-auto border-r border-slate-200 bg-white transition-transform duration-200 peer-checked:translate-x-0 lg:translate-x-0">
        <div class="flex h-full flex-col p-4 lg:p-5">
            <div class="mb-8 px-2 leading-tight">
                <p class="font-serif text-lg font-semibold">Ralph de Vinca</p>
                <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Technology</p>
            </div>

            <nav class="flex-1 space-y-1">
                <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span>Dashboard</span>
                </x-sidebar-link>

                @unless (auth()->user()?->isManajer())
                <x-sidebar-link :href="route('absensi.index')" :active="request()->routeIs('absensi.*')">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Absensi</span>
                </x-sidebar-link>

                <x-sidebar-link :href="route('rekap.mahasiswa')" :active="request()->routeIs('rekap.*')">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                    </svg>
                    <span>Rekap Kehadiran</span>
                </x-sidebar-link>
                @endunless

                @if (auth()->user()?->isAdmin() || auth()->user()?->isManajer())
                <x-sidebar-link :href="route('poin.index')" :active="request()->routeIs('poin.*')">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                    <span>Poin Mahasiswa</span>
                </x-sidebar-link>
                @endif

                @if (auth()->user()?->isAdmin())
                    <p class="px-3 pt-5 pb-1.5 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">Kelola</p>

                    <x-sidebar-link :href="route('kelas.index')" :active="request()->routeIs('kelas.*')">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                        </svg>
                        <span>Kelola Kelas</span>
                    </x-sidebar-link>

                    <x-sidebar-link :href="route('sesi.index')" :active="request()->routeIs('sesi.*')">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Sesi Absensi</span>
                    </x-sidebar-link>

                    <x-sidebar-link :href="route('mahasiswa.index')" :active="request()->routeIs('mahasiswa.*')">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                        </svg>
                        <span>Data Mahasiswa</span>
                    </x-sidebar-link>

                    <x-sidebar-link :href="route('import.create')" :active="request()->routeIs('import.*')">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        <span>Import Excel</span>
                    </x-sidebar-link>

                    <x-sidebar-link :href="route('users.index')" :active="request()->routeIs('users.*')">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span>Akun Pengguna</span>
                    </x-sidebar-link>
                @endif
            </nav>

            <div class="mt-4 border-t border-slate-200 pt-4">
                <div class="mb-2 flex items-center gap-3 rounded-lg px-3 py-2">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-600">
                        {{ strtoupper(substr(auth()->user()?->name ?? '?', 0, 1)) }}
                    </div>
                    <div class="min-w-0 leading-tight">
                        <p class="truncate text-sm font-medium text-slate-900">{{ auth()->user()?->name }}</p>
                        <p class="text-xs uppercase text-slate-500">{{ auth()->user()?->role }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-3 rounded-full px-3 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                        </svg>
                        <span>Keluar</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <div class="lg:pl-72">
        <header class="sticky top-0 z-20 flex items-center gap-3 border-b border-slate-200 bg-white/80 px-4 py-3 backdrop-blur lg:border-0 lg:bg-linear-to-r lg:from-gold-700 lg:to-gold-900 lg:px-8 lg:py-6 lg:shadow-md lg:shadow-gold-900/20">
            <label for="sidebar-toggle" class="cursor-pointer rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="Buka menu">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                </svg>
            </label>

            <div>
                <h1 class="font-serif text-xl font-semibold tracking-tight lg:text-2xl lg:text-white">{{ $title ?? 'Dashboard' }}</h1>
                <p class="hidden text-sm text-gold-100 lg:block">{{ now()->translatedFormat('l, d F Y') }}</p>
            </div>

            <div class="ml-auto flex items-center gap-2 text-sm text-slate-600 lg:hidden">
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium uppercase text-slate-500">
                    {{ auth()->user()?->role }}
                </span>
            </div>
        </header>

        <main class="mx-auto max-w-screen-2xl px-4 py-6 lg:px-8 lg:py-8">
            <x-flash />

            {{ $slot }}
        </main>
    </div>

    <script>
        setTimeout(function () {
            window.location.reload();
        }, {{ (config('session.lifetime') * 60 + 5) * 1000 }});
    </script>
</body>
</html>
