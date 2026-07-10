@extends('layouts.dashboard')

@section('title', 'AI Comparison')
@section('page-title', 'AI Providers Comparison')

@section('content')
<!-- Banners for Alert/Success/Error -->
@if(session('success'))
    <div style="margin-bottom: 24px; padding: 16px; background-color: var(--success-light); color: #065f46; border-radius: var(--radius-md); font-weight: 500; display: flex; align-items: center; gap: 12px; border-left: 4px solid var(--success);">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width: 20px; height: 20px; flex-shrink: 0;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span>{{ session('success') }}</span>
    </div>
@endif

@if(session('error'))
    <div style="margin-bottom: 24px; padding: 16px; background-color: var(--danger-light); color: #991b1b; border-radius: var(--radius-md); font-weight: 500; display: flex; align-items: center; gap: 12px; border-left: 4px solid var(--danger);">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width: 20px; height: 20px; flex-shrink: 0;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <span>{{ session('error') }}</span>
    </div>
@endif

@if(session('info'))
    <div style="margin-bottom: 24px; padding: 16px; background-color: var(--info-light); color: #1e40af; border-radius: var(--radius-md); font-weight: 500; display: flex; align-items: center; gap: 12px; border-left: 4px solid var(--info);">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width: 20px; height: 20px; flex-shrink: 0;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span>{{ session('info') }}</span>
    </div>
@endif

<!-- Summary Cards Grid -->
<div class="metrics-grid" style="margin-bottom: 24px;">
    <div class="metric-card rating">
        <div class="metric-info">
            <span class="metric-label">Total Pengeluaran (USD)</span>
            <span class="metric-value">${{ number_format($totalCostOverall, 6) }}</span>
        </div>
        <div class="metric-icon-wrapper" style="background-color: var(--warning-light); color: var(--warning);">
            <svg class="metric-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
    </div>
    
    <div class="metric-card reviews">
        <div class="metric-info">
            <span class="metric-label">Ulasan Teranalisis</span>
            <span class="metric-value">{{ number_format($totalAnalyzedOverall) }}</span>
        </div>
        <div class="metric-icon-wrapper">
            <svg class="metric-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
            </svg>
        </div>
    </div>
    
    <div class="metric-card stores">
        <div class="metric-info">
            <span class="metric-label">Rata-rata Latensi</span>
            <span class="metric-value">{{ number_format($avgLatencyOverall, 0) }} ms</span>
        </div>
        <div class="metric-icon-wrapper" style="background-color: var(--brand-primary-light); color: var(--brand-primary);">
            <svg class="metric-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
    </div>
    
    <div class="metric-card spam">
        <div class="metric-info">
            <span class="metric-label">Rata-rata Confidence AI</span>
            <span class="metric-value">{{ number_format($avgConfidenceOverall * 100, 1) }}%</span>
        </div>
        <div class="metric-icon-wrapper" style="background-color: var(--success-light); color: var(--success);">
            <svg class="metric-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
            </svg>
        </div>
    </div>
</div>

