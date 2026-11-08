<?php

namespace Tests\Unit;

use App\Services\EncryptionService;
use Tests\TestCase;

class EncryptionServiceTest extends TestCase
{
    protected EncryptionService $encryptionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->encryptionService = resolve(EncryptionService::class);
    }

    public function test_encrypt_returns_non_plaintext_string(): void
    {
        $plaintext = 'Secret Supplier Info';
        $ciphertext = $this->encryptionService->encrypt($plaintext);

        $this->assertNotEmpty($ciphertext);
        $this->assertNotEquals($plaintext, $ciphertext);
    }

    public function test_decrypt_returns_original_value(): void
    {
        $plaintext = 'Secret Supplier Info';
        $ciphertext = $this->encryptionService->encrypt($plaintext);
        $decrypted = $this->encryptionService->decrypt($ciphertext);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function test_null_value_returns_null(): void
    {
        $this->assertNull($this->encryptionService->encrypt(null));
        $this->assertNull($this->encryptionService->decrypt(null));
    }

    public function test_empty_value_returns_empty(): void
    {
        $this->assertEquals('', $this->encryptionService->encrypt(''));
        $this->assertEquals('', $this->encryptionService->decrypt(''));
    }

    public function test_different_encryptions_of_same_value_differ_due_to_random_iv(): void
    {
        $plaintext = 'Secret Supplier Info';
        $ciphertext1 = $this->encryptionService->encrypt($plaintext);
        $ciphertext2 = $this->encryptionService->encrypt($plaintext);

        $this->assertNotEquals($ciphertext1, $ciphertext2);
        $this->assertEquals($plaintext, $this->encryptionService->decrypt($ciphertext1));
        $this->assertEquals($plaintext, $this->encryptionService->decrypt($ciphertext2));
    }
}
