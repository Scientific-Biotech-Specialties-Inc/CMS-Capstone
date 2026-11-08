<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractCategory extends Model
{
    protected $table = 'contract_categories';
    protected $primaryKey = 'category_id';
    public $timestamps = false;
    protected $fillable = ['category_name', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function workflows()
    {
        return $this->hasMany(Workflow::class, 'contract_type_id', 'category_id');
    }

    public function activeWorkflow()
    {
        return $this->hasOne(Workflow::class, 'contract_type_id', 'category_id')->where('status', 'active');
    }
}
