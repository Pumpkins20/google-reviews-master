@extends('layouts.dashboard')

@section('title', 'Foto Toko')
@section('page-title', 'Foto Toko')

@section('content')
@if(session('success'))
    <div style="margin-bottom: 24px; padding: 16px; background-color: var(--success-light); color: #065f46; border-radius: var(--radius-md); font-weight: 500; border-left: 4px solid var(--success);">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div style="margin-bottom: 24px; padding: 16px; background-color: var(--danger-light); color: #991b1b; border-radius: var(--radius-md); font-weight: 500; border-left: 4px solid var(--danger);">{{ session('error') }}</div>
@endif

<div class="metrics-grid" style="margin-bottom: 24px;">
    <div class="metric-card stores">
        <div class="metric-info">
            <span class="metric-label">Foto Aktif</span>
            <span class="metric-value">{{ number_format($stats['photos']) }}</span>
        </div>
    </div>
    <div class="metric-card reviews">
        <div class="metric-info">
            <span class="metric-label">Sudah Dianalisis</span>
            <span class="metric-value">{{ number_format($stats['analyzed']) }}</span>
        </div>
    </div>
    <div class="metric-card spam">
        <div class="metric-info">
            <span class="metric-label">Ditandai Spam/Scam</span>
            <span class="metric-value">{{ number_format($stats['flagged']) }}</span>
        </div>
    </div>
    <div class="metric-card rating">
        <div class="metric-info">
            <span class="metric-label">Sudah Dilaporkan</span>
            <span class="metric-value">{{ number_format($stats['reported']) }}</span>
        </div>
    </div>
</div>

<div class="card-panel" style="margin-bottom: 24px;">
    <div class="panel-header">
        <h3 class="panel-title">Biaya Klasifikasi Gambar (Gemini)</h3>
        <span style="font-size: 12px; color: var(--text-secondary);">Model {{ $stats['model'] }}</span>
    </div>
    <div class="panel-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px;">
            <div>
                <div class="filter-label">Total biaya</div>
                <div style="font-size: 22px; font-weight: 700;">${{ number_format($stats['total_cost'], 4) }}</div>
            </div>
            <div>
                <div class="filter-label">Bulan ini</div>
                <div style="font-size: 22px; font-weight: 700;">${{ number_format($stats['month_cost'], 4) }}</div>
            </div>
            <div>
                <div class="filter-label">Rata-rata per foto</div>
                <div style="font-size: 22px; font-weight: 700;">${{ number_format($stats['avg_cost'], 6) }}</div>
            </div>
            <div>
                <div class="filter-label">Estimasi per 1.000 foto</div>
                <div style="font-size: 22px; font-weight: 700;">${{ number_format($stats['avg_cost'] * 1000, 4) }}</div>
            </div>
            <div>
                <div class="filter-label">Panggilan / token</div>
                <div style="font-size: 14px; font-weight: 600;">{{ number_format($stats['runs']) }} analisis<br>{{ number_format($stats['input_tokens']) }} masuk, {{ number_format($stats['output_tokens']) }} keluar</div>
            </div>
        </div>
        <p style="margin: 16px 0 0; font-size: 12px; color: var(--text-secondary);">
            Dihitung dari jumlah token yang dikembalikan Gemini dikali harga per juta token di <code>config/ai.php</code>. Foto yang sudah dianalisis tidak dihitung ulang kecuali diminta.
        </p>
    </div>
</div>

<div class="card-panel">
    <div class="panel-header">
        <h3 class="panel-title">Daftar Foto</h3>
        <form action="{{ route('photos.index') }}" method="GET" style="display: flex; gap: 8px;">
            <select name="status" class="form-control" style="width: 190px;">
                @foreach($filters as $value => $label)
                    <option value="{{ $value }}" {{ $filter === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <input type="text" name="q" class="form-control" value="{{ $q }}" placeholder="Cari toko atau kode" style="width: 220px;">
            <button type="submit" class="btn btn-primary">Filter</button>
        </form>
    </div>
    <div class="panel-body">
        @if($photos->count())
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px;">
                @foreach($photos as $photo)
                    @php $analysis = $photo->latestAnalysis; @endphp
                    <div style="border: 1px solid var(--border-color); border-radius: var(--radius-md); overflow: hidden; background: var(--bg-secondary);">
                    <a href="{{ $photo->isFlagged() ? route('photos.report', $photo->id) : route('photos.show', $photo->id) }}" style="text-decoration: none; color: inherit; display: block;">
                        <img src="{{ $photo->imageUrl() }}" alt="Foto toko" loading="lazy" referrerpolicy="no-referrer" style="width: 100%; height: 150px; object-fit: cover; display: block;">
                        <div style="padding: 12px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 6px;">
                                @include('photos._label', ['label' => $photo->label])
                                @if($photo->report && $photo->report->status === 'reported')
                                    <span style="font-size: 11px; color: var(--success); font-weight: 600;">Dilaporkan</span>
                                @endif
                            </div>
                            <div style="font-size: 13px; font-weight: 600;">{{ $photo->place->place_name ?? '-' }}</div>
                            @if($analysis && $analysis->extracted_phones)
                                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">{{ implode(', ', array_slice($analysis->extracted_phones, 0, 2)) }}</div>
                            @endif
                        </div>
                    </a>
                    @if($photo->isFlagged() && !($photo->report && $photo->report->status === 'reported'))
                        <div style="padding: 0 12px 12px;">
                            @if($photo->reportUrl())
                                <a href="{{ route('photos.report.go', $photo->id) }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-primary" style="width: 100%; background-color: var(--danger);">Laporkan</a>
                            @else
                                <a href="{{ route('photos.report', $photo->id) }}" class="btn btn-sm btn-secondary" style="width: 100%;" title="Link halaman foto belum tersimpan; scrape ulang toko ini untuk melengkapinya">Laporkan (manual)</a>
                            @endif
                        </div>
                    @endif
                    </div>
                @endforeach
            </div>
            <div style="margin-top: 16px;">{{ $photos->links() }}</div>
        @else
            <p style="margin: 0; text-align: center; color: var(--text-secondary);">Tidak ada foto untuk filter ini.</p>
        @endif
    </div>
</div>
@endsection
