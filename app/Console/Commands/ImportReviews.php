<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Place;
use App\Models\Review;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ImportReviews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'grms:import-reviews {file? : Path to the google_reviews.json file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import scraped reviews from a JSON file into the MySQL database';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $filePath = $this->argument('file') ?? base_path('../google-reviews-scraper-pro/google_reviews.json');

        if (!file_exists($filePath)) {
            $this->error("File not found: " . $filePath);
            return 1;
        }

        $this->info("Reading file: " . $filePath);
        $jsonContent = file_get_contents($filePath);
        $items = json_decode($jsonContent, true);

        if (!is_array($items)) {
            $this->error("Invalid JSON format. Expected an array of reviews.");
            return 1;
        }

        $totalItems = count($items);
        $this->info("Found {$totalItems} reviews in JSON file. Processing...");

        // First, ensure all places exist
        $this->info("Syncing places (branches)...");
        $placeMap = [];
        foreach ($items as $item) {
            $placeId = $item['place_id'] ?? null;
            if (!$placeId) {
                continue;
            }

            if (!isset($placeMap[$placeId])) {
                $placeMap[$placeId] = [
                    'place_name' => $item['company'] ?? 'Toko Google Maps',
                    'created_date' => $item['created_date'] ?? now()->toIso8601String(),
                ];
            }
        }

        foreach ($placeMap as $placeId => $data) {
            Place::firstOrCreate(
                ['place_id' => $placeId],
                [
                    'place_name' => $data['place_name'],
                    'original_url' => 'https://www.google.com/maps/place/?q=place_id:' . $placeId,
                    'first_seen' => $data['created_date'],
                    'last_scraped' => now()->toIso8601String(),
                ]
            );
        }
        $this->info("Successfully synced " . count($placeMap) . " places.");

        // Now, import reviews with progress bar
        $this->info("Syncing reviews...");
        $bar = $this->output->createProgressBar($totalItems);
        $bar->start();

        $importedCount = 0;
        foreach ($items as $item) {
            if (empty($item['review_id']) || empty($item['place_id'])) {
                $bar->advance();
                continue;
            }

            $rawDate = $item['raw_date'] ?? (isset($item['review_date']) ? Carbon::parse($item['review_date'])->diffForHumans() : null);

            Review::updateOrCreate(
                [
                    'review_id' => $item['review_id'],
                    'place_id' => $item['place_id']
                ],
                [
                    'author' => $item['author'] ?? null,
                    'rating' => $item['rating'] ?? null,
                    'review_text' => isset($item['description']) ? json_encode($item['description'], JSON_UNESCAPED_UNICODE) : null,
                    'review_date' => $item['review_date'] ?? null,
                    'raw_date' => $rawDate,
                    'likes' => $item['likes'] ?? 0,
                    'user_images' => isset($item['user_images']) ? json_encode($item['user_images']) : null,
                    's3_images' => isset($item['s3_images']) ? json_encode($item['s3_images']) : null,
                    'profile_url' => $item['author_profile_url'] ?? $item['profile_url'] ?? null,
                    'profile_picture' => $item['profile_picture'] ?? null,
                    's3_profile_picture' => $item['s3_profile_picture'] ?? null,
                    'owner_responses' => isset($item['owner_responses']) ? json_encode($item['owner_responses'], JSON_UNESCAPED_UNICODE) : null,
                    'created_date' => $item['created_date'] ?? now()->toIso8601String(),
                    'last_modified' => $item['last_modified_date'] ?? now()->toIso8601String(),
                    'last_seen_session' => $item['last_seen_session'] ?? null,
                    'last_changed_session' => $item['last_changed_session'] ?? null,
                    'is_deleted' => $item['is_deleted'] ?? 0,
                    'content_hash' => $item['content_hash'] ?? null,
                    'engagement_hash' => $item['engagement_hash'] ?? null,
                    'row_version' => $item['row_version'] ?? 1,
                    'sub_ratings' => isset($item['sub_ratings']) ? json_encode($item['sub_ratings']) : null,
                ]
            );

            $importedCount++;
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info("Successfully synced {$importedCount} reviews.");

        // Update total_reviews count on each place
        $this->info("Recalculating total review counts for places...");
        $places = Place::all();
        foreach ($places as $place) {
            $count = Review::where('place_id', $place->place_id)->count();
            $place->update(['total_reviews' => $count]);
        }
        $this->info("Done!");

        return 0;
    }
}
