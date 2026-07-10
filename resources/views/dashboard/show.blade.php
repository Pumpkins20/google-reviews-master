@extends('layouts.dashboard')

@section('title', $place->place_name)
@section('page-title')
    <a href="{{ route('dashboard.index') }}" style="color: var(--brand-primary); text-decoration: none; font-size: 14px; display: inline-flex; align-items: center; gap: 4px; margin-bottom: 4px;">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width: 16px; height: 16px;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Kembali ke Overview
    </a>
    <div style="font-size: 24px; font-weight: 700; margin-top: 4px;">{{ $place->place_name }}</div>
@endsection

@section('content')
<!-- Store stats grid -->
<div class="metrics-grid" style="margin-bottom: 24px;">
    <div class="metric-card reviews">
        <div class="metric-info">
            <span class="metric-label">Jumlah Ulasan</span>
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
            <span class="metric-label">Spam Terdeteksi</span>
            <span class="metric-value">{{ number_format($spamCount) }}</span>
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

<!-- Filters Panel -->
<div class="filter-panel">
    <form action="{{ route('dashboard.show', $place->place_id) }}" method="GET" class="filter-form">
        <div class="filter-group" style="grid-column: span 2;">
            <label class="filter-label" for="search">Cari Ulasan</label>
            <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Cari nama penulis atau teks ulasan..." class="form-control">
        </div>
        
        <div class="filter-group">
            <label class="filter-label" for="rating">Rating Bintang</label>
            <select name="rating" id="rating" class="form-control">
                <option value="">Semua Bintang</option>
                @for($i = 5; $i >= 1; $i--)
                    <option value="{{ $i }}" {{ request('rating') == $i ? 'selected' : '' }}>{{ $i }} Bintang</option>
                @endfor
            </select>
        </div>
        
        <div class="filter-group">
            <label class="filter-label" for="spam_label">Status Spam AI</label>
            <select name="spam_label" id="spam_label" class="form-control">
                <option value="">Semua Label</option>
                <option value="ham" {{ request('spam_label') == 'ham' ? 'selected' : '' }}>Bukan Spam (Ham)</option>
                <option value="spam" {{ request('spam_label') == 'spam' ? 'selected' : '' }}>Terdeteksi Spam</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label class="filter-label" for="category">Kategori AI</label>
            <select name="category" id="category" class="form-control">
                <option value="">Semua Kategori</option>
                <option value="POSITIF" {{ request('category') == 'POSITIF' ? 'selected' : '' }}>POSITIF</option>
                <option value="NEGATIF" {{ request('category') == 'NEGATIF' ? 'selected' : '' }}>NEGATIF</option>
                <option value="CAMPURAN" {{ request('category') == 'CAMPURAN' ? 'selected' : '' }}>CAMPURAN</option>
                <option value="SPAM_PROMOSI" {{ request('category') == 'SPAM_PROMOSI' ? 'selected' : '' }}>SPAM PROMOSI</option>
                <option value="INDIKASI_PENIPUAN" {{ request('category') == 'INDIKASI_PENIPUAN' ? 'selected' : '' }}>INDIKASI PENIPUAN</option>
                <option value="PERTANYAAN" {{ request('category') == 'PERTANYAAN' ? 'selected' : '' }}>PERTANYAAN</option>
            </select>
        </div>
        
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="{{ route('dashboard.show', $place->place_id) }}" class="btn btn-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- Reviews List -->
<div class="reviews-container">
    @forelse($reviews as $review)
        @php
            // Parse review text JSON
            $reviewText = $review->review_text;
            $decoded = json_decode($reviewText, true);
            if (is_array($decoded)) {
                $reviewText = $decoded['id'] ?? $decoded['en'] ?? reset($decoded) ?? '';
            }
        @endphp
        <div class="review-card">
            <div class="review-card-header">
                <div class="author-meta">
                    @if($review->profile_picture)
                        <img src="{{ $review->profile_picture }}" alt="{{ $review->author }}" class="author-pic" onerror="this.src='https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y';">
                    @else
                        <div class="author-pic" style="display: flex; align-items: center; justify-content: center; background-color: var(--brand-primary-light); color: var(--brand-primary); font-weight: 700; font-size: 14px;">
                            {{ substr($review->author ?? 'G', 0, 1) }}
                        </div>
                    @endif
                    <div class="author-name-wrapper">
                        <span class="author-name">{{ $review->author ?? 'Pengguna Google Maps' }}</span>
                        <span class="review-date-meta">{{ $review->raw_date ?? \Carbon\Carbon::parse($review->review_date)->diffForHumans() }}</span>
                    </div>
                </div>
                
                <div class="rating-stars">
                    @for($i = 1; $i <= 5; $i++)
                        <svg class="star-icon {{ $i <= $review->rating ? '' : 'empty' }}" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                        </svg>
                    @endfor
                </div>
            </div>
            
            <div class="review-card-body">
                @if(empty($reviewText))
                    <span style="color: var(--text-muted); font-style: italic;">(Pengguna hanya memberikan rating bintang tanpa menulis ulasan teks)</span>
                @else
                    <p class="review-text-content">{{ $reviewText }}</p>
                @endif
            </div>
            
            <div class="review-card-footer">
                <div class="ai-analysis-tag">
                    @if($review->analysis)
                        <div class="ai-badges">
                            <!-- Spam/Ham Badge -->
                            <span class="badge spam-{{ $review->analysis->spam_label }}">
                                {{ $review->analysis->spam_label == 'spam' ? '⚠️ Spam' : '✓ Ham' }}
                            </span>
                            
                            <!-- Category Badge -->
                            <span class="badge cat-{{ $review->analysis->category }}">
                                Kategori: {{ $review->analysis->category }}
                            </span>
                            
                            <!-- Confidence Badge -->
                            <span class="badge" style="background-color: #f1f5f9; color: var(--text-secondary); font-size: 11px;">
                                Akurasi: {{ number_format($review->analysis->confidence * 100) }}%
                            </span>
                        </div>
                        
                        <!-- AI analysis reason -->
                        <div class="ai-reason {{ $review->analysis->spam_label == 'spam' ? 'spam' : '' }}">
                            {{ $review->analysis->reason }}
                        </div>
                    @else
                        <span style="font-size: 12px; color: var(--text-muted); display: flex; align-items: center; gap: 6px;">
                            <span style="width: 8px; height: 8px; background-color: var(--text-muted); border-radius: 50%;"></span>
                            Belum dianalisis oleh AI Pipeline.
                        </span>
                    @endif
                </div>
                
                <div class="meta-item">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width: 14px; height: 14px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5" />
                    </svg>
                    <span>{{ $review->likes }} Likes</span>
                </div>
            </div>
        </div>
    @empty
        <div style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 48px; text-align: center; color: var(--text-secondary);">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 12px;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p style="font-weight: 600; font-size: 16px;">Tidak ada ulasan ditemukan</p>
            <p style="font-size: 14px; margin-top: 4px;">Cobalah untuk mengubah kata kunci pencarian atau filter yang Anda terapkan.</p>
        </div>
    @endforelse
</div>

<!-- Pagination Links -->
@if($reviews->hasPages())
    <div class="pagination-wrapper">
        {{ $reviews->links() }}
    </div>
@endif
@endsection
