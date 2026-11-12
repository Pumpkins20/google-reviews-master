@extends('layouts.dashboard')

@section('title', 'Store')
@section('page-title', 'Store')

@section('content')
<div class="card-panel">
    <div class="panel-header">
        <h3 class="panel-title">Daftar Store</h3>
        <form action="{{ route('stores.index') }}" method="GET" style="display: flex; gap: 8px;">
            <input type="text" name="q" class="form-control" value="{{ $q }}" placeholder="Cari nama, kode, atau kota" style="width: 260px;">
            <button type="submit" class="btn btn-primary">Cari</button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Store</th>
                    <th>Kota</th>
                    <th>Telepon</th>
                    <th>Rating Google</th>
                    <th>Ulasan</th>
                    <th>Foto</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($stores as $store)
                    @php $place = $places->get($store->kode_toko); @endphp
                    <tr>
                        <td>{{ $store->kode_toko }}</td>
                        <td>{{ $store->nama_toko }}</td>
                        <td>{{ $store->kota_kab }}</td>
                        <td>{{ $store->telp ?: ($store->whatsapp ?: '-') }}</td>
                        <td>{{ $place && $place->reviews_avg_rating ? number_format($place->reviews_avg_rating, 1) : '-' }}</td>
                        <td>{{ $place ? $place->reviews_count : '-' }}</td>
                        <td>
                            @if($place && $place->photos)
                                {{ count($place->photos) }}
                            @elseif(!$store->link_gmaps)
                                <span style="color: var(--text-secondary);">Tanpa link Maps</span>
                            @else
                                -
                            @endif
                        </td>
                        <td><a href="{{ route('stores.show', $store->id) }}" class="btn btn-secondary btn-sm">Detail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align: center;">Tidak ada store ditemukan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="panel-body">{{ $stores->links() }}</div>
</div>
@endsection
