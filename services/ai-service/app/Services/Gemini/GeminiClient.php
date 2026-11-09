<?php

namespace App\Services\Gemini;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin REST client for the Gemini API (no official Google PHP SDK exists,
 * so this wraps the plain HTTP endpoints via Laravel's Http facade). Tries
 * GEMINI_MODEL_PRIMARY first; on a 429/5xx/timeout it retries once against
 * GEMINI_MODEL_FALLBACK so a single model outage/rate-limit doesn't hard-fail
 * the RAG pipeline or vendor-suggestion generation.
 */
class GeminiClient
{
    protected string $apiKey;
    protected string $primaryModel;
    protected string $fallbackModel;
    protected string $embeddingModel;
    protected int $embeddingDimensions;
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct()
    {
        $this->apiKey              = (string) env('GEMINI_API_KEY', '');
        $this->primaryModel        = (string) env('GEMINI_MODEL_PRIMARY', 'gemini-2.5-flash-lite');
        $this->fallbackModel       = (string) env('GEMINI_MODEL_FALLBACK', 'gemini-2.5-flash');
        $this->embeddingModel      = (string) env('GEMINI_EMBEDDING_MODEL', 'gemini-embedding-001');
        $this->embeddingDimensions = (int) env('GEMINI_EMBEDDING_DIMENSIONS', 1536);
    }

    /**
     * Generate content with a structured JSON response, constrained by the
     * given response schema. Returns the decoded JSON array from Gemini's
     * response, or null if both the primary and fallback model calls fail.
     */
    public function generateJson(string $systemInstruction, string $userPrompt, array $responseSchema): ?array
    {
        $result = $this->callGenerateWithRetry($this->primaryModel, $systemInstruction, $userPrompt, $responseSchema);

        if ($result === null) {
            Log::warning('Gemini primary model failed after retries, trying fallback model.', [
                'primary_model'  => $this->primaryModel,
                'fallback_model' => $this->fallbackModel,
            ]);
            $result = $this->callGenerateWithRetry($this->fallbackModel, $systemInstruction, $userPrompt, $responseSchema);
        }

        return $result;
    }

    /**
     * Retry wrapper: only retries on 429 (rate-limit) and 503 (overload).
     * Hard failures like 404 (model not found) return null immediately.
     */
    protected function callGenerateWithRetry(string $model, string $systemInstruction, string $userPrompt, array $responseSchema): ?array
    {
        $delays = app()->environment('testing') ? [0] : [2, 5]; // in testing, avoid sleeping and extra retries
        foreach ($delays as $i => $delay) {
            [$result, $shouldRetry] = $this->callGenerateRaw($model, $systemInstruction, $userPrompt, $responseSchema);
            if ($result !== null) {
                return $result;
            }
            if (!$shouldRetry) {
                // Hard failure (e.g. 404 model not found) — no point retrying
                return null;
            }
            if ($i < count($delays) - 1) {
                Log::info('Gemini call rate-limited/overloaded, retrying shortly.', [
                    'model'        => $model,
                    'attempt'      => $i + 1,
                    'wait_seconds' => $delay,
                ]);
                sleep($delay);
            }
        }
        return null;
    }

    protected function callGenerate(string $model, string $systemInstruction, string $userPrompt, array $responseSchema): ?array
    {
        [$result] = $this->callGenerateRaw($model, $systemInstruction, $userPrompt, $responseSchema);
        return $result;
    }

    /**
     * Returns [result|null, shouldRetry].
     */
    protected function callGenerateRaw(string $model, string $systemInstruction, string $userPrompt, array $responseSchema): array
    {
        if (!$this->apiKey) {
            Log::error('Gemini API call skipped: GEMINI_API_KEY is not configured.');
            return [null, false];
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("{$this->baseUrl}/models/{$model}:generateContent?key={$this->apiKey}", [
                    'systemInstruction' => [
                        'parts' => [['text' => $systemInstruction]],
                    ],
                    'contents' => [
                        ['role' => 'user', 'parts' => [['text' => $userPrompt]]],
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'responseSchema'   => $responseSchema,
                    ],
                ]);

            if (!$response->successful()) {
                $status = $response->status();
                Log::warning('Gemini generateContent call failed.', [
                    'model'  => $model,
                    'status' => $status,
                    'body'   => $response->body(),
                ]);
                // 429 = rate limit, 503 = overload → worth retrying
                $retryable = in_array($status, [429, 503]);
                return [null, $retryable];
            }

            $text = $response->json('candidates.0.content.parts.0.text');
            if (!$text) {
                return [null, false];
            }

            $decoded = json_decode($text, true);
            return [is_array($decoded) ? $decoded : null, false];
        } catch (\Exception $e) {
            Log::error('Gemini generateContent connection error.', [
                'model'   => $model,
                'message' => $e->getMessage(),
            ]);
            return [null, true]; // connection errors are worth retrying
        }
    }

    /**
     * Embed a piece of text, truncated to GEMINI_EMBEDDING_DIMENSIONS (1536
     * by default, matching the existing pgvector `vector(1536)` column).
     * Returns null on failure so callers can skip/retry rather than crash.
     *
     * @return list<float>|null
     */
    public function embed(string $text): ?array
    {
        if (!$this->apiKey) {
            Log::error('Gemini embedding call skipped: GEMINI_API_KEY is not configured.');
            return null;
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("{$this->baseUrl}/models/{$this->embeddingModel}:embedContent?key={$this->apiKey}", [
                    'model'   => "models/{$this->embeddingModel}",
                    'content' => ['parts' => [['text' => $text]]],
                    'outputDimensionality' => $this->embeddingDimensions,
                ]);

            if (!$response->successful()) {
                Log::warning('Gemini embedContent call failed.', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $values = $response->json('embedding.values');
            return is_array($values) ? array_map('floatval', $values) : null;
        } catch (\Exception $e) {
            Log::error('Gemini embedContent connection error.', ['message' => $e->getMessage()]);
            return null;
        }
    }
}
