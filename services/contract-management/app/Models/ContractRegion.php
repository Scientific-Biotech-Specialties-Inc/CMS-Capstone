<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractRegion extends Model
{
    protected $table = 'contract_regions';
    protected $primaryKey = 'region_id';
    public $timestamps = false;
    protected $fillable = ['region_name'];
}
