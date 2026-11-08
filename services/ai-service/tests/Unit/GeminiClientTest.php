<?php

namespace Tests\Unit;

use App\Services\Gemini\GeminiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class GeminiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['GEMINI_API_KEY'] = 'test-key';
        putenv('GEMINI_API_KEY=test-key');
        putenv('GEMINI_MODEL_PRIMARY=gemini-2.5-flash-lite');
        putenv('GEMINI_MODEL_FALLBACK=gemini-2.5-flash');
    }

    public function test_generate_json_uses_primary_model_when_it_succeeds(): void
    {
        Http::fake([
            '*gemini-2.5-flash-lite:generateContent*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode(['deviates' => true, 'severity' => 'high'])]]]],
                ],
            ], 200),
        ]);

        $client = new GeminiClient();
        $result = $client->generateJson('system', 'prompt', ['type' => 'object']);

        $this->assertSame(['deviates' => true, 'severity' => 'high'], $result);
        Http::assertSentCount(1);
    }

    public function test_generate_json_falls_back_to_secondary_model_on_primary_failure(): void
    {
        Http::fake([
            '*gemini-2.5-flash-lite:generateContent*' => Http::response(['error' => 'rate limited'], 429),
            '*gemini-2.5-flash:generateContent*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode(['deviates' => false])]]]],
                ],
            ], 200),
        ]);

        $client = new GeminiClient();
        $result = $client->generateJson('system', 'prompt', ['type' => 'object']);

        $this->assertSame(['deviates' => false], $result);
        Http::assertSentCount(2);
    }

    public function test_generate_json_returns_null_when_both_models_fail(): void
    {
        Http::fake([
            '*generateContent*' => Http::response(['error' => 'server error'], 500),
        ]);

        $client = new GeminiClient();
        $result = $client->generateJson('system', 'prompt', ['type' => 'object']);

        $this->assertNull($result);
        Http::assertSentCount(2);
    }

    public function test_embed_returns_vector_from_response(): void
    {
        Http::fake([
            '*embedContent*' => Http::response([
                'embedding' => ['values' => [0.1, 0.2, 0.3]],
            ], 200),
        ]);

        $client = new GeminiClient();
        $result = $client->embed('some text');

        $this->assertSame([0.1, 0.2, 0.3], $result);
    }

    public function test_embed_returns_null_on_failure(): void
    {
        Http::fake([
            '*embedContent*' => Http::response(['error' => 'bad request'], 400),
        ]);

        $client = new GeminiClient();
        $this->assertNull($client->embed('some text'));
    }

    public function test_embed_returns_null_when_api_key_missing(): void
    {
        // Fake HTTP *before* anything else so that even if the env-var
        // clearing below is imperfect (e.g. a real key already loaded into
        // $_SERVER by Dotenv at boot from the committed .env file), no real
        // network call to Gemini's API can occur during this test.
        Http::fake();

        putenv('GEMINI_API_KEY');
        unset($_ENV['GEMINI_API_KEY'], $_SERVER['GEMINI_API_KEY']);

        $client = new GeminiClient();
        $this->assertNull($client->embed('some text'));

        Http::assertNothingSent();
    }
}
