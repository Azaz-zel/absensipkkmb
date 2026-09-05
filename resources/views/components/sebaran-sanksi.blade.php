@props(['sebaran', 'total'])

@php
    $tingkat = [
        ['level' => 'aman', 'label' => 'Tanpa Sanksi', 'rentang' => '0 poin', 'bar' => 'bg-slate-300', 'teks' => 'text-slate-600'],
        ['level' => 'ringan', 'label' => 'PKKMB JOURNEY', 'rentang' => '1-5 poin', 'bar' => 'bg-sky-500', 'teks' => 'text-sky-700'],
        ['level' => 'sedang', 'label' => 'Edukasi Dijitalisasi', 'rentang' => '6-9 poin', 'bar' => 'bg-amber-500', 'teks' => 'text-amber-700'],
        ['level' => 'berat', 'label' => 'Problem and Solution', 'rentang' => '10-19 poin', 'bar' => 'bg-orange-500', 'teks' => 'text-orange-700'],
        ['level' => 'merah', 'label' => 'Cap Merah', 'rentang' => '20+ poin', 'bar' => 'bg-rose-600', 'teks' => 'text-rose-700'],
    ];

    $pembagi = max($total, 1);
@endphp

<x-card>
    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3.5 lg:px-6">
        <h2 class="font-serif text-lg font-semibold">Sebaran Sanksi</h2>
        <span class="text-[11px] font-medium uppercase tracking-[0.15em] text-slate-400">{{ $total }} maba</span>
    </div>

    <div class="space-y-4 px-4 py-4 lg:px-6 lg:py-5">
        @foreach ($tingkat as $t)
            @php
                $jumlah = $sebaran[$t['level']];
                $persen = round($jumlah / $pembagi * 100, 1);
            @endphp
            <div>
                <div class="mb-1.5 flex items-baseline justify-between gap-3">
                    <p class="truncate text-sm font-medium {{ $t['teks'] }}">
                        {{ $t['label'] }}
                        <span class="ml-1 text-xs font-normal text-slate-400">{{ $t['rentang'] }}</span>
                    </p>
                    <p class="shrink-0 text-sm text-slate-500">
                        <span class="font-semibold text-slate-800">{{ $jumlah }}</span>
                        <span class="text-xs">({{ $persen }}%)</span>
                    </p>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full {{ $t['bar'] }}" style="width: {{ max($persen, $jumlah > 0 ? 1.5 : 0) }}%"></div>
                </div>
            </div>
        @endforeach
    </div>
</x-card>
