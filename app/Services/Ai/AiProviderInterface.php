<?php

namespace App\Services\Ai;

interface AiProviderInterface
{
    /**
     * Analyze a batch of reviews.
     *
     * Each review in the batch should contain:
     * - review_id (string)
     * - rating (int)
     * - review_text (string)
     *
     * The method must return an array of results where each item maps to a review, containing:
     * - review_id (string)
     * - category (string) - POSITIF, NEGATIF, CAMPURAN, SPAM_PROMOSI, INDIKASI_PENIPUAN, PERTANYAAN
     * - spam_label (string) - spam (if category is SPAM_PROMOSI or INDIKASI_PENIPUAN), ham (otherwise)
     * - confidence (float)
     * - reason (string)
     * - execution_time_ms (float) - apportioned execution time for this review
     * - input_tokens (int|null) - apportioned input tokens
     * - output_tokens (int|null) - apportioned output tokens
     * - cost (float|null) - apportioned cost in USD
     * - raw_response (string) - raw response from the provider
     *
     * @param array $reviews
     * @return array
     */
    public function analyzeBatch(array $reviews): array;
}
