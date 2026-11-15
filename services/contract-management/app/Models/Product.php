<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Product extends Model
{
    protected $fillable = [
        'item_code', 'product_name', 'sbu_number', 'description', 'category',
        'manufacturer', 'model', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function catalogDetails(): array
    {
        return ['product_id' => $this->id, ...$this->only($this->fillable)];
    }
}
