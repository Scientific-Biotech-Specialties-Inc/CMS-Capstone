<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Product extends Model
{
    protected $fillable = [
        'prs_product_id',
        'item_code',
        'serial_number',
        'product_name',
        'sbu_number',
        'sbu',
        'description',
        'category',
        'manufacturer',
        'brand',
        'model',
        'is_active',
    ];

    protected $casts = [
        'prs_product_id' => 'integer',
        'sbu'            => 'integer',
        'is_active'      => 'boolean',
    ];

    public static function parseSbuInt(string|int|null $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) || ctype_digit((string) $value)) {
            $int = (int) $value;
            return ($int >= 0 && $int <= 255) ? $int : null;
        }
        if (preg_match('/(\d+)/', (string) $value, $m) === 1) {
            $int = (int) $m[1];
            return ($int >= 0 && $int <= 255) ? $int : null;
        }

        return null;
    }

    public static function formatSbuNumber(string|int|null $value): string
    {
        if (is_string($value) && preg_match('/^SBU-\d{3}$/', trim($value)) === 1) {
            return trim($value);
        }
        $int = self::parseSbuInt($value);
        if ($int !== null) {
            return 'SBU-' . str_pad((string) $int, 3, '0', STR_PAD_LEFT);
        }

        return (string) ($value ?: 'SBU-001');
    }

    public function catalogDetails(): array
    {
        return [
            'product_id'   => $this->id,
            'item_code'    => $this->item_code,
            'product_name' => $this->product_name,
            'sbu_number'   => $this->sbu_number,
            'description'  => $this->description,
            'category'     => $this->category,
            'manufacturer' => $this->manufacturer,
            'model'        => $this->model,
            'is_active'    => $this->is_active,
        ];
    }

    public function toPrsProductPayload(): array
    {
        return [
            'id'             => (int) ($this->prs_product_id ?? $this->id),
            'product_id'     => (int) $this->id,
            'prs_product_id' => $this->prs_product_id !== null ? (int) $this->prs_product_id : null,
            'product_name'   => $this->product_name,
            'product_code'   => $this->item_code,
            'item_code'      => $this->item_code,
            'serial_number'  => $this->serial_number,
            'sbu'            => $this->sbu ?? self::parseSbuInt($this->sbu_number),
            'sbu_number'     => $this->sbu_number ?: self::formatSbuNumber($this->sbu),
            'category'       => $this->category,
            'manufacturer'   => $this->manufacturer,
            'brand'          => $this->brand ?? $this->manufacturer,
            'model'          => $this->model,
            'is_active'      => (bool) $this->is_active,
            'description'    => $this->description,
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
