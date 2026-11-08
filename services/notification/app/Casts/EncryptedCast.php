<?php

namespace App\Casts;

use App\Services\EncryptionService;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class EncryptedCast implements CastsAttributes
{
    protected EncryptionService $encryptionService;

    public function __construct()
    {
        $this->encryptionService = resolve(EncryptionService::class);
    }

    /**
     * Cast the given value (from database to Eloquent model).
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value !== null ? $this->encryptionService->decrypt($value) : null;
    }

    /**
     * Prepare the given value for storage (from Eloquent model to database).
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value !== null ? $this->encryptionService->encrypt($value) : null;
    }
}
