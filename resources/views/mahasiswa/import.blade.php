<x-layouts.app title="Import Data Mahasiswa">
    @if (! $rows)
        <div class="flex justify-center py-6 lg:py-10">
            <x-card padded class="w-full max-w-3xl">
                <p class="mb-5 text-base text-slate-600">
                    Upload file Excel (.xlsx / .xls) dengan kolom <strong>nama, nim, jurusan, kelas</strong>.
                    Belum punya file? <a href="{{ route('import.template') }}" class="font-medium text-slate-900 underline">Download template</a>.
                </p>

                <form method="POST" action="{{ route('import.preview') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="file" name="file" accept=".xlsx,.xls" required
                           class="block w-full rounded-lg border border-slate-300 px-4 py-3 text-sm shadow-sm file:mr-4 file:rounded-md file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-medium">

                    <button type="submit" class="mt-5 w-full rounded-full bg-gold-600 px-4 py-3 text-sm font-medium text-white shadow-sm transition hover:bg-gold-700 sm:w-auto">
                        Upload &amp; Preview
                    </button>
                </form>
            </x-card>
        </div>
    @else
        <div class="mb-5 grid grid-cols-3 gap-4 lg:gap-5">
            <x-card padded>
                <p class="text-xs font-medium text-slate-500">Data Ditemukan</p>
                <p class="mt-1 text-3xl font-semibold tracking-tight">{{ $summary['total'] }}</p>
            </x-card>
            <x-card padded>
                <p class="text-xs font-medium text-slate-500">Data Valid</p>
                <p class="mt-1 text-3xl font-semibold tracking-tight text-emerald-600">{{ $summary['valid'] }}</p>
            </x-card>
            <x-card padded>
                <p class="text-xs font-medium text-slate-500">Data Error</p>
                <p class="mt-1 text-3xl font-semibold tracking-tight text-rose-600">{{ $summary['error'] }}</p>
            </x-card>
        </div>

        <x-card class="overflow-hidden">
            <div class="max-h-[28rem] overflow-auto">
                <table class="w-full text-left text-sm">
                    <thead class="sticky top-0 bg-slate-50 text-xs uppercase tracking-[0.08em] text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5 lg:px-6">Baris</th>
                            <th class="px-4 py-2.5 lg:px-6">Nama</th>
                            <th class="px-4 py-2.5 lg:px-6">NIM</th>
                            <th class="px-4 py-2.5 lg:px-6">Jurusan</th>
                            <th class="px-4 py-2.5 lg:px-6">Kelas</th>
                            <th class="px-4 py-2.5 lg:px-6">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rows as $row)
                            <tr class="{{ $row['valid'] ? '' : 'bg-rose-50/50' }}">
                                <td class="px-4 py-2.5 text-slate-500 lg:px-6">{{ $row['baris'] }}</td>
                                <td class="px-4 py-2.5 lg:px-6">{{ $row['nama'] ?: '—' }}</td>
                                <td class="px-4 py-2.5 lg:px-6">{{ $row['nim'] ?: '—' }}</td>
                                <td class="px-4 py-2.5 lg:px-6">{{ $row['jurusan'] ?: '—' }}</td>
                                <td class="px-4 py-2.5 lg:px-6">{{ $row['kelas'] ?: '—' }}</td>
                                <td class="px-4 py-2.5 lg:px-6">
                                    @if ($row['valid'])
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">Valid</span>
                                    @else
                                        <span class="text-xs font-medium text-rose-600">{{ implode(', ', $row['errors']) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        <div class="mt-4 flex gap-2">
            <form method="POST" action="{{ route('import.store') }}"
                  onsubmit="return confirm('Import {{ $summary['valid'] }} data mahasiswa yang valid?');">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <button type="submit" @disabled($summary['valid'] === 0)
                        class="rounded-full bg-gold-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gold-700 disabled:cursor-not-allowed disabled:opacity-50">
                    Konfirmasi Import ({{ $summary['valid'] }} data)
                </button>
            </form>
            <a href="{{ route('import.create') }}" class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                Upload Ulang
            </a>
        </div>
    @endif
</x-layouts.app>
