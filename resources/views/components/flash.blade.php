@if ($errors->any())
    <div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700 ring-1 ring-inset ring-rose-600/20">
        <p class="font-medium">Terjadi kesalahan:</p>
        <ul class="mt-1 list-inside list-disc space-y-0.5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@php
    $toastType = (session('error') || $errors->any()) ? 'error' : (session('success') ? 'success' : null);
    $toastMessage = session('success') ?? session('error') ?? $errors->first();
@endphp

@if ($toastType)
    <div
        id="app-toast"
        role="alert"
        class="animate-toast-in fixed right-4 top-4 z-50 flex w-full max-w-sm items-start gap-3 rounded-2xl border bg-white p-4 shadow-lg {{ $toastType === 'success' ? 'border-emerald-200' : 'border-rose-200' }}"
    >
        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $toastType === 'success' ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600' }}">
            @if ($toastType === 'success')
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                </svg>
            @else
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
                </svg>
            @endif
        </div>

        <p class="flex-1 pt-1 text-sm font-medium text-slate-700">{{ $toastMessage }}</p>

        <button type="button" onclick="document.getElementById('app-toast')?.remove()"
                class="shrink-0 rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
            </svg>
        </button>
    </div>

    <script>
        setTimeout(function () {
            var toast = document.getElementById('app-toast');
            if (!toast) return;
            toast.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-0.5rem)';
            setTimeout(function () { toast?.remove(); }, 300);
        }, 4500);
    </script>
@endif
