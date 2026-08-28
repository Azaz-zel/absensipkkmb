<x-layouts.guest title="Login">
    <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <div class="mb-6 text-center">
            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gold-600 font-serif text-xl font-bold text-white">R</div>
            <h1 class="font-serif text-xl font-semibold text-slate-900">Absensi Maba PKKMB</h1>
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">INSTIKI 2026</p>
        </div>

        <x-flash />

        <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
            @csrf

            <div>
                <label for="login" class="mb-1 block text-sm font-medium text-slate-700">Email atau Username</label>
                <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus
                       class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm font-medium text-slate-700">Password</label>
                <input id="password" name="password" type="password" required
                       class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
            </div>

            <button type="submit"
                    class="w-full rounded-full bg-gold-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-gold-700">
                Masuk
            </button>
        </form>
    </div>
</x-layouts.guest>