<div class="section-grid" style="display: grid; grid-template-columns: 1fr; gap: 24px; margin-bottom: 24px;">
    <!-- Comparison Table Card -->
    <div class="card-panel">
        <div class="panel-header" style="display: flex; align-items: center; justify-content: space-between;">
            <h3 class="panel-title">Tabel Perbandingan Performa AI</h3>
            <span style="font-size: 12px; color: var(--text-muted); font-weight: 500;">Diperbarui secara real-time</span>
        </div>
        <div class="panel-body table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Model</th>
                        <th>Ulasan Dianalisis</th>
                        <th>Rerata Kecepatan (ms)</th>
                        <th>Total Tokens (In / Out)</th>
                        <th>Total Biaya (USD)</th>
                        <th>Rerata Confidence</th>
                        <th>Spam / Ham Ratio</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($metrics as $row)
                        @php
                            // Calculate max cost and latency to draw representative visual indicators
                            $maxLatency = 5000; // 5 seconds ceiling for display
                            $latencyPct = min(100, ($row->avg_latency / $maxLatency) * 100);
                        @endphp
                        <tr>
                            <td>
                                <span style="font-weight: 600; text-transform: uppercase; letter-spacing: 0.02em; display: inline-flex; align-items: center; gap: 6px;">
                                    <span style="width: 8px; height: 8px; border-radius: 50%; background-color: {{ 
                                        $row->provider === 'gemini' ? '#0ea5e9' : 
                                        ($row->provider === 'openai' ? '#10b981' : 
                                        ($row->provider === 'groq' ? '#f59e0b' : '#94a3b8'))
                                    }}"></span>
                                    {{ $row->provider }}
                                </span>
                            </td>
                            <td><code style="font-size: 12px; font-weight: 600; color: #b45309; background-color: #fef3c7; padding: 2px 6px; border-radius: 4px;">{{ $row->model }}</code></td>
                            <td><strong>{{ number_format($row->total_reviews) }}</strong> ulasan</td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 4px; min-width: 120px;">
                                    <span style="font-weight: 600;">{{ number_format($row->avg_latency, 0) }} ms</span>
                                    <div class="progress-track" style="height: 4px; background-color: #e2e8f0; width: 100%;">
                                        <div class="progress-bar" style="width: {{ $latencyPct }}%; background-color: {{ $row->avg_latency > 2500 ? 'var(--danger)' : ($row->avg_latency > 1000 ? 'var(--warning)' : 'var(--success)') }};"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span style="font-size: 13px; color: var(--text-secondary);">
                                    {{ number_format($row->total_input_tokens) }} <span style="color: var(--text-muted);">in</span> / 
                                    {{ number_format($row->total_output_tokens) }} <span style="color: var(--text-muted);">out</span>
                                </span>
                            </td>
                            <td>
                                <span style="font-weight: 700; color: #15803d; font-family: monospace; font-size: 14px;">
                                    ${{ number_format($row->total_cost, 6) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge" style="background-color: {{ $row->avg_confidence >= 0.9 ? 'var(--success-light)' : ($row->avg_confidence >= 0.8 ? 'var(--warning-light)' : 'var(--danger-light)') }}; color: {{ $row->avg_confidence >= 0.9 ? 'var(--success)' : ($row->avg_confidence >= 0.8 ? 'var(--warning)' : 'var(--danger)') }};">
                                    {{ number_format($row->avg_confidence * 100, 1) }}%
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span class="badge spam-spam" style="font-size: 10px; padding: 2px 6px;">{{ $row->spam_count }} Spam</span>
                                    <span class="badge spam-ham" style="font-size: 10px; padding: 2px 6px;">{{ $row->ham_count }} Ham</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 32px;">Tidak ada data perbandingan. Jalankan analisis batch untuk mengisi data.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="section-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
    <!-- Interactive Batch Runner Card -->
    <div class="card-panel">
        <div class="panel-header">
            <h3 class="panel-title">Jalankan Analisis Batch (Manual)</h3>
        </div>
        <div class="panel-body">
            <form action="{{ route('dashboard.comparison.analyze') }}" method="POST" style="display: flex; flex-direction: column; gap: 16px;">
                @csrf
                <div class="filter-group" style="width: 100%;">
                    <label class="filter-label" for="provider">Pilih Provider AI</label>
                    <select name="provider" id="provider" class="form-control" required>
                        @foreach($providers as $key => $name)
                            <option value="{{ $key }}" {{ config('ai.default') === $key ? 'selected' : '' }}>
                                {{ $name }} (Sisa: {{ number_format($unanalyzedCounts[$key]) }} ulasan)
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="filter-group">
                        <label class="filter-label" for="limit">Jumlah Ulasan</label>
                        <select name="limit" id="limit" class="form-control" required>
                            <option value="5">5 Ulasan</option>
                            <option value="10" selected>10 Ulasan</option>
                            <option value="20">20 Ulasan</option>
                            <option value="50">50 Ulasan</option>
                            <option value="100">100 Ulasan</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label" for="batch_size">Ukuran Batch (RPM)</label>
                        <select name="batch_size" id="batch_size" class="form-control" required>
                            <option value="5">5 ulasan / request</option>
                            <option value="10" selected>10 ulasan / request</option>
                            <option value="20">20 ulasan / request</option>
                        </select>
                    </div>
                </div>
                
                <div style="margin-top: 12px;">
                    <button type="submit" class="btn btn-primary" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; height: 44px; font-size: 14px;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width: 18px; height: 18px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        <span>Jalankan Batch Analisis Sekarang</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Pricing Schema Info Card -->
    <div class="card-panel">
        <div class="panel-header">
            <h3 class="panel-title">Skema Biaya Token & Info Model</h3>
        </div>
        <div class="panel-body">
            <div style="display: flex; flex-direction: column; gap: 14px;">
                <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.6;">
                    Biaya eksekusi dihitung secara dinamis berdasarkan jumlah token prompt input dan jawaban output yang dikembalikan oleh API provider dikalikan dengan rate standard berikut:
                </p>
                <div style="background-color: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 16px;">
                    <ul style="list-style: none; padding-left: 0; display: flex; flex-direction: column; gap: 10px; font-size: 13px;">
                        <li style="display: flex; justify-content: space-between; align-items: center;">
                            <span><strong>Gemini 2.5 Flash Lite</strong></span>
                            <span style="font-family: monospace; color: #15803d;">$0.030 / $0.120 per 1M tok</span>
                        </li>
                        <li style="display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed var(--border-color); padding-top: 8px;">
                            <span><strong>OpenAI GPT-4o Mini</strong></span>
                            <span style="font-family: monospace; color: #15803d;">$0.150 / $0.600 per 1M tok</span>
                        </li>
                        <li style="display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed var(--border-color); padding-top: 8px;">
                            <span><strong>Groq Llama 3.1 8B</strong></span>
                            <span style="font-family: monospace; color: #15803d;">$0.050 / $0.080 per 1M tok</span>
                        </li>
                        <li style="display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed var(--border-color); padding-top: 8px;">
                            <span><strong>Mock Simulation Driver</strong></span>
                            <span style="font-family: monospace; color: var(--text-muted);">$0.000 (Simulasi Lokal)</span>
                        </li>
                    </ul>
                </div>
                <div style="background-color: var(--info-light); color: #1e40af; border-radius: var(--radius-md); padding: 12px; font-size: 12px; font-weight: 500; line-height: 1.5;">
                    💡 <strong>Tips Efisiensi:</strong> Dengan menggunakan **Batching (5-10 ulasan per request)**, kita bisa menghemat overhead token sistem prompt sebesar 80% dan memangkas penggunaan kuota RPM / RPD secara signifikan!
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
