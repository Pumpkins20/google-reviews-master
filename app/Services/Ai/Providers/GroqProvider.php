<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class GroqProvider implements AiProviderInterface
{
    protected $config;
    protected $model;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->model = $config['model'] ?? 'llama-3.1-8b-instant';
    }

    public function analyzeBatch(array $reviews): array
    {
        $apiKey = $this->config['api_key'] ?? '';
        if (empty($apiKey)) {
            throw new Exception("Groq API key is not configured.");
        }

        $apiUrl = $this->config['api_url'] ?? 'https://api.groq.com/openai/v1/chat/completions';
        
        $idMap = [];
        $reviewsForPrompt = [];
        $counter = 1;
        foreach ($reviews as $review) {
            $tempId = "rev-" . $counter++;
            $idMap[$tempId] = $review['review_id'];
            $reviewsForPrompt[] = [
                'review_id' => $tempId,
                'rating' => $review['rating'],
                'review_text' => $review['review_text'] ?? '',
            ];
        }

        $prompt = strtr(config('ai.prompt_template'), [
            '{reviews_json}' => json_encode($reviewsForPrompt, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ]);

        $startTime = microtime(true);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(45)->post($apiUrl, [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'response_format' => [
                'type' => 'json_object'
            ]
        ]);

        $endTime = microtime(true);
        $executionTimeMs = ($endTime - $startTime) * 1000;

        if ($response->status() === 429) {
            throw new Exception("Groq API Rate Limit exceeded (429).");
        }

        if ($response->failed()) {
            throw new Exception("Groq API call failed: " . $response->status() . " - " . $response->body());
        }

        $rawResponse = $response->json();
        $textResponse = $response->json('choices.0.message.content');

        if (empty($textResponse)) {
            throw new Exception("Groq returned an empty text response. Raw response: " . json_encode($rawResponse));
        }

        $parsedData = json_decode($textResponse, true);

        if (!is_array($parsedData) || !isset($parsedData['reviews']) || !is_array($parsedData['reviews'])) {
            throw new Exception("Groq output format was invalid (expected key 'reviews'): " . $textResponse);
        }

        $tokenMetadata = $rawResponse['usage'] ?? [];
        $inputTokens = $tokenMetadata['prompt_tokens'] ?? 0;
        $outputTokens = $tokenMetadata['completion_tokens'] ?? 0;

        // Pricing lookup
        $pricing = config("ai.pricing.{$this->model}", [
            'input' => 0.05,
            'output' => 0.08
        ]);
        
        $totalCost = (($inputTokens * $pricing['input']) + ($outputTokens * $pricing['output'])) / 1000000;

        $results = [];
        $totalReviews = count($reviews);
        $apportionedTime = $executionTimeMs / max(1, $totalReviews);
        $apportionedInputTokens = $inputTokens / max(1, $totalReviews);
        $apportionedOutputTokens = $outputTokens / max(1, $totalReviews);
        $apportionedCost = $totalCost / max(1, $totalReviews);

        // Index the response reviews by mapping the temp ID to the original ID
        $parsedReviewsMap = [];
        foreach ($parsedData['reviews'] as $parsedReview) {
            if (isset($parsedReview['review_id'])) {
                $tempId = trim($parsedReview['review_id']);
                
                // Direct lookup
                if (isset($idMap[$tempId])) {
                    $originalId = $idMap[$tempId];
                    $parsedReviewsMap[$originalId] = $parsedReview;
                } else {
                    // Robust lookup by extracting numeric part
                    preg_match('/\d+/', $tempId, $matches);
                    $num = $matches[0] ?? null;
                    if ($num !== null) {
                        $normalizedTempId = "rev-" . $num;
                        if (isset($idMap[$normalizedTempId])) {
                            $originalId = $idMap[$normalizedTempId];
                            $parsedReviewsMap[$originalId] = $parsedReview;
                        }
                    }
                }
            }
        }

        foreach ($reviews as $originalReview) {
            $id = $originalReview['review_id'];
            
            if (isset($parsedReviewsMap[$id])) {
                $parsed = $parsedReviewsMap[$id];
                $category = $parsed['kategori'] ?? 'POSITIF';
                $spamLabel = in_array($category, ['SPAM_PROMOSI', 'INDIKASI_PENIPUAN']) ? 'spam' : 'ham';

                $results[] = [
                    'review_id' => $id,
                    'category' => $category,
                    'spam_label' => $spamLabel,
                    'confidence' => $parsed['confidence'] ?? 0.5,
                    'reason' => $parsed['alasan'] ?? '',
                    'execution_time_ms' => round($apportionedTime, 2),
                    'input_tokens' => (int) round($apportionedInputTokens),
                    'output_tokens' => (int) round($apportionedOutputTokens),
                    'cost' => round($apportionedCost, 6),
                    'raw_response' => json_encode($rawResponse),
                ];
            } else {
                Log::warning("Review ID {$id} was sent to Groq but not returned in the structured output.");
                $results[] = [
                    'review_id' => $id,
                    'category' => 'PERTANYAAN',
                    'spam_label' => 'ham',
                    'confidence' => 0.5,
                    'reason' => 'Gagal dianalisis oleh Groq (ID tidak dikembalikan oleh API).',
                    'execution_time_ms' => round($apportionedTime, 2),
                    'input_tokens' => (int) round($apportionedInputTokens),
                    'output_tokens' => (int) round($apportionedOutputTokens),
                    'cost' => round($apportionedCost, 6),
                    'raw_response' => json_encode($rawResponse),
                ];
            }
        }

        return $results;
    }
}
