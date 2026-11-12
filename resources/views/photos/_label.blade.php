@php
    $labels = [
        'scam' => ['Scam', 'var(--danger-light)', '#991b1b'],
        'suspicious' => ['Mencurigakan', 'var(--warning-light)', '#92400e'],
        'ok' => ['Aman', 'var(--success-light)', '#065f46'],
        'unreadable' => ['Tidak terbaca', 'var(--info-light)', '#1e40af'],
    ];
    [$text, $bg, $fg] = $labels[$label] ?? ['Belum dianalisis', '#f1f5f9', '#475569'];
@endphp
<span class="badge" style="background-color: {{ $bg }}; color: {{ $fg }};">{{ $text }}</span>
