<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractApprovalStatus extends Model
{
    protected $table      = 'contract_approval_statuses';
    protected $primaryKey = 'approval_status_id';
    public    $timestamps = false;

    protected $fillable = ['status_name'];
}
