@extends('layouts.dashboard')

@section('title', 'Overview')
@section('page-title', 'Overview')

@section('content')
<!-- Metrics cards -->
<div class="metrics-grid">
    <div class="metric-card stores">
        <div class="metric-info">
            <span class="metric-label">Total Cabang Toko</span>
            <span class="metric-value">{{ $totalStores }}</span>
        </div>
        <div class="metric-icon-wrapper">
            <svg class="metric-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
            </svg>
        </div>
    </div>
    
    <div class="metric-card reviews">
        <div class="metric-info">
            <span class="metric-label">Total Ulasan</span>
            <span class="metric-value">{{ number_format($totalReviews) }}</span>
        </div>
        <div class="metric-icon-wrapper">
            <svg class="metric-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
            </svg>
        </div>
    </div>
    
    <div class="metric-card spam">
        <div class="metric-info">
            <span class="metric-label">AI Deteksi Spam</span>
            <span class="metric-value">{{ number_format($totalSpam) }}</span>
        </div>
        <div class="metric-icon-wrapper">
            <svg class="metric-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
        </div>
    </div>
    
    <div class="metric-card rating">
        <div class="metric-info">
            <span class="metric-label">Rata-rata Rating</span>
            <span class="metric-value">{{ number_format($avgRating, 1) }} / 5.0</span>
        </div>
        <div class="metric-icon-wrapper">
            <svg class="metric-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.907c.961 0 1.36 1.252.583 1.882l-3.978 2.89a1 1 0 00-.364 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.978-2.89a1 1 0 00-1.176 0l-3.978 2.89c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.364-1.118l-3.978-2.89c-.77-.63-.371-1.882.583-1.882h4.907a1 1 0 00.95-.69l1.519-4.674z"></path>
            </svg>
        </div>
    </div>
</div>

<div class="section-grid">
    <!-- Stores Table Panel -->
    <div class="card-panel">
        <div class="panel-header">
            <h3 class="panel-title">Daftar Cabang Toko</h3>
        </div>
        <div class="panel-body table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama Toko</th>
                        <th>Jumlah Ulasan</th>
                        <th>Spam Terdeteksi</th>
                        <th>Rata-rata Rating</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($places as $place)
                        <tr>
                            <td><strong>{{ $place->store_code ?? '-' }}</strong></td>
                            <td>{{ $place->place_name }}</td>
                            <td>{{ number_format($place->reviews_count) }}</td>
                            <td>
                                @if($place->spam_count > 0)
                                    <span class="badge spam-spam" style="font-size: 11px;">{{ $place->spam_count }} Spam</span>
                                @else
                                    <span class="badge spam-ham" style="font-size: 11px;">0 Spam</span>
                                @endif
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="font-weight: 600;">{{ number_format($place->avg_rating, 1) }}</span>
                                    <div class="rating-stars">
                                        @for($i = 1; $i <= 5; $i++)
                                            <svg class="star-icon {{ $i <= round($place->avg_rating) ? '' : 'empty' }}" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" style="width: 14px; height: 14px;">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                            </svg>
                                        @endfor
                                    </div>
                                </div>
                            </td>
                            <td>
                                <a href="{{ route('dashboard.show', $place->place_id) }}" class="btn btn-primary btn-sm">Buka Review</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--text-muted);">Tidak ada data cabang.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- AI Distribution Panel -->
    <div class="card-panel">
        <div class="panel-header">
            <h3 class="panel-title">Distribusi Kategori AI</h3>
        </div>
        <div class="panel-body">
            @php
                $totalAnalyzed = array_sum($categoryCounts);
            @endphp
            <div class="dist-list">
                @foreach($categoryCounts as $category => $count)
                    @php
                        $percentage = $totalAnalyzed > 0 ? ($count / $totalAnalyzed) * 100 : 0;
                    @endphp
                    <div class="dist-item">
                        <div class="dist-label-wrapper">
                            <span class="dist-name">{{ $category }}</span>
                            <span class="dist-count">{{ number_format($count) }} ({{ number_format($percentage, 0) }}%)</span>
                        </div>
                        <div class="progress-track">
                            <div class="progress-bar {{ strtolower($category) }}" style="width: {{ $percentage }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
            @if($totalReviews > $totalAnalyzed)
                <div style="margin-top: 24px; padding: 12px; background-color: var(--warning-light); color: #92400e; border-radius: var(--radius-md); font-size: 12px; font-weight: 500;">
                    ⚠️ Ada <strong>{{ number_format($totalReviews - $totalAnalyzed) }}</strong> ulasan yang belum dianalisis oleh AI pipeline.
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
