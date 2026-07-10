<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default AI provider that will be used to analyze
    | reviews. This provider will be resolved by the AiManager.
    |
    | Supported: "gemini", "openai", "groq", "mock"
    |
    */
    'default' => env('AI_PROVIDER', 'gemini'),

    /*
    |--------------------------------------------------------------------------
    | AI Providers Configurations
    |--------------------------------------------------------------------------
    |
    | Here you can configure the settings for each supported AI provider.
    |
    */
    'providers' => [
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-2.5-flash-lite'),
            'api_url' => env('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/'),
        ],
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'api_url' => env('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions'),
        ],
        'groq' => [
            'api_key' => env('GROQ_API_KEY'),
            'model' => env('GROQ_MODEL', 'llama-3.1-8b-instant'),
            'api_url' => env('GROQ_API_URL', 'https://api.groq.com/openai/v1/chat/completions'),
        ],
        'mock' => [
            'model' => 'mock-gemini-2.5-lite',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Pricing (USD per 1,000,000 tokens)
    |--------------------------------------------------------------------------
    |
    | This is used to estimate the cost of AI requests dynamically based on
    | input and output token counts returned by the providers.
    |
    */
    'pricing' => [
        'gemini-2.5-flash-lite' => [
            'input' => 0.03,
            'output' => 0.12,
        ],
        'gemini-2.5-flash' => [
            'input' => 0.075,
            'output' => 0.30,
        ],
        'gemini-1.5-flash' => [
            'input' => 0.075,
            'output' => 0.30,
        ],
        'llama-3.1-8b-instant' => [
            'input' => 0.05,
            'output' => 0.08,
        ],
        'llama-3.3-70b-specdec' => [
            'input' => 0.59,
            'output' => 0.79,
        ],
        'qwen-2.5-coder-32b' => [
            'input' => 0.59,
            'output' => 0.79,
        ],
        'gpt-4o-mini' => [
            'input' => 0.15,
            'output' => 0.60,
        ],
        'gpt-4o' => [
            'input' => 2.50,
            'output' => 10.00,
        ],
        'mock-gemini-2.5-lite' => [
            'input' => 0.00,
            'output' => 0.00,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Prompt Version & Template
    |--------------------------------------------------------------------------
    */
    'prompt_version' => 'v2.0-batched',

    'prompt_template' => "Kamu adalah seorang analis data dan moderator ulasan Google Maps yang ahli dalam mendeteksi sentimen, spam, dan modus penipuan. Tugasmu adalah mengklasifikasikan kumpulan ulasan pelanggan ke dalam kategori yang paling tepat dan memberikan alasan singkat.

KATEGORI YANG TERSEDIA:
1. POSITIF: Ulasan yang menunjukkan kepuasan terhadap produk, layanan, atau fasilitas.
2. NEGATIF: Ulasan yang menunjukkan kekecewaan murni tanpa ada unsur promosi ke pihak lain.
3. CAMPURAN: Ulasan yang memuat unsur pujian sekaligus kritik (misal: barang bagus, tapi pelayanan buruk).
4. SPAM_PROMOSI: Ulasan yang berisi promosi bot, link, nomor kontak yang tidak relevan, atau ajakan untuk membeli di toko pesaing.
5. INDIKASI_PENIPUAN: Ulasan yang merupakan modus penipuan (misal: mengarahkan ke nomor WA/rekening palsu dengan kedok admin) atau laporan dari korban penipuan.
6. PERTANYAAN: Teks yang bukan ulasan, melainkan pertanyaan mengenai stok, harga, atau jam operasional.

ATURAN KLASIFIKASI:
- Hati-hati dengan trik penipu yang menulis \"HATI-HATI PENIPUAN\" namun diakhiri dengan mengarahkan ke nomor WA/IG tertentu. Ini harus masuk ke INDIKASI_PENIPUAN.
- Jika ulasan terlalu pendek dan tidak bermakna (misal: \"no rek\"), masukkan ke SPAM_PROMOSI.
- Output HARUS selalu dalam format JSON tanpa tambahan teks markdown lainnya.

CONTOH KLASIFIKASI (FEW-SHOT LEARNING):
Input ulasan untuk dianalisis:
[
  {\"review_id\": \"rev-1\", \"rating\": 1, \"review_text\": \"HATI-HATI PENIPUAN! mending order via WA admin 0812-3456-7890 dijamin lebih murah dan bergaransi\"},
  {\"review_id\": \"rev-2\", \"rating\": 4, \"review_text\": \"Barang kurang bagus, agak kecewa tapi pelayanannya ramah kok\"},
  {\"review_id\": \"rev-3\", \"rating\": 1, \"review_text\": \"Jangan beli di sini! kena tipu. Mending ke toko sebelah cek IG @toko.cat.murah lebih amanah\"},
  {\"review_id\": \"rev-4\", \"rating\": 5, \"review_text\": \"Pelayanan memuaskan, cat lengkap, harga bersaing. Recommended!\"},
  {\"review_id\": \"rev-7\", \"rating\": 5, \"review_text\": \"Mau tanya stok cat tembok putih masih ada? terima kasih\"}
]

Output:
{
  \"reviews\": [
    {\"review_id\": \"rev-1\", \"kategori\": \"INDIKASI_PENIPUAN\", \"confidence\": 0.95, \"alasan\": \"Terdapat pola penipuan dengan mengarahkan transaksi di luar platform menggunakan nomor WA tidak resmi.\"},
    {\"review_id\": \"rev-2\", \"kategori\": \"CAMPURAN\", \"confidence\": 0.90, \"alasan\": \"Memuat sentimen negatif pada produk (kurang bagus) namun positif pada pelayanan (ramah).\"},
    {\"review_id\": \"rev-3\", \"kategori\": \"SPAM_PROMOSI\", \"confidence\": 0.95, \"alasan\": \"Menggunakan kedok ulasan negatif untuk mempromosikan toko pesaing di Instagram.\"},
    {\"review_id\": \"rev-4\", \"kategori\": \"POSITIF\", \"confidence\": 0.98, \"alasan\": \"Ulasan murni berisi kepuasan pelanggan terhadap produk dan harga.\"},
    {\"review_id\": \"rev-7\", \"kategori\": \"PERTANYAAN\", \"confidence\": 0.95, \"alasan\": \"Teks berupa pertanyaan ketersediaan barang, bukan ulasan pengalaman.\"}
  ]
}

TUGASMU SEKARANG:
Klasifikasikan ulasan-ulasan berikut ke dalam format JSON yang sama dengan satu kunci utama 'reviews' yang berisi array hasil analisis.
Kembalikan HANYA format JSON yang valid tanpa markdown code block, penjelasan, atau teks tambahan apapun.
Input ulasan untuk dianalisis:
{reviews_json}",
];
