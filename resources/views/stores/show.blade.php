@extends('layouts.dashboard')

@section('title', $store->nama_toko)
@section('page-title', $store->nama_toko)

@section('content')
@if(session('success'))
    <div style="margin-bottom: 24px; padding: 16px; background-color: var(--success-light); color: #065f46; border-radius: var(--radius-md); font-weight: 500; border-left: 4px solid var(--success);">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div style="margin-bottom: 24px; padding: 16px; background-color: var(--danger-light); color: #991b1b; border-radius: var(--radius-md); font-weight: 500; border-left: 4px solid var(--danger);">{{ session('error') }}</div>
@endif

<div class="card-panel" style="margin-bottom: 24px;">
    <div class="panel-header">
        <h3 class="panel-title">Informasi Store</h3>
        <div style="display: flex; gap: 8px;">
            @if($store->link_gmaps)
                <form action="{{ route('stores.scrape', $store->id) }}" method="POST" onsubmit="return confirm('Jadwalkan scrape ulasan dan foto untuk store ini?');">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm">Ambil ulasan &amp; foto</button>
                </form>
            @endif
            <a href="{{ route('stores.index') }}" class="btn btn-secondary btn-sm">Kembali</a>
        </div>
    </div>
    <div class="panel-body">
        <table class="data-table">
            <tbody>
                <tr><th style="width: 200px;">Kode</th><td>{{ $store->kode_toko }}</td></tr>
                <tr><th>Alamat</th><td>{{ collect([$store->alamat_toko, $store->kelurahan, $store->kecamatan, $store->kota_kab, $store->provinsi, $store->kode_pos])->filter()->implode(', ') ?: '-' }}</td></tr>
                <tr><th>Telepon</th><td>{{ $store->telp ?: '-' }}</td></tr>
                <tr><th>WhatsApp</th><td>{{ $store->whatsapp ?: '-' }}</td></tr>
                <tr><th>Email</th><td>{{ $store->webmail ?: '-' }}</td></tr>
                <tr>
                    <th>Google Maps</th>
                    <td>
                        @if($store->link_gmaps)
                            <a href="{{ $store->link_gmaps }}" target="_blank" rel="noopener noreferrer">{{ $store->link_gmaps }}</a>
                        @else
                            Belum ada link
                        @endif
                    </td>
                </tr>
                @if($place)
                    <tr><th>Rating (dari ulasan tersimpan)</th><td>{{ $place->reviews_avg_rating ? number_format($place->reviews_avg_rating, 1) : '-' }} ({{ $place->reviews_count }} ulasan)</td></tr>
                    <tr><th>Terakhir di-scrape</th><td>{{ $place->last_scraped ?: '-' }}</td></tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

<div class="card-panel">
    <div class="panel-header">
        <h3 class="panel-title">Foto Suasana Toko</h3>
        <div style="display: flex; align-items: center; gap: 12px;">
            @if($place && $place->photos_updated_at)
                <span style="font-size: 12px; color: var(--text-secondary);">Diperbarui {{ \Carbon\Carbon::parse($place->photos_updated_at)->format('d M Y H:i') }}</span>
            @endif
            @if($photos->count())
                <form action="{{ route('stores.photos.analyze', $store->id) }}" method="POST" onsubmit="return confirm('Analisis foto yang belum pernah dianalisis dengan Gemini? Ini menambah biaya.');">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm">Analisis foto ({{ $photos->whereNull('analyzed_at')->count() }} belum)</button>
                </form>
            @endif
        </div>
    </div>
    <div class="panel-body">
        @if($photos->count())
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px;">
                @foreach($photos as $photo)
                    <a href="{{ $photo->isFlagged() ? route('photos.report', $photo->id) : route('photos.show', $photo->id) }}" style="position: relative; display: block;">
                        <img src="{{ $photo->imageUrl() }}" alt="Foto {{ $store->nama_toko }}" loading="lazy" referrerpolicy="no-referrer" style="width: 100%; height: 150px; object-fit: cover; border-radius: 8px; display: block; {{ $photo->isFlagged() ? 'outline: 3px solid var(--danger);' : '' }}">
                        @if($photo->label)
                            <span style="position: absolute; top: 8px; left: 8px;">@include('photos._label', ['label' => $photo->label])</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @else
            <p style="margin: 0; color: var(--text-secondary);">
                Belum ada foto. Foto diambil saat scrape:
                <code>php artisan grms:scrape-reviews --kode-toko={{ $store->kode_toko }}</code>
            </p>
        @endif
    </div>
</div>
@endsection
