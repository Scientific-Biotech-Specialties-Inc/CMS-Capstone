<?php

namespace App\Services\Gemini;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin REST client for the Gemini API, duplicated from ai-service's
 * GeminiClient rather than adding an inter-service dependency (per the
 * plan's decision — this is a ~100-line wrapper, not a shared library).
 * Used only for the narrow "summarize this already-computed structured
 * diagnostic result in plain language" role — never to compute the
 * diagnosis itself.
 */
class GeminiClient
{
    protected string $apiKey;
    protected string $primaryModel;
    protected string $fallbackModel;
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct()
    {
        $this->apiKey        = (string) env('GEMINI_API_KEY', '');
        $this->primaryModel  = (string) env('GEMINI_MODEL_PRIMARY', 'gemini-2.5-flash-lite');
        $this->fallbackModel = (string) env('GEMINI_MODEL_FALLBACK', 'gemini-2.5-flash');
    }

    /**
     * Generates a plain-text narrative for the given prompt. Returns null on
     * failure (both primary and fallback models) — callers should degrade
     * gracefully to "no AI narrative available" rather than block on this.
     */
    public function generateText(string $systemInstruction, string $userPrompt): ?string
    {
        $result = $this->callGenerate($this->primaryModel, $systemInstruction, $userPrompt);

        if ($result === null) {
            Log::warning('Gemini primary model call failed, retrying with fallback model.', [
                'primary_model'  => $this->primaryModel,
                'fallback_model' => $this->fallbackModel,
            ]);
            $result = $this->callGenerate($this->fallbackModel, $systemInstruction, $userPrompt);
        }

        return $result;
    }

    protected function callGenerate(string $model, string $systemInstruction, string $userPrompt): ?string
    {
        if (!$this->apiKey) {
            Log::error('Gemini API call skipped: GEMINI_API_KEY is not configured.');
            return null;
        }

        try {
            $response = Http::timeout(60)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("{$this->baseUrl}/models/{$model}:generateContent?key={$this->apiKey}", [
                    'systemInstruction' => [
                        'parts' => [['text' => $systemInstruction]],
                    ],
                    'contents' => [
                        ['role' => 'user', 'parts' => [['text' => $userPrompt]]],
                    ],
                ]);

            if (!$response->successful()) {
                Log::warning('Gemini generateContent call failed.', [
                    'model'  => $model,
                    'status' => $response->status(),
                ]);
                return null;
            }

            return $response->json('candidates.0.content.parts.0.text');
        } catch (\Exception $e) {
            Log::error('Gemini generateContent connection error.', [
                'model'   => $model,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
