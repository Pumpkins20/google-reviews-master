<?php

return [
    'api_key' => env('GEMINI_API_KEY'),
    'api_url' => env('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent'),
    'model' => env('GEMINI_MODEL', 'gemini-2.5-flash-lite'),
    'prompt_version' => 'v1.0',
    
    'prompt_template' => "Analisislah ulasan Google Maps berikut dan berikan label apakah ulasan tersebut merupakan 'spam' atau 'ham' (bukan spam).
    
Ketentuan Spam:
- Ulasan kosong atau hanya berisi karakter acak.
- Ulasan yang berisi promosi bisnis lain secara terang-terangan.
- Ulasan yang terindikasi menggunakan bot/copy-paste berulang kali untuk menjatuhkan atau menaikkan rating secara tidak wajar.

Ketentuan Kategori:
Klasifikasikan ulasan ke dalam salah satu kategori berikut:
- 'produk' (jika membahas tentang kualitas barang, warna, kelengkapan produk cat, dll)
- 'layanan' (jika membahas tentang keramahan karyawan, kecepatan melayani, bantuan teknis, dll)
- 'fasilitas' (jika membahas tentang tempat parkir, kenyamanan toko, AC, kebersihan, dll)
- 'lainnya' (jika umum atau tidak masuk kategori di atas)

Berikan analisis dalam format JSON terstruktur dengan kunci berikut:
1. 'spam_label': string ('spam' atau 'ham')
2. 'confidence': float (skor keyakinan AI dari 0.0 sampai 1.0)
3. 'category': string ('produk', 'layanan', 'fasilitas', atau 'lainnya')
4. 'reason': string (alasan singkat mengapa diklasifikasikan demikian, ditulis dalam Bahasa Indonesia)

Berikut ulasan yang harus dianalisis:
Rating Bintang: {rating}
Teks Ulasan: {review_text}
",
];
