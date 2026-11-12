@extends('layouts.dashboard')

@section('title', 'Detail Foto')
@section('page-title', $reportMode ? 'Laporkan Foto' : 'Detail Foto')

@php
    $mapsLink = $photo->maps_url ?: ($photo->place->original_url ?? null);
    $official = $analysis ? ($analysis->official_phones ?: []) : [];
    $statusLabels = \App\Models\PhotoReport::STATUSES;
    $currentStatus = $report ? $report->status : 'pending';
@endphp

@section('content')
@if(session('success'))
    <div style="margin-bottom: 24px; padding: 16px; background-color: var(--success-light); color: #065f46; border-radius: var(--radius-md); font-weight: 500; border-left: 4px solid var(--success);">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div style="margin-bottom: 24px; padding: 16px; background-color: var(--danger-light); color: #991b1b; border-radius: var(--radius-md); font-weight: 500; border-left: 4px solid var(--danger);">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div style="margin-bottom: 24px; padding: 16px; background-color: var(--danger-light); color: #991b1b; border-radius: var(--radius-md); font-weight: 500; border-left: 4px solid var(--danger);">{{ $errors->first() }}</div>
@endif

<div style="display: grid; grid-template-columns: minmax(280px, 1fr) minmax(320px, 1.2fr); gap: 24px; align-items: start;">
    <div class="card-panel">
        <a href="{{ $photo->imageUrl('w1600') }}" target="_blank" rel="noopener noreferrer">
            <img src="{{ $photo->imageUrl('w900') }}" alt="Foto toko" referrerpolicy="no-referrer" style="width: 100%; display: block;">
        </a>
        <div class="panel-body" style="display: flex; gap: 8px; flex-wrap: wrap;">
            @if($mapsLink)
                <a href="{{ $mapsLink }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm">{{ $photo->maps_url ? 'Buka foto di Google Maps' : 'Buka toko di Google Maps' }}</a>
            @endif
            <form action="{{ route('photos.analyze', $photo->id) }}" method="POST" onsubmit="return confirm('Analisis ulang foto ini dengan Gemini? Ini menambah biaya.');">
                @csrf
                <button type="submit" class="btn btn-secondary btn-sm">{{ $analysis ? 'Analisis ulang' : 'Analisis sekarang' }}</button>
            </form>
            @if($photo->place)
                <a href="{{ $store ? route('stores.show', $store->id) : route('photos.index') }}" class="btn btn-secondary btn-sm">Kembali ke toko</a>
            @endif
        </div>
    </div>

    <div>
        @if($photo->isFlagged())
            <div class="card-panel" style="margin-bottom: 24px; border-left: 4px solid var(--danger);">
                <div class="panel-header">
                    <h3 class="panel-title">Laporkan ke Google</h3>
                    <span style="font-size: 13px; font-weight: 600;">{{ $statusLabels[$currentStatus] }}</span>
                </div>
                <div class="panel-body">
                    @if($photo->reportUrl())
                        <p style="margin-top: 0;">Tombol di bawah membuka halaman laporan Google untuk foto ini (perlu login akun Google). Pilih alasan, kirim, lalu kembali ke sini dan ubah status.</p>
                        <a href="{{ route('photos.report.go', $photo->id) }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary" style="margin-bottom: 16px; background-color: var(--danger);">Laporkan ke Google</a>
                    @else
                        <p style="margin-top: 0;">Tautan laporan langsung belum tersedia untuk foto ini (link halaman foto belum tersimpan; scrape berikutnya akan menyimpannya). Sementara, laporkan lewat Google Maps:</p>
                        <ol style="margin: 0 0 16px 18px; padding: 0; line-height: 1.7;">
                            <li>Buka foto di Google Maps, lalu menu titik tiga pada foto.</li>
                            <li>Pilih <strong>Laporkan foto</strong>, pilih alasan, dan kirim.</li>
                        </ol>
                        @if($mapsLink)
                            <a href="{{ $mapsLink }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary" style="margin-bottom: 16px;">Buka toko di Google Maps</a>
                        @endif
                    @endif
                    <form action="{{ route('photos.report.update', $photo->id) }}" method="POST" class="filter-form" style="grid-template-columns: 1fr;">
                        @csrf
                        <div class="filter-group">
                            <label class="filter-label" for="status">Status laporan</label>
                            <select id="status" name="status" class="form-control">
                                @foreach($statusLabels as $value => $label)
                                    <option value="{{ $value }}" {{ $currentStatus === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label" for="note">Catatan (opsional)</label>
                            <input type="text" id="note" name="note" class="form-control" maxlength="1000" value="{{ old('note', $report->note ?? '') }}">
                        </div>
                        <div class="filter-group">
                            <button type="submit" class="btn btn-primary">Simpan status</button>
                        </div>
                    </form>
                    @if($report && $report->reported_at)
                        <p style="font-size: 12px; color: var(--text-secondary); margin-bottom: 0;">Dilaporkan pada {{ $report->reported_at->format('d M Y H:i') }}.</p>
                    @endif
                </div>
            </div>
        @endif

        <div class="card-panel" style="margin-bottom: 24px;">
            <div class="panel-header">
                <h3 class="panel-title">Hasil Analisis</h3>
                @include('photos._label', ['label' => $photo->label])
            </div>
            <div class="panel-body">
                @if($analysis)
                    <table class="data-table">
                        <tbody>
                            <tr><th style="width: 190px;">Toko</th><td>{{ $photo->place->place_name ?? '-' }} @if($photo->place && $photo->place->store_code)({{ $photo->place->store_code }})@endif</td></tr>
                            <tr><th>Nomor resmi toko</th><td>{{ $store ? (collect([$store->telp, $store->whatsapp, $store->nohp_delivery])->filter()->implode(' | ') ?: 'belum terdaftar') : '-' }}</td></tr>
                            <tr><th>Nomor resmi perusahaan</th><td>{{ implode(' | ', (array) config('photo_ai.company_phones')) ?: '-' }} <span style="font-size: 12px; color: var(--text-secondary);">(berlaku untuk semua toko)</span></td></tr>
                            <tr>
                                <th>Nomor di foto</th>
                                <td>
                                    @php
                                        $statusStyles = [
                                            'official' => ['resmi', 'var(--success)'],
                                            'near' => ['mirip nomor resmi', '#b45309'],
                                            'foreign' => ['tidak terdaftar', 'var(--danger)'],
                                            'ignored' => ['diabaikan (kemasan produk)', 'var(--text-secondary)'],
                                        ];
                                        $roleLabels = \App\Services\Ai\PhotoClassifier::ROLE_LABELS;
                                    @endphp
                                    @if($analysis->phone_details)
                                        @foreach($analysis->phone_details as $d)
                                            @php [$text, $color] = $statusStyles[$d['status']] ?? [$d['status'], 'inherit']; @endphp
                                            <div style="margin-bottom: 6px;">
                                                {{ $d['number'] }}
                                                <span style="font-size: 12px; font-weight: 600; color: {{ $color }};">
                                                    {{ $d['status'] === 'official' ? ($d['source'] === 'company' ? 'resmi perusahaan' : 'resmi toko') : $text }}
                                                </span>
                                                <div style="font-size: 12px; color: var(--text-secondary);">
                                                    {{ $roleLabels[$d['role']] ?? $d['role'] }}
                                                    @if(!empty($d['edited'])) &middot; tampak ditempel/diubah @endif
                                                    @if(isset($d['clear']) && !$d['clear']) &middot; digit kurang jelas @endif
                                                    @if(!empty($d['nearest'])) &middot; mirip {{ $d['nearest'] }} (selisih {{ $d['distance'] }} digit) @endif
                                                </div>
                                            </div>
                                        @endforeach
                                        @if(!count($analysis->phone_details)) Tidak ada nomor terbaca @endif
                                    @else
                                        @forelse($analysis->extracted_phones ?: [] as $phone)
                                            @php $isOfficial = \App\Support\PhoneNumbers::matches($phone, $official); @endphp
                                            <div>{{ $phone }}
                                                <span style="font-size: 12px; font-weight: 600; color: {{ $isOfficial ? 'var(--success)' : 'var(--danger)' }};">{{ $isOfficial ? 'resmi' : 'tidak terdaftar' }}</span>
                                            </div>
                                        @empty
                                            Tidak ada nomor terbaca
                                        @endforelse
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Tanda lain</th>
                                <td>
                                    @php $flags = $analysis->flags ?: []; $any = false; @endphp
                                    @if(!empty($flags['qr_code'])) QR code<br>@php $any = true; @endphp @endif
                                    @if(!empty($flags['social_handles'])) Akun sosial: {{ implode(', ', $flags['social_handles']) }}<br>@php $any = true; @endphp @endif
                                    @if(!empty($flags['promo_category'])) Promosi bisnis lain<br>@php $any = true; @endphp @endif
                                    @if(!empty($flags['unverifiable_phones'])) Toko tanpa nomor terdaftar, nomor di foto tidak bisa diverifikasi<br>@php $any = true; @endphp @endif
                                    @unless($any) Tidak ada @endunless
                                </td>
                            </tr>
                            <tr><th>Kategori Gemini</th><td>{{ $analysis->category ?: '-' }} @if($analysis->confidence !== null)(yakin {{ number_format($analysis->confidence * 100) }}%)@endif</td></tr>
                            <tr><th>Alasan</th><td>{{ $analysis->reason ?: '-' }}</td></tr>
                        </tbody>
                    </table>
                @else
                    <p style="margin: 0; color: var(--text-secondary);">Foto ini belum dianalisis. Tekan <strong>Analisis sekarang</strong>.</p>
                @endif
            </div>
        </div>

        @if($analysis)
            <div class="card-panel">
                <div class="panel-header"><h3 class="panel-title">Biaya Analisis Ini</h3></div>
                <div class="panel-body">
                    <table class="data-table">
                        <tbody>
                            <tr><th style="width: 190px;">Model</th><td>{{ $analysis->provider }} / {{ $analysis->model }}</td></tr>
                            <tr><th>Token</th><td>{{ number_format($analysis->input_tokens) }} masuk, {{ number_format($analysis->output_tokens) }} keluar</td></tr>
                            <tr><th>Biaya</th><td>{{ $analysis->cost !== null ? '$' . number_format($analysis->cost, 6) : 'harga model belum dikonfigurasi' }}</td></tr>
                            <tr><th>Waktu proses</th><td>{{ number_format($analysis->execution_time_ms) }} ms</td></tr>
                            <tr><th>Dianalisis</th><td>{{ $analysis->analyzed_at ? $analysis->analyzed_at->format('d M Y H:i') : '-' }} ({{ $photo->analyses->count() }} kali)</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
