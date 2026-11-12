<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Photo classification (Gemini vision)
    |--------------------------------------------------------------------------
    |
    | Photos of a store on Google Maps are checked for contact details that do
    | not belong to the store (replaced phone numbers, foreign QR codes, ...).
    | Every call is priced from the token counts Gemini returns and stored in
    | photo_analyses, so the cost of the whole feature stays visible.
    |
    */

    'enabled' => (bool) env('PHOTO_AI_ENABLED', true),

    // Classify new photos right after a scrape (set false to only run on demand).
    'auto_after_scrape' => (bool) env('PHOTO_AI_AUTO', true),

    // Upper bound of photos classified per run; keeps one scrape from running up the bill.
    'max_per_run' => (int) env('PHOTO_AI_MAX_PER_RUN', 60),

    // Defaults to the model configured for review analysis (config/ai.php).
    'model' => env('PHOTO_AI_MODEL', env('GEMINI_MODEL', 'gemini-2.5-flash-lite')),

    // Image width requested from Google when downloading a photo for Gemini.
    'image_width' => (int) env('PHOTO_AI_IMAGE_WIDTH', 800),

    // Gemini requests per minute. 12 stays under the free tier's limit of about 15;
    // raise it (or set 0 to disable pacing) when the key is on a paid tier.
    'requests_per_minute' => (int) env('PHOTO_AI_RPM', 12),

    // Company-wide official numbers (e.g. the complaints & suggestions line). They may appear
    // on any store's photos; per-store numbers come from the store table.
    'company_phones' => array_filter(array_map('trim', explode(',', env('PHOTO_AI_COMPANY_PHONES', '0878 0816 3003')))),

    // A number that is not official but differs from an official one by at most this many
    // digits (and has at least this many digits) is "suspicious", not "scam": it may be
    // a misread digit or a tampered one.
    'near_match_max_distance' => (int) env('PHOTO_AI_NEAR_DISTANCE', 2),
    'near_match_min_digits' => (int) env('PHOTO_AI_NEAR_MIN_DIGITS', 9),

    'prompt_version' => 'photo-v2',

    // Social accounts that belong to the company; seeing them on a photo is normal and
    // is not flagged. (Websites are not checked at all.)
    'official_handles' => array_filter(array_map('trim', explode(',', env('PHOTO_AI_OFFICIAL_HANDLES', '')))),

];
