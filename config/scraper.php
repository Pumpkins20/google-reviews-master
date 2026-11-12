<?php

// A relative SCRAPER_PATH is resolved from the project root, since commands
// cd into the scraper directory before running it.
$scraperPath = env('SCRAPER_PATH') ?: storage_path('scraper/google-reviews-scraper-pro');
if (!preg_match('#^([A-Za-z]:)?[\\\\/]#', $scraperPath)) {
    $scraperPath = base_path($scraperPath);
}

$yasogaPath = env('YASOGA_PATH') ?: storage_path('scraper/google-maps-review-scraper');
if (!preg_match('#^([A-Za-z]:)?[\\\\/]#', $yasogaPath)) {
    $yasogaPath = base_path($yasogaPath);
}

return [

    /*
    |--------------------------------------------------------------------------
    | Scraper Directory
    |--------------------------------------------------------------------------
    |
    | Location of the google-reviews-scraper-pro checkout. Defaults to
    | storage/scraper so it travels with the app when deployed.
    |
    */

    'path' => $scraperPath,

    /*
    |--------------------------------------------------------------------------
    | Python Executable
    |--------------------------------------------------------------------------
    |
    | Point this at the scraper's virtualenv interpreter on the server, e.g.
    | .../google-reviews-scraper-pro/venv/bin/python (venv\Scripts\python.exe
    | on Windows).
    |
    */

    'python' => env('SCRAPER_PYTHON', 'python'),

    /*
    |--------------------------------------------------------------------------
    | Process Timeout (seconds)
    |--------------------------------------------------------------------------
    */

    'timeout' => (int) env('SCRAPER_TIMEOUT', 3600),

    /*
    |--------------------------------------------------------------------------
    | Database File
    |--------------------------------------------------------------------------
    |
    | SQLite file written by the scraper (db_path in its config.yaml,
    | relative to the scraper directory).
    |
    */

    'db' => env('SCRAPER_DB', 'reviews.db'),

    /*
    |--------------------------------------------------------------------------
    | Yasoga Lab (google-maps-review-scraper)
    |--------------------------------------------------------------------------
    |
    | Node.js (>= 22.12) library used by the separate "Yasoga Lab" menu. The
    | wrapper script sits next to the library checkout.
    |
    */

    'yasoga' => [
        'path' => $yasogaPath,
        'node' => env('YASOGA_NODE', 'node'),
        'timeout' => (int) env('YASOGA_TIMEOUT', 300),
        'proxy' => env('YASOGA_PROXY'),
    ],

];
