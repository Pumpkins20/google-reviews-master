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
    protected $signature = 'grms:import-reviews {file? : Path to the reviews.db or google_reviews.json file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import scraped reviews from reviews.db (SQLite) or a JSON file into MySQL';

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
        $filePath = $this->argument('file') ?? base_path('../google-reviews-scraper-pro/reviews.db');

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'db' || $extension === 'sqlite') {
            // Check if SQLite PDO driver is available
            if (!in_array('sqlite', \PDO::getAvailableDrivers())) {
                $this->warn("SQLite PDO driver is not enabled in your PHP installation.");
                $this->info("Attempting to export database to JSON using the Python scraper instead...");

                $scraperPath = base_path('../google-reviews-scraper-pro');
                $jsonPath = base_path('../google-reviews-scraper-pro/google_reviews.json');
                
                // 1. Pre-sync place names using Python script directly from reviews.db
                if (file_exists($filePath)) {
                    $this->syncPlacesFromSQLiteViaPython($filePath);
                }

                // 2. Run python start.py export -o google_reviews.json
                // We use cd to the directory to make sure start.py loads local modules correctly
                $cmd = "cd " . escapeshellarg($scraperPath) . " && python start.py export -o " . escapeshellarg($jsonPath);
                $this->info("Running command: " . $cmd);
                
                exec($cmd, $output, $returnVar);
                
                if ($returnVar === 0 && file_exists($jsonPath)) {
                    $this->info("Successfully compiled SQLite DB to JSON!");
                    return $this->importFromJSON($jsonPath);
                } else {
                    $this->error("Failed to export SQLite database to JSON via python. Please check if python is in your system PATH.");
                    return 1;
                }
            }

            if (!file_exists($filePath)) {
                $this->error("File not found: " . $filePath);
                return 1;
            }

            return $this->importFromSQLite($filePath);
        } else {
            if (!file_exists($filePath)) {
                $this->error("File not found: " . $filePath);
                return 1;
            }
            return $this->importFromJSON($filePath);
        }
    }

    /**
     * Import reviews from SQLite database
     *
     * @param string $dbPath
     * @return int
     */
    protected function importFromSQLite($dbPath)
    {
        $this->info("Connecting to SQLite database: " . $dbPath);
        
        try {
            $pdo = new \PDO("sqlite:" . $dbPath);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\Exception $e) {
            $this->error("Failed to connect to SQLite: " . $e->getMessage());
            return 1;
        }

        // 1. Sync places (branches)
        $this->info("Syncing places (branches)...");
        $placeStmt = $pdo->query("SELECT * FROM places");
        $places = $placeStmt->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($places as $place) {
            Place::updateOrCreate(
                ['place_id' => $place['place_id']],
                [
                    'place_name' => $place['place_name'] ?? 'Toko Google Maps',
                    'original_url' => $place['original_url'] ?? ('https://www.google.com/maps/place/?q=place_id:' . $place['place_id']),
                    'first_seen' => $place['first_seen'] ?? now()->toIso8601String(),
                    'last_scraped' => $place['last_scraped'] ?? now()->toIso8601String(),
                ]
            );
        }
        $this->info("Successfully synced " . count($places) . " places.");

        // 2. Count total reviews
        $countStmt = $pdo->query("SELECT COUNT(*) as total FROM reviews");
        $totalItems = $countStmt->fetch(\PDO::FETCH_ASSOC)['total'];

        $this->info("Found {$totalItems} reviews in SQLite. Processing...");

        $bar = $this->output->createProgressBar($totalItems);
        $bar->start();

        $importedCount = 0;
        
        // Use a cursor to fetch row by row to prevent memory exhaustion
        $reviewStmt = $pdo->query("SELECT * FROM reviews");
        
        while ($row = $reviewStmt->fetch(\PDO::FETCH_ASSOC)) {
            Review::updateOrCreate(
                [
                    'review_id' => $row['review_id'],
                    'place_id' => $row['place_id']
                ],
                [
                    'author' => $row['author'] ?? null,
                    'rating' => $row['rating'] ?? null,
                    'review_text' => $row['review_text'] ?? null, // already JSON string in sqlite
                    'review_date' => $row['review_date'] ?? null,
                    'raw_date' => $row['raw_date'] ?? null,
                    'likes' => $row['likes'] ?? 0,
                    'user_images' => $row['user_images'] ?? null, // already JSON string in sqlite
                    's3_images' => $row['s3_images'] ?? null, // already JSON string in sqlite
                    'profile_url' => $row['profile_url'] ?? null,
                    'profile_picture' => $row['profile_picture'] ?? null,
                    's3_profile_picture' => $row['s3_profile_picture'] ?? null,
                    'owner_responses' => $row['owner_responses'] ?? null, // already JSON string in sqlite
                    'created_date' => $row['created_date'] ?? now()->toIso8601String(),
                    'last_modified' => $row['last_modified'] ?? now()->toIso8601String(),
                    'last_seen_session' => $row['last_seen_session'] ?? null,
                    'last_changed_session' => $row['last_changed_session'] ?? null,
                    'is_deleted' => $row['is_deleted'] ?? 0,
                    'content_hash' => $row['content_hash'] ?? null,
                    'engagement_hash' => $row['engagement_hash'] ?? null,
                    'row_version' => $row['row_version'] ?? 1,
                    'sub_ratings' => $row['sub_ratings'] ?? null, // already JSON string in sqlite
                ]
            );
            $importedCount++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Successfully synced {$importedCount} reviews.");

        // 3. Recalculate total review counts for places
        $this->info("Recalculating total review counts for places...");
        $places = Place::all();
        foreach ($places as $place) {
            $count = Review::where('place_id', $place->place_id)->count();
            $place->update(['total_reviews' => $count]);
        }
        $this->info("Done!");

        return 0;
    }

    /**
     * Import reviews from JSON file
     *
     * @param string $filePath
     * @return int
     */
    protected function importFromJSON($filePath)
    {
        $this->info("Reading file: " . $filePath);
        $jsonContent = file_get_contents($filePath);
        $items = json_decode($jsonContent, true);

        if (!is_array($items)) {
            $this->error("Invalid JSON format. Expected an array of reviews.");
            return 1;
        }

        // If it's a dictionary of lists (grouped by place_id), flatten it first
        $reviews = [];
        $isGrouped = false;
        foreach ($items as $key => $value) {
            if (is_array($value) && is_string($key)) {
                $isGrouped = true;
                foreach ($value as $review) {
                    $reviews[] = $review;
                }
            } else {
                $reviews[] = $value;
            }
        }
        $items = $reviews;

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
                // Try to find existing place name to avoid 'Toko Google Maps' override
                $existingPlace = Place::where('place_id', $placeId)->first();
                $placeName = $item['company'] ?? ($existingPlace ? $existingPlace->place_name : 'Toko Google Maps');

                $placeMap[$placeId] = [
                    'place_name' => $placeName,
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
                    'review_text' => isset($item['review_text']) ? json_encode($item['review_text'], JSON_UNESCAPED_UNICODE) : (isset($item['description']) ? json_encode($item['description'], JSON_UNESCAPED_UNICODE) : null),
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
                    'last_modified' => $item['last_modified'] ?? $item['last_modified_date'] ?? now()->toIso8601String(),
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

    /**
     * Export and sync places from SQLite using a temporary Python script
     * (used as fallback when pdo_sqlite is missing in PHP)
     *
     * @param string $dbPath
     * @return void
     */
    protected function syncPlacesFromSQLiteViaPython($dbPath)
    {
        $this->info("Exporting places from SQLite using Python...");
        
        $scriptPath = base_path('temp_places_export.py');
        $pythonScript = <<<'PY'
import sqlite3
import json
import sys

db_path = sys.argv[1]
conn = sqlite3.connect(db_path)
conn.row_factory = sqlite3.Row
cursor = conn.cursor()
try:
    cursor.execute("SELECT place_id, place_name, original_url, first_seen, last_scraped FROM places")
    places = [dict(row) for row in cursor.fetchall()]
    print(json.dumps(places))
except Exception as e:
    print(json.dumps([]))
finally:
    conn.close()
PY;

        file_put_contents($scriptPath, $pythonScript);
        
        $cmd = "python " . escapeshellarg($scriptPath) . " " . escapeshellarg($dbPath);
        $output = shell_exec($cmd);
        
        @unlink($scriptPath);
        
        if (empty($output)) {
            $this->warn("No output from place exporter script.");
            return;
        }
        
        $places = json_decode($output, true);
        if (!is_array($places)) {
            $this->warn("Invalid response from place exporter script.");
            return;
        }
        
        foreach ($places as $place) {
            Place::updateOrCreate(
                ['place_id' => $place['place_id']],
                [
                    'place_name' => $place['place_name'] ?? 'Toko Google Maps',
                    'original_url' => $place['original_url'] ?? ('https://www.google.com/maps/place/?q=place_id:' . $place['place_id']),
                    'first_seen' => $place['first_seen'] ?? now()->toIso8601String(),
                    'last_scraped' => $place['last_scraped'] ?? now()->toIso8601String(),
                ]
            );
        }
        
        $this->info("Successfully synced " . count($places) . " places using Python script.");
    }
}
