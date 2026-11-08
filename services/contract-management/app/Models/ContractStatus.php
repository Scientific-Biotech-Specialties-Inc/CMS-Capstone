<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractStatus extends Model
{
    protected $table = 'contract_statuses';
    protected $primaryKey = 'status_id';
    public $timestamps = false;
    protected $fillable = ['status_name', 'color_code'];
}
