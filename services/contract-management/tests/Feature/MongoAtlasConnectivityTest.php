<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * @group atlas
 */
class MongoAtlasConnectivityTest extends TestCase
{
    /**
     * Test connectivity to the real MongoDB Atlas cluster.
     * It writes a probe document, verifies it, and deletes it.
     */
    public function test_atlas_cluster_connectivity_and_operations()
    {
        // 1. Guard check: ensure we are actually hitting Atlas, not the old local URI
        $uri = env('MONGODB_URI');
        $this->assertStringContainsString(
            'mongodb+srv://', 
            $uri, 
            'The MONGODB_URI in the environment must be an Atlas SRV string.'
        );

        // 2. Insert a probe document
        $collection = DB::connection('mongodb')->table('connectivity_checks');
        
        $probeId = 'probe_' . uniqid();
        $inserted = $collection->insert([
            'probe_id' => $probeId,
            'message' => 'Atlas connectivity check successful',
            'created_at' => now()->toDateTimeString(),
        ]);

        $this->assertTrue($inserted, 'Failed to insert probe document into Atlas cluster.');

        // 3. Read the document back
        $document = $collection->where('probe_id', $probeId)->first();
        
        $this->assertNotNull($document, 'Probe document could not be read back from Atlas.');
        $this->assertEquals('Atlas connectivity check successful', $document->message);

        // 4. Clean up the probe document
        $deleted = $collection->where('probe_id', $probeId)->delete();
        
        $this->assertEquals(1, $deleted, 'Failed to clean up the probe document from Atlas.');
    }
}
